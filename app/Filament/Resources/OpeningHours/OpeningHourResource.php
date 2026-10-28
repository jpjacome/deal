<?php

namespace App\Filament\Resources\OpeningHours;

use App\Filament\Resources\OpeningHours\Pages\ManageOpeningHours;
use App\Filament\Resources\OpeningHours\Schemas\OpeningHourSchema;
use App\Models\OpeningHour;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Venue-wide opening hours (rows without a space). Spaces can override them from their own page.
 */
class OpeningHourResource extends Resource
{
    protected static ?string $model = OpeningHour::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Reservas';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Horario general';

    protected static ?string $modelLabel = 'horario';

    protected static ?string $pluralModelLabel = 'horario general';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('space_id');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(OpeningHourSchema::fields());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Días y horas en que el local atiende. Un día puede tener varios tramos (p. ej. mañana y tarde). Los días sin horario se consideran cerrados.')
            ->defaultSort(fn (Builder $query) => $query->orderBy('weekday')->orderBy('opens_at'))
            ->columns(OpeningHourSchema::columns())
            ->paginated(false)
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOpeningHours::route('/'),
        ];
    }
}
