<?php

namespace App\Filament\Resources\WaitlistEntries;

use App\Filament\Resources\WaitlistEntries\Pages\ManageWaitlistEntries;
use App\Models\WaitlistEntry;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * People waiting for a full day to free up. Entries are created from the booking page and
 * notified automatically when a booking for that space and day is cancelled.
 */
class WaitlistEntryResource extends Resource
{
    protected static ?string $model = WaitlistEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Reservas';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Lista de espera';

    protected static ?string $modelLabel = 'persona en espera';

    protected static ?string $pluralModelLabel = 'lista de espera';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('space'))
            ->defaultSort('date')
            ->columns([
                TextColumn::make('date')->label('Día')->date('D d/m/Y')->sortable(),
                TextColumn::make('space.name')->label('Espacio'),
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('email')->label('Correo')->searchable()->copyable(),
                TextColumn::make('phone')->label('Celular')->placeholder('—'),
                TextColumn::make('notified_at')->label('Avisado')->since()->placeholder('Esperando'),
                TextColumn::make('created_at')->label('Se anotó')->since()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('upcoming')
                    ->label('Fechas')
                    ->placeholder('Todas')
                    ->trueLabel('Próximas')
                    ->falseLabel('Pasadas')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->whereDate('date', '>=', today()),
                        false: fn (Builder $query) => $query->whereDate('date', '<', today()),
                    ),
            ])
            ->recordActions([
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWaitlistEntries::route('/'),
        ];
    }
}
