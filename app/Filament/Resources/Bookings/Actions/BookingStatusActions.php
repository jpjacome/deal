<?php

namespace App\Filament\Resources\Bookings\Actions;

use App\Actions\Bookings\ChangeBookingStatus;
use App\Actions\Bookings\RescheduleBooking;
use App\Enums\BookingStatus;
use App\Exceptions\BookingUnavailable;
use App\Models\Booking;
use App\Models\Space;
use App\Services\Availability;
use Carbon\CarbonImmutable;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * Booking actions shared by the bookings table, the dashboard and the booking page.
 */
class BookingStatusActions
{
    /**
     * @return list<Action>
     */
    public static function make(): array
    {
        return [
            self::status('confirm', BookingStatus::Confirmed, 'Confirmar', Heroicon::OutlinedCheckCircle, 'success'),
            self::checkIn(),
            self::reschedule(),
            self::status('reject', BookingStatus::Rejected, 'Rechazar', Heroicon::OutlinedXCircle, 'danger', askReason: true),
            self::status('cancel', BookingStatus::Cancelled, 'Cancelar', Heroicon::OutlinedNoSymbol, 'danger', askReason: true),
            self::status('complete', BookingStatus::Completed, 'Marcar como completada', Heroicon::OutlinedCheckBadge, 'info', afterStartOnly: true),
            self::status('noShow', BookingStatus::NoShow, 'No asistió', Heroicon::OutlinedUserMinus, 'gray', afterStartOnly: true),
        ];
    }

    private static function status(
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
                    ->helperText('El cliente lo verá en su reserva y en el aviso.')
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

    /**
     * Reception marks the customer as arrived (e.g. after scanning the QR on their booking page).
     */
    public static function checkIn(): Action
    {
        return Action::make('checkIn')
            ->label('Registrar llegada')
            ->icon(Heroicon::OutlinedMapPin)
            ->color('success')
            ->visible(fn (Booking $record): bool => $record->status === BookingStatus::Confirmed
                && $record->checked_in_at === null
                && $record->starts_at->isToday())
            ->action(function (Booking $record): void {
                $record->update(['checked_in_at' => now()]);
                $record->events()->create(['user_id' => auth()->id(), 'from_status' => $record->status, 'to_status' => $record->status, 'note' => 'Llegada registrada.']);

                Notification::make()->success()->title("{$record->customer->name} llegó")->send();
            });
    }

    public static function reschedule(): Action
    {
        return Action::make('reschedule')
            ->label('Cambiar horario')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (Booking $record): bool => in_array($record->status, BookingStatus::holdingSlot(), true))
            ->modalHeading(fn (Booking $record): string => "Cambiar horario de {$record->code}")
            ->modalDescription('El cliente recibe un aviso con el nuevo horario.')
            ->fillForm(fn (Booking $record): array => [
                'space_id' => $record->space_id,
                'date' => $record->starts_at->toDateString(),
                'start_time' => $record->starts_at->format('H:i'),
                'end_time' => $record->ends_at->format('H:i'),
            ])
            ->schema([
                Select::make('space_id')
                    ->label('Espacio')
                    ->options(fn () => Space::where('is_active', true)->orderBy('sort')->pluck('name', 'id'))
                    ->required()
                    ->live(),
                DatePicker::make('date')->label('Fecha')->required(),
                TimePicker::make('start_time')
                    ->label('Desde')
                    ->seconds(false)
                    ->required()
                    ->visible(fn (Get $get): bool => ! (Space::find($get('space_id'))?->booking_mode->isFullDay() ?? false)),
                TimePicker::make('end_time')
                    ->label('Hasta')
                    ->seconds(false)
                    ->required()
                    ->after('start_time')
                    ->visible(fn (Get $get): bool => ! (Space::find($get('space_id'))?->booking_mode->isFullDay() ?? false)),
            ])
            ->action(function (Booking $record, array $data, Action $action): void {
                $space = Space::findOrFail($data['space_id']);
                $date = CarbonImmutable::parse($data['date'])->startOfDay();

                [$start, $end] = $space->booking_mode->isFullDay()
                    ? (app(Availability::class)->dayBounds($space, $date) ?? [$date, $date->addDay()])
                    : [$date->setTimeFromTimeString($data['start_time']), $date->setTimeFromTimeString($data['end_time'])];

                try {
                    app(RescheduleBooking::class)->handle($record, $start, $end, $space, auth()->user());
                } catch (BookingUnavailable|DomainException $e) {
                    Notification::make()->danger()->title('No se pudo cambiar')->body($e->getMessage())->send();
                    $action->halt();
                }

                $record->refresh();

                Notification::make()->success()->title('Horario actualizado y cliente avisado')->send();
            });
    }
}
