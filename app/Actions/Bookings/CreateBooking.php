<?php

namespace App\Actions\Bookings;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Events\BookingCreated;
use App\Exceptions\BookingUnavailable;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\Space;
use App\Models\User;
use App\Services\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateBooking
{
    public function __construct(private Availability $availability) {}

    /**
     * @param  array{name: string, email?: ?string, phone?: ?string, company?: ?string, whatsapp_opt_in?: bool}  $customer
     *
     * @throws BookingUnavailable
     */
    public function handle(
        Space $space,
        CarbonImmutable $start,
        CarbonImmutable $end,
        array $customer,
        int $attendees = 1,
        ?string $notes = null,
        BookingSource $source = BookingSource::Web,
        ?User $createdBy = null,
        bool $notifyCustomer = true,
        ?string $seriesId = null,
    ): Booking {
        return DB::transaction(function () use ($space, $start, $end, $customer, $attendees, $notes, $source, $createdBy, $notifyCustomer, $seriesId) {
            // Serialize bookings per space so two requests can't take the same slot.
            $space = Space::whereKey($space->getKey())->lockForUpdate()->firstOrFail();

            $byStaff = $source === BookingSource::Admin;

            $this->availability->ensureBookable($space, $start, $end, $attendees, strict: ! $byStaff);

            $customer = $byStaff ? $this->saveCustomerForStaff($customer) : $this->saveCustomerFromWebsite($customer);

            [$status, $reason] = $this->initialStatus($space, $customer, $byStaff);

            $booking = $space->bookings()->create([
                'customer_id' => $customer->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'attendees' => $attendees,
                'notes' => $notes,
                'status' => $status,
                'status_reason' => $reason,
                'source' => $source,
                'series_id' => $seriesId,
                'confirmed_at' => $status === BookingStatus::Confirmed ? now() : null,
            ]);

            $booking->events()->create([
                'user_id' => $createdBy?->id,
                'to_status' => $status,
                'note' => $reason,
            ]);

            BookingCreated::dispatch($booking, $notifyCustomer);

            return $booking;
        });
    }

    /**
     * @return array{0: BookingStatus, 1: ?string}
     */
    private function initialStatus(Space $space, Customer $customer, bool $byStaff): array
    {
        if ($byStaff) {
            return [BookingStatus::Confirmed, null];
        }

        if ($customer->hasReachedNoShowLimit()) {
            return [BookingStatus::Pending, 'Requiere confirmación por inasistencias previas.'];
        }

        return [$space->requires_approval ? BookingStatus::Pending : BookingStatus::Confirmed, null];
    }

    /**
     * Website bookings: match by email, record consent, and never overwrite an existing customer's
     * name or phone (someone else could type their email and redirect their notifications).
     *
     * @param  array{name: string, email?: ?string, phone?: ?string, company?: ?string, whatsapp_opt_in?: bool}  $data
     */
    private function saveCustomerFromWebsite(array $data): Customer
    {
        $customer = Customer::firstOrNew(['email' => Str::lower(trim((string) $data['email']))]);

        $customer->name = $customer->name ?: $data['name'];
        $customer->phone = $customer->phone ?: ($data['phone'] ?? null);
        $customer->company = $customer->company ?: ($data['company'] ?? null);
        $customer->whatsapp_opt_in = (bool) ($data['whatsapp_opt_in'] ?? false);
        $customer->privacy_accepted_at = now();
        $customer->privacy_version = (string) Setting::get('privacy_version');
        $customer->save();

        return $customer;
    }

    /**
     * Staff bookings: email is optional (walk-ins); match by email, then phone. Staff details win.
     *
     * @param  array{name: string, email?: ?string, phone?: ?string, company?: ?string}  $data
     */
    private function saveCustomerForStaff(array $data): Customer
    {
        $email = filled($data['email'] ?? null) ? Str::lower(trim($data['email'])) : null;
        $phone = $data['phone'] ?? null;

        $customer = match (true) {
            $email !== null => Customer::firstOrNew(['email' => $email]),
            filled($phone) => Customer::firstOrNew(['phone' => $phone]),
            default => new Customer,
        };

        $customer->fill(array_filter([
            'name' => $data['name'],
            'email' => $email,
            'phone' => $phone,
            'company' => $data['company'] ?? null,
        ], filled(...)));

        $customer->save();

        return $customer;
    }
}
