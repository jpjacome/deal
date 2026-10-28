<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Actions\Bookings\CreateRecurringBookings;
use App\Enums\BookingMode;
use App\Filament\Forms\PhoneField;
use App\Models\Space;
use App\Services\Availability;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Throwable;

/**
 * Staff booking form (phone and walk-in bookings). Only used to create; existing bookings change
 * through the status and reschedule actions.
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
                            ->default(fn () => request()->integer('space') ?: null)
                            ->required()
                            ->live(),
                        DatePicker::make('date')
                            ->label('Fecha')
                            ->default(fn () => request()->query('date'))
                            ->required()
                            ->minDate(today())
                            ->live(),
                        Select::make('period')
                            ->label('Duración')
                            ->options(['full' => 'Día completo', 'am' => 'Medio día (mañana)', 'pm' => 'Medio día (tarde)'])
                            ->default('full')
                            ->visible(fn (Get $get): bool => self::space($get('space_id'))?->allows_half_day ?? false)
                            ->required(),
                        TimePicker::make('start_time')
                            ->label('Desde')
                            ->seconds(false)
                            ->default(fn () => request()->query('start'))
                            ->visible(fn (Get $get): bool => self::isHourly($get('space_id')))
                            ->required(),
                        TimePicker::make('end_time')
                            ->label('Hasta')
                            ->seconds(false)
                            ->default(fn () => ($start = request()->query('start')) ? CarbonImmutable::parse($start)->addHour()->format('H:i') : null)
                            ->visible(fn (Get $get): bool => self::isHourly($get('space_id')))
                            ->required()
                            ->after('start_time'),
                        Text::make(fn (Get $get): string => self::availabilityHint($get('space_id'), $get('date')))
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => filled($get('space_id')) && filled($get('date'))),
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
                        Toggle::make('repeat')
                            ->label('Repetir cada semana')
                            ->live(),
                        DatePicker::make('repeat_until')
                            ->label('Hasta el')
                            ->visible(fn (Get $get): bool => (bool) $get('repeat'))
                            ->required(fn (Get $get): bool => (bool) $get('repeat'))
                            ->afterOrEqual('date')
                            ->maxDate(today()->addWeeks(CreateRecurringBookings::MAX_WEEKS))
                            ->helperText('Las fechas sin disponibilidad se omiten y se te avisa cuáles.'),
                    ]),
                Section::make('Cliente')
                    ->description('Si el correo o el celular ya existen, se usa ese cliente y se actualizan sus datos.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('email')
                            ->label('Correo')
                            ->helperText('Opcional. Sin correo, el cliente no recibe la confirmación.')
                            ->email(),
                        PhoneField::make('phone')
                            ->label('Celular'),
                        TextInput::make('company')
                            ->label('Empresa')
                            ->maxLength(120),
                        Toggle::make('notify')
                            ->label('Enviar confirmación al cliente')
                            ->default(true),
                    ]),
            ]);
    }

    public static function isHourly(mixed $spaceId): bool
    {
        return self::space($spaceId)?->booking_mode === BookingMode::Hourly;
    }

    private static function space(mixed $spaceId): ?Space
    {
        return filled($spaceId) ? Space::find($spaceId) : null;
    }

    /**
     * Free times for the chosen space and day, so staff don't have to guess.
     */
    private static function availabilityHint(mixed $spaceId, mixed $date): string
    {
        $space = self::space($spaceId);

        try {
            $day = CarbonImmutable::parse((string) $date)->startOfDay();
        } catch (Throwable) {
            return '';
        }

        if ($space === null) {
            return '';
        }

        $availability = app(Availability::class);

        if ($availability->openingRanges($space, $day) === []) {
            return 'Ese día el local está cerrado.';
        }

        if ($space->booking_mode->isFullDay()) {
            return $availability->isDayAvailable($space, $day) ? 'Disponible todo el día.' : 'Sin disponibilidad para el día completo.';
        }

        $free = collect($availability->slots($space, $day, $space->slot_minutes, strict: false))
            ->where('available', true)
            ->map(fn (array $slot) => $slot['start']->format('H:i'));

        return $free->isEmpty() ? 'No quedan horarios libres ese día.' : 'Horarios libres: '.$free->join(', ');
    }
}
