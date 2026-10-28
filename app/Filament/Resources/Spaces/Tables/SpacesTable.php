<?php

namespace App\Filament\Resources\Spaces\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class SpacesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->description(fn ($record): ?string => $record->type_label),
                TextColumn::make('booking_mode')
                    ->label('Tipo de reserva')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('capacity')
                    ->label('Capacidad')
                    ->alignCenter(),
                TextColumn::make('price_label')
                    ->label('Precio')
                    ->placeholder('—'),
                IconColumn::make('requires_approval')
                    ->label('Requiere confirmación')
                    ->boolean(),
                ToggleColumn::make('is_bookable')
                    ->label('Reserva en línea'),
                ToggleColumn::make('is_active')
                    ->label('Visible'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
