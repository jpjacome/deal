<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Enums\BookingMode;
use App\Models\Space;
use App\Support\PhoneNumber;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Staff booking form (phone and walk-in bookings). Only used to create; existing bookings change through status actions.
 */
class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Reserva')
                    ->columns(2)
                    ->schema([
                        Select::make('space_id')
                            ->label('Espacio')
                            ->options(fn () => Space::where('is_active', true)->orderBy('sort')->pluck('name', 'id'))
                            ->required()
                            ->live(),
                        DatePicker::make('date')
                            ->label('Fecha')
                            ->required()
                            ->minDate(today()),
                        TimePicker::make('start_time')
                            ->label('Desde')
                            ->seconds(false)
                            ->visible(fn (Get $get): bool => self::isHourly($get('space_id')))
                            ->required(),
                        TimePicker::make('end_time')
                            ->label('Hasta')
                            ->seconds(false)
                            ->visible(fn (Get $get): bool => self::isHourly($get('space_id')))
                            ->required()
                            ->after('start_time'),
                        TextInput::make('attendees')
                            ->label('Personas')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                        Textarea::make('notes')
                            ->label('Notas')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('Cliente')
                    ->description('Si el correo ya existe, se usa ese cliente y se actualizan sus datos.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('email')
                            ->label('Correo')
                            ->email()
                            ->required(),
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('phone')
                            ->label('Celular')
                            ->tel()
                            ->placeholder('099 123 4567')
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && PhoneNumber::normalize($value) === null) {
                                    $fail('Número de celular no válido.');
                                }
                            }),
                        TextInput::make('company')
                            ->label('Empresa')
                            ->maxLength(120),
                    ]),
            ]);
    }

    public static function isHourly(mixed $spaceId): bool
    {
        return filled($spaceId) && Space::find($spaceId)?->booking_mode === BookingMode::Hourly;
    }
}
