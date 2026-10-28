<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Bookings\Actions\BookingStatusActions;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Tables\BookingsTable;
use App\Models\Booking;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pending and confirmed bookings for the next 7 days, soonest first.
 */
class UpcomingBookings extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Próximos 7 días';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Booking::query()
                ->with(['space', 'customer'])
                ->holdingSlot()
                ->where('ends_at', '>=', now())
                ->where('starts_at', '<', today()->addDays(7))
                ->orderBy('starts_at'))
            ->columns([
                TextColumn::make('starts_at')
                    ->label('Fecha')
                    ->formatStateUsing(fn (Booking $record): string => ucfirst(BookingsTable::when($record))),
                TextColumn::make('space.name')->label('Espacio'),
                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->description(fn (Booking $record): ?string => $record->customer->phone),
                TextColumn::make('attendees')->label('Personas')->alignCenter(),
                TextColumn::make('status')->label('Estado')->badge(),
            ])
            ->recordActions([
                ActionGroup::make(BookingStatusActions::make()),
            ])
            ->recordUrl(fn (Booking $record): string => BookingResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('No hay reservas en los próximos 7 días')
            ->paginated([10, 25]);
    }
}
