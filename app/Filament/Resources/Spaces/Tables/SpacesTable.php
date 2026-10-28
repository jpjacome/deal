<?php

namespace App\Filament\Resources\Spaces\Tables;

use App\Models\Space;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
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
                TextColumn::make('price')
                    ->label('Precio')
                    ->money('USD')
                    ->description(fn (Space $record): string => $record->priceUnitLabel())
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
                Action::make('doorSign')
                    ->label('Letrero QR')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->color('gray')
                    ->tooltip('Para imprimir y pegar en la puerta: escanean y reservan este espacio.')
                    ->visible(fn (Space $record): bool => $record->is_active && $record->is_bookable)
                    ->url(fn (Space $record): string => route('spaces.door-sign', $record), shouldOpenInNewTab: true),
            ]);
    }
}
