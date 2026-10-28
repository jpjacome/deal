<?php

namespace App\Filament\Resources\Spaces\Schemas;

use App\Enums\BookingMode;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class SpaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(120)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set, string $operation): void {
                                if ($operation === 'create' && blank($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('URL')
                            ->helperText('Se usa en la dirección de la página del espacio.')
                            ->required()
                            ->alphaDash()
                            ->unique(ignoreRecord: true),
                        TextInput::make('type_label')
                            ->label('Tipo')
                            ->placeholder('Sala, Oficina, Coworking…'),
                        TextInput::make('price')
                            ->label('Precio')
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0)
                            ->helperText('Por hora, por día o por persona al día según el tipo de reserva. Se usa para el total estimado.'),
                        TextInput::make('price_label')
                            ->label('Precio (texto alternativo)')
                            ->placeholder('Consultar')
                            ->helperText('Solo si no hay precio numérico.'),
                        TextInput::make('capacity')
                            ->label('Capacidad (personas)')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                        TextInput::make('sort')
                            ->label('Orden')
                            ->numeric()
                            ->default(0),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(4)
                            ->columnSpanFull(),
                        FileUpload::make('photo')
                            ->label('Foto principal')
                            ->image()
                            ->disk('public')
                            ->directory('spaces')
                            ->imageEditor()
                            ->imageAspectRatio('16:10')
                            ->maxSize(4096),
                        TagsInput::make('amenities')
                            ->label('Equipamiento y servicios')
                            ->placeholder('Pantalla, Pizarra, Café…')
                            ->suggestions(['Wifi', 'Pantalla', 'Proyector', 'Pizarra', 'Café', 'Aire acondicionado', 'Luz natural', 'Videollamadas', 'Locker', 'Impresora']),
                        Toggle::make('is_active')
                            ->label('Visible en el sitio')
                            ->default(true),
                        Toggle::make('is_bookable')
                            ->label('Se puede reservar en línea')
                            ->default(true),
                    ]),
                Section::make('Reglas de reserva')
                    ->columns(3)
                    ->schema([
                        Select::make('booking_mode')
                            ->label('Tipo de reserva')
                            ->options(BookingMode::class)
                            ->default(BookingMode::Hourly)
                            ->required()
                            ->live()
                            ->columnSpan(2),
                        Toggle::make('requires_approval')
                            ->label('Requiere confirmación de Deal')
                            ->helperText('Si está apagado, las reservas se confirman al instante.')
                            ->default(true)
                            ->inline(false),
                        Toggle::make('allows_half_day')
                            ->label('Permite medio día (mañana / tarde)')
                            ->inline(false)
                            ->live()
                            ->visible(fn (Get $get): bool => ! self::isHourly($get('booking_mode'))),
                        TextInput::make('half_day_price')
                            ->label('Precio medio día')
                            ->numeric()
                            ->prefix('$')
                            ->visible(fn (Get $get): bool => ! self::isHourly($get('booking_mode')) && $get('allows_half_day')),
                        Select::make('slot_minutes')
                            ->label('Intervalo de horarios')
                            ->options([15 => 'Cada 15 min', 30 => 'Cada 30 min', 60 => 'Cada hora'])
                            ->default(60)
                            ->required()
                            ->visible(fn (Get $get): bool => self::isHourly($get('booking_mode'))),
                        TextInput::make('min_minutes')
                            ->label('Duración mínima (min)')
                            ->numeric()
                            ->minValue(15)
                            ->default(60)
                            ->required()
                            ->visible(fn (Get $get): bool => self::isHourly($get('booking_mode'))),
                        TextInput::make('max_minutes')
                            ->label('Duración máxima (min)')
                            ->numeric()
                            ->gte('min_minutes')
                            ->default(480)
                            ->required()
                            ->visible(fn (Get $get): bool => self::isHourly($get('booking_mode'))),
                        TextInput::make('buffer_minutes')
                            ->label('Pausa entre reservas (min)')
                            ->helperText('Tiempo para limpieza/preparación.')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        TextInput::make('lead_time_minutes')
                            ->label('Anticipación mínima (min)')
                            ->numeric()
                            ->minValue(0)
                            ->default(120)
                            ->required(),
                        TextInput::make('horizon_days')
                            ->label('Reservar hasta (días)')
                            ->numeric()
                            ->minValue(1)
                            ->default(60)
                            ->required(),
                        TextInput::make('cancellation_hours')
                            ->label('Cancelación en línea hasta (horas antes)')
                            ->numeric()
                            ->minValue(0)
                            ->default(24)
                            ->required(),
                    ]),
            ]);
    }

    private static function isHourly(mixed $mode): bool
    {
        return ($mode instanceof BookingMode ? $mode : BookingMode::tryFrom((string) $mode)) === BookingMode::Hourly;
    }
}
