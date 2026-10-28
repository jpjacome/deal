<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Actions\Bookings\CreateBooking as CreateBookingAction;
use App\Actions\Bookings\CreateRecurringBookings;
use App\Enums\BookingSource;
use App\Exceptions\BookingUnavailable;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Space;
use App\Services\Availability;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $space = Space::findOrFail($data['space_id']);
        [$start, $end] = $this->timeRange($space, CarbonImmutable::parse($data['date'])->startOfDay(), $data);

        $customer = [
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => PhoneNumber::normalize($data['phone'] ?? null),
            'company' => $data['company'] ?? null,
        ];

        try {
            if ($data['repeat'] ?? false) {
                return $this->createSeries($space, $start, $end, $data, $customer);
            }

            return app(CreateBookingAction::class)->handle(
                $space,
                $start,
                $end,
                $customer,
                attendees: (int) $data['attendees'],
                notes: $data['notes'] ?? null,
                source: BookingSource::Admin,
                createdBy: auth()->user(),
                notifyCustomer: (bool) ($data['notify'] ?? true),
            );
        } catch (BookingUnavailable $e) {
            Notification::make()->danger()->title('No se pudo crear la reserva')->body($e->getMessage())->persistent()->send();

            $this->halt();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{name: string, email: ?string, phone: ?string, company: ?string}  $customer
     */
    private function createSeries(Space $space, CarbonImmutable $start, CarbonImmutable $end, array $data, array $customer): Model
    {
        $result = app(CreateRecurringBookings::class)->handle(
            $space,
            $start,
            $end,
            CarbonImmutable::parse($data['repeat_until']),
            $customer,
            (int) $data['attendees'],
            $data['notes'] ?? null,
            auth()->user(),
            (bool) ($data['notify'] ?? true),
        );

        if ($result['created'] === []) {
            throw new BookingUnavailable('Ninguna fecha de la serie está disponible.');
        }

        if ($result['skipped'] !== []) {
            Notification::make()
                ->warning()
                ->title(count($result['skipped']).' fecha(s) omitida(s)')
                ->body(collect($result['skipped'])->map(fn (string $reason, string $date) => CarbonImmutable::parse($date)->format('d/m').": {$reason}")->join("\n"))
                ->persistent()
                ->send();
        }

        return $result['created'][0];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function timeRange(Space $space, CarbonImmutable $date, array $data): array
    {
        $availability = app(Availability::class);

        if (! $space->booking_mode->isFullDay()) {
            return [$date->setTimeFromTimeString($data['start_time']), $date->setTimeFromTimeString($data['end_time'])];
        }

        $period = $data['period'] ?? 'full';

        if ($period !== 'full' && ($halves = $availability->halfDayBounds($space, $date))) {
            return $halves[$period];
        }

        // Closed day: let the availability check explain.
        return $availability->dayBounds($space, $date) ?? [$date, $date->addDay()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Reserva creada y confirmada';
    }
}
