<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BookingMode: string implements HasLabel
{
    /** Exclusive use, booked in time slots (meeting rooms). */
    case Hourly = 'hourly';

    /** Exclusive use, booked for the whole day (private office). */
    case Daily = 'daily';

    /** Shared use, booked for the whole day, up to the space capacity (hot desk). */
    case Capacity = 'capacity';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hourly => 'Por horas',
            self::Daily => 'Por día (uso exclusivo)',
            self::Capacity => 'Por día (puestos compartidos)',
        };
    }

    public function isFullDay(): bool
    {
        return $this !== self::Hourly;
    }
}
