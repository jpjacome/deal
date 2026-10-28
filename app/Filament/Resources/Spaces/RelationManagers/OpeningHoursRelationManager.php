<?php

namespace App\Filament\Resources\Spaces\RelationManagers;

use App\Filament\Resources\OpeningHours\Schemas\OpeningHourSchema;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class OpeningHoursRelationManager extends RelationManager
{
    protected static string $relationship = 'openingHours';

    protected static ?string $title = 'Horario propio';

    protected static ?string $modelLabel = 'horario';

    protected static ?string $pluralModelLabel = 'horarios';

    public function form(Schema $schema): Schema
    {
        return $schema->components(OpeningHourSchema::fields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Si este espacio no tiene horario propio, usa el horario general del local.')
            ->defaultSort(fn ($query) => $query->orderBy('weekday')->orderBy('opens_at'))
            ->columns(OpeningHourSchema::columns())
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
