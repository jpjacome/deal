<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Tables\BookingsTable;
use App\Models\Booking;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    protected static ?string $title = 'Reservas';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('space'))
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('code')->label('Código')->weight('bold'),
                TextColumn::make('starts_at')
                    ->label('Fecha')
                    ->formatStateUsing(fn (Booking $record): string => BookingsTable::when($record)),
                TextColumn::make('space.name')->label('Espacio'),
                TextColumn::make('status')->label('Estado')->badge(),
            ])
            ->recordUrl(fn (Booking $record): string => BookingResource::getUrl('view', ['record' => $record]));
    }
}
