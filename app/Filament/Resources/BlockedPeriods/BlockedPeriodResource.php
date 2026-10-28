<?php

namespace App\Filament\Resources\BlockedPeriods;

use App\Filament\Resources\BlockedPeriods\Pages\ManageBlockedPeriods;
use App\Models\BlockedPeriod;
use App\Models\Booking;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Holidays (whole venue) and blocks on a single space ("mantenimiento", "evento privado").
 */
class BlockedPeriodResource extends Resource
{
    protected static ?string $model = BlockedPeriod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static string|UnitEnum|null $navigationGroup = 'Reservas';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Bloqueos y feriados';

    protected static ?string $modelLabel = 'bloqueo';

    protected static ?string $pluralModelLabel = 'bloqueos y feriados';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('space_id')
                    ->label('Espacio')
                    ->relationship('space', 'name')
                    ->placeholder('Todo el local')
                    ->helperText('Déjalo vacío para cerrar todo el local (feriados).')
                    ->columnSpanFull(),
                DateTimePicker::make('starts_at')
                    ->label('Desde')
                    ->seconds(false)
                    ->required(),
                DateTimePicker::make('ends_at')
                    ->label('Hasta')
                    ->seconds(false)
                    ->required()
                    ->after('starts_at'),
                TextInput::make('reason')
                    ->label('Motivo (interno)')
                    ->placeholder('Feriado, mantenimiento, evento privado…')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('space'))
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('space.name')
                    ->label('Espacio')
                    ->placeholder('Todo el local'),
                TextColumn::make('starts_at')
                    ->label('Desde')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Hasta')
                    ->dateTime('d/m/Y H:i'),
                TextColumn::make('reason')
                    ->label('Motivo')
                    ->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('upcoming')
                    ->label('Vigencia')
                    ->placeholder('Todos')
                    ->trueLabel('Vigentes y próximos')
                    ->falseLabel('Pasados')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->where('ends_at', '>=', now()),
                        false: fn (Builder $query) => $query->where('ends_at', '<', now()),
                    ),
            ])
            ->recordActions([
                EditAction::make()->after(fn (BlockedPeriod $record) => self::warnAboutBookings($record)),
                DeleteAction::make(),
            ]);
    }

    /**
     * Blocking doesn't cancel existing bookings; tell staff which ones to handle.
     */
    public static function warnAboutBookings(BlockedPeriod $period): void
    {
        $affected = Booking::holdingSlot()
            ->when($period->space_id, fn (Builder $query) => $query->where('space_id', $period->space_id))
            ->where('starts_at', '<', $period->ends_at)
            ->where('ends_at', '>', $period->starts_at)
            ->pluck('code');

        if ($affected->isNotEmpty()) {
            Notification::make()
                ->warning()
                ->title('Hay reservas activas en ese periodo')
                ->body('Revisa y cancela si corresponde: '.$affected->join(', '))
                ->persistent()
                ->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBlockedPeriods::route('/'),
        ];
    }
}
