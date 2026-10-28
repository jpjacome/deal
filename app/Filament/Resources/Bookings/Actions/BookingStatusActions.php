<?php

namespace App\Filament\Resources\Bookings\Actions;

use App\Actions\Bookings\ChangeBookingStatus;
use App\Enums\BookingStatus;
use App\Models\Booking;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Confirm / reject / cancel / complete / no-show, shared by the bookings table and the booking page.
 */
class BookingStatusActions
{
    /**
     * @return list<Action>
     */
    public static function make(): array
    {
        return [
            self::action('confirm', BookingStatus::Confirmed, 'Confirmar', Heroicon::OutlinedCheckCircle, 'success'),
            self::action('reject', BookingStatus::Rejected, 'Rechazar', Heroicon::OutlinedXCircle, 'danger', askReason: true),
            self::action('cancel', BookingStatus::Cancelled, 'Cancelar', Heroicon::OutlinedNoSymbol, 'danger', askReason: true),
            self::action('complete', BookingStatus::Completed, 'Marcar como completada', Heroicon::OutlinedCheckBadge, 'info', afterStartOnly: true),
            self::action('noShow', BookingStatus::NoShow, 'No asistió', Heroicon::OutlinedUserMinus, 'gray', afterStartOnly: true),
        ];
    }

    private static function action(
        string $name,
        BookingStatus $to,
        string $label,
        Heroicon $icon,
        string $color,
        bool $askReason = false,
        bool $afterStartOnly = false,
    ): Action {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->visible(fn (Booking $record): bool => $record->status->canTransitionTo($to)
                && (! $afterStartOnly || $record->starts_at->isPast()))
            ->requiresConfirmation()
            ->modalHeading(fn (Booking $record): string => "{$label} la reserva {$record->code}")
            ->schema($askReason ? [
                Textarea::make('reason')
                    ->label('Motivo')
                    ->helperText('El cliente lo verá en su reserva.')
                    ->rows(3),
            ] : [])
            ->action(function (Booking $record, array $data) use ($to): void {
                try {
                    app(ChangeBookingStatus::class)->handle($record, $to, auth()->user(), $data['reason'] ?? null);
                } catch (DomainException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                $record->refresh();

                Notification::make()->success()->title("Reserva {$record->code}: {$to->getLabel()}")->send();
            });
    }
}
