<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\Actions\BookingStatusActions;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * @property Booking $record
 */
class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    public function getTitle(): string
    {
        return "Reserva {$this->record->code}";
    }

    protected function getHeaderActions(): array
    {
        return [
            ...BookingStatusActions::make(),
            ActionGroup::make([
                Action::make('whatsapp')
                    ->label('Escribir por WhatsApp')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->visible(fn (): bool => filled($this->record->customer->phone))
                    ->url(fn (): string => 'https://wa.me/'.ltrim((string) $this->record->customer->phone, '+'), shouldOpenInNewTab: true),
                Action::make('customerPage')
                    ->label('Ver página del cliente')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (): string => route('bookings.show', $this->record->manage_token), shouldOpenInNewTab: true),
            ]),
        ];
    }
}
