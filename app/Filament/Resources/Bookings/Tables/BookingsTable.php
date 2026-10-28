<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\Actions\BookingStatusActions;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['space', 'customer']))
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('starts_at')
                    ->label('Fecha')
                    ->sortable()
                    ->formatStateUsing(fn (Booking $record): string => self::when($record))
                    ->description(fn (Booking $record): string => $record->starts_at->diffForHumans()),
                TextColumn::make('space.name')
                    ->label('Espacio'),
                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->description(fn (Booking $record): ?string => $record->customer->phone),
                TextColumn::make('customer.email')
                    ->label('Correo')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('attendees')
                    ->label('Personas')
                    ->alignCenter(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('source')
                    ->label('Origen')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creada')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(BookingStatus::class)
                    ->multiple(),
                SelectFilter::make('space')
                    ->label('Espacio')
                    ->relationship('space', 'name'),
                SelectFilter::make('source')
                    ->label('Origen')
                    ->options(BookingSource::class),
                Filter::make('dates')
                    ->schema([
                        DatePicker::make('from')->label('Desde'),
                        DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('starts_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('starts_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
                ActionGroup::make(BookingStatusActions::make()),
            ])
            ->recordUrl(fn (Booking $record): string => BookingResource::getUrl('view', ['record' => $record]));
    }

    /**
     * "mié 4 nov · 10:00–12:00"
     */
    public static function when(Booking $booking): string
    {
        return $booking->starts_at->translatedFormat('D j M').' · '.$booking->starts_at->format('H:i').'–'.$booking->ends_at->format('H:i');
    }
}
