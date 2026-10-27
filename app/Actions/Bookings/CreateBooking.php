<?php

namespace App\Actions\Bookings;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Exceptions\BookingUnavailable;
use App\Models\Booking;
use App\Models\Customer;
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
     * @param  array{name: string, email: string, phone?: ?string, company?: ?string, whatsapp_opt_in?: bool}  $customer
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
    ): Booking {
        return DB::transaction(function () use ($space, $start, $end, $customer, $attendees, $notes, $source, $createdBy) {
            // Serialize bookings per space so two requests can't take the same slot.
            $space = Space::whereKey($space->getKey())->lockForUpdate()->firstOrFail();

            $byStaff = $source === BookingSource::Admin;

            $this->availability->ensureBookable($space, $start, $end, $attendees, enforceWindow: ! $byStaff);

            $customer = $this->upsertCustomer($customer, $byStaff);

            $status = $space->requires_approval && ! $byStaff ? BookingStatus::Pending : BookingStatus::Confirmed;

            $booking = $space->bookings()->create([
                'customer_id' => $customer->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'attendees' => $attendees,
                'notes' => $notes,
                'status' => $status,
                'source' => $source,
                'confirmed_at' => $status === BookingStatus::Confirmed ? now() : null,
            ]);

            $booking->events()->create([
                'user_id' => $createdBy?->id,
                'to_status' => $status,
            ]);

            return $booking;
        });
    }

    /**
     * @param  array{name: string, email: string, phone?: ?string, company?: ?string, whatsapp_opt_in?: bool}  $data
     */
    private function upsertCustomer(array $data, bool $byStaff): Customer
    {
        $customer = Customer::firstOrNew(['email' => Str::lower(trim($data['email']))]);

        $customer->fill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? $customer->phone,
            'company' => $data['company'] ?? $customer->company,
        ]);

        // Consent is only recorded when the customer gives it themselves on the site.
        if (! $byStaff) {
            $customer->whatsapp_opt_in = $data['whatsapp_opt_in'] ?? false;
            $customer->privacy_accepted_at = now();
        }

        $customer->save();

        return $customer;
    }
}
