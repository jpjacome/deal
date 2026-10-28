<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('bookings')->withMax('bookings', 'starts_at'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->description(fn (Customer $record): ?string => $record->company),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('phone')
                    ->label('Celular')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('tags')
                    ->label('Etiquetas')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                IconColumn::make('whatsapp_opt_in')
                    ->label('WhatsApp')
                    ->boolean(),
                TextColumn::make('bookings_count')
                    ->label('Reservas')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('bookings_max_starts_at')
                    ->label('Última reserva')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('no_show_count')
                    ->label('Inasistencias')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Cliente desde')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
