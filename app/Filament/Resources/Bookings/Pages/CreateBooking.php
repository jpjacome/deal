<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Actions\Bookings\CreateBooking as CreateBookingAction;
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
        $date = CarbonImmutable::parse($data['date'])->startOfDay();

        if ($space->booking_mode->isFullDay()) {
            [$start, $end] = app(Availability::class)->dayBounds($space, $date) ?? [$date, $date->addDay()]; // closed day: let the availability check explain
        } else {
            $start = $date->setTimeFromTimeString($data['start_time']);
            $end = $date->setTimeFromTimeString($data['end_time']);
        }

        try {
            return app(CreateBookingAction::class)->handle(
                $space,
                $start,
                $end,
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => PhoneNumber::normalize($data['phone'] ?? null),
                    'company' => $data['company'] ?? null,
                ],
                attendees: (int) $data['attendees'],
                notes: $data['notes'] ?? null,
                source: BookingSource::Admin,
                createdBy: auth()->user(),
            );
        } catch (BookingUnavailable $e) {
            Notification::make()->danger()->title('No se pudo crear la reserva')->body($e->getMessage())->persistent()->send();

            $this->halt();
        }
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
