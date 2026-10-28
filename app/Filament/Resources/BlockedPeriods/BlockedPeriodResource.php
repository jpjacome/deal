<?php

namespace App\Filament\Resources\BlockedPeriods;

use App\Actions\Bookings\ChangeBookingStatus;
use App\Enums\BookingStatus;
use App\Filament\Resources\BlockedPeriods\Pages\ManageBlockedPeriods;
use App\Models\BlockedPeriod;
use App\Models\Booking;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
                self::cancelAffectedAction(),
                EditAction::make()->after(fn (BlockedPeriod $record) => self::warnAboutBookings($record)),
                DeleteAction::make(),
            ]);
    }

    /**
     * Blocking doesn't cancel existing bookings; tell staff which ones to handle.
     */
    public static function warnAboutBookings(BlockedPeriod $period): void
    {
        $affected = self::affectedBookings($period)->pluck('code');

        if ($affected->isNotEmpty()) {
            Notification::make()
                ->warning()
                ->title('Hay reservas activas en ese periodo')
                ->body('Reservas: '.$affected->join(', ').'. Puedes cancelarlas y avisar a los clientes con "Cancelar reservas afectadas".')
                ->persistent()
                ->send();
        }
    }

    /**
     * @return EloquentCollection<int, Booking>
     */
    public static function affectedBookings(BlockedPeriod $period): EloquentCollection
    {
        return Booking::holdingSlot()
            ->when($period->space_id, fn (Builder $query) => $query->where('space_id', $period->space_id))
            ->where('starts_at', '<', $period->ends_at)
            ->where('ends_at', '>', $period->starts_at)
            ->get();
    }

    public static function cancelAffectedAction(): Action
    {
        return Action::make('cancelAffected')
            ->label('Cancelar reservas afectadas')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (BlockedPeriod $record): bool => self::affectedBookings($record)->isNotEmpty())
            ->requiresConfirmation()
            ->modalHeading(fn (BlockedPeriod $record): string => 'Cancelar '.self::affectedBookings($record)->count().' reserva(s)')
            ->schema([
                Textarea::make('reason')
                    ->label('Motivo para los clientes')
                    ->default(fn (BlockedPeriod $record): string => 'El espacio no estará disponible'.($record->reason ? " ({$record->reason})" : '').'. Disculpa las molestias.')
                    ->required(),
            ])
            ->action(function (BlockedPeriod $record, array $data): void {
                $count = 0;

                foreach (self::affectedBookings($record) as $booking) {
                    try {
                        app(ChangeBookingStatus::class)->handle($booking, BookingStatus::Cancelled, auth()->user(), $data['reason']);
                        $count++;
                    } catch (DomainException) {
                        // Changed meanwhile.
                    }
                }

                Notification::make()->success()->title("{$count} reserva(s) cancelada(s) y clientes avisados")->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBlockedPeriods::route('/'),
        ];
    }
}
