<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BookingStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Completed = 'completed';
    case NoShow = 'no_show';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Confirmed => 'Confirmada',
            self::Rejected => 'Rechazada',
            self::Cancelled => 'Cancelada',
            self::Expired => 'Expirada',
            self::Completed => 'Completada',
            self::NoShow => 'No asistió',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'success',
            self::Completed => 'info',
            self::Rejected, self::Cancelled, self::Expired, self::NoShow => 'gray',
        };
    }

    /**
     * Statuses that keep the time slot occupied.
     *
     * @return list<self>
     */
    public static function holdingSlot(): array
    {
        return [self::Pending, self::Confirmed];
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Rejected, self::Cancelled, self::Expired],
            self::Confirmed => [self::Cancelled, self::Completed, self::NoShow],
            default => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }
}
