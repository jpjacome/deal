<?php

namespace App\Filament\Resources\OpeningHours\Schemas;

use App\Models\OpeningHour;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Tables\Columns\TextColumn;

/**
 * Form fields and table columns shared by venue-wide hours and per-space hours.
 */
class OpeningHourSchema
{
    /**
     * @return array<int, mixed>
     */
    public static function fields(): array
    {
        return [
            Select::make('weekday')
                ->label('Día')
                ->options(OpeningHour::WEEKDAYS)
                ->required(),
            TimePicker::make('opens_at')
                ->label('Abre')
                ->seconds(false)
                ->required(),
            TimePicker::make('closes_at')
                ->label('Cierra')
                ->seconds(false)
                ->required()
                ->after('opens_at'),
        ];
    }

    /**
     * @return array<int, TextColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('weekday')
                ->label('Día')
                ->formatStateUsing(fn (int $state): string => OpeningHour::WEEKDAYS[$state]),
            TextColumn::make('opens_at')
                ->label('Abre')
                ->time('H:i'),
            TextColumn::make('closes_at')
                ->label('Cierra')
                ->time('H:i'),
        ];
    }
}
