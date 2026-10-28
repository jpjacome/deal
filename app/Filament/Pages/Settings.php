<?php

namespace App\Filament\Pages;

use App\Filament\Forms\PhoneField;
use App\Models\Setting;
use App\Rules\ValidPhoneNumber;
use App\Support\PhoneNumber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Venue details, team alerts, automatic rules, follow-ups and legal texts.
 *
 * @property-read Schema $form
 */
class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Ajustes';

    protected static ?string $title = 'Ajustes';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(Setting::values());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make()->tabs([
                    Tab::make('Contacto')->icon(Heroicon::OutlinedMapPin)->schema([
                        Section::make('Ubicación y contacto')
                            ->description('Aparece en los correos, en "Mi reserva" y en el sitio.')
                            ->columns(2)
                            ->schema([
                                TextInput::make('venue_address')->label('Dirección')->required()->columnSpanFull(),
                                TextInput::make('maps_url')->label('Enlace de Google Maps')->url(),
                                TextInput::make('contact_email')->label('Correo de contacto')->email(),
                                PhoneField::make('whatsapp_number')
                                    ->label('WhatsApp de Deal')
                                    ->helperText('Con código de país. Se usa en los botones "Escríbenos por WhatsApp".'),
                                Textarea::make('arrival_info')
                                    ->label('Información de llegada')
                                    ->placeholder("Wifi: Deal-Guest / clave…\nParqueadero en la calle lateral.\nAnúnciate en recepción, planta baja.")
                                    ->helperText('Se envía con la confirmación y el recordatorio.')
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ]),
                    ]),
                    Tab::make('Avisos')->icon(Heroicon::OutlinedBellAlert)->schema([
                        Section::make('Avisos al equipo')
                            ->description('Quién recibe las reservas nuevas, cancelaciones, pendientes y la agenda diaria. Si no hay correos, se avisa a los administradores.')
                            ->schema([
                                TagsInput::make('notify_emails')->label('Correos')->placeholder('reservas@deal.ec')->nestedRecursiveRules(['email']),
                                TagsInput::make('notify_whatsapp')
                                    ->label('Números de WhatsApp')
                                    ->placeholder(PhoneField::EXAMPLE)
                                    ->helperText('Con código de país (sin él se asume Ecuador). Requiere la API de WhatsApp activa.')
                                    ->nestedRecursiveRules([new ValidPhoneNumber])
                                    ->dehydrateStateUsing(fn (?array $state): array => array_values(array_map(
                                        fn (string $number): string => PhoneNumber::normalize($number) ?? $number,
                                        $state ?? [],
                                    ))),
                            ]),
                    ]),
                    Tab::make('Reglas')->icon(Heroicon::OutlinedAdjustmentsHorizontal)->schema([
                        Section::make('Automatizaciones')
                            ->description('Usa 0 para desactivar una regla.')
                            ->columns(2)
                            ->schema([
                                TextInput::make('pending_expiry_hours')->label('Las solicitudes pendientes expiran a las (horas)')->numeric()->minValue(0)->required(),
                                TextInput::make('pending_reminder_hours')->label('Recordar al equipo pendientes después de (horas)')->numeric()->minValue(0)->required(),
                                TextInput::make('reminder_hours_before')->label('Recordatorio al cliente (horas antes)')->numeric()->minValue(0)->required(),
                                TextInput::make('no_show_threshold')
                                    ->label('Inasistencias para exigir confirmación')
                                    ->helperText('Con este número de inasistencias, sus reservas en línea quedan pendientes.')
                                    ->numeric()->minValue(0)->required(),
                                TextInput::make('checkin_grace_minutes')
                                    ->label('Liberar si no registra llegada en (minutos)')
                                    ->helperText('Solo si usan el registro de llegada con QR. 0 = desactivado.')
                                    ->numeric()->minValue(0)->required(),
                            ]),
                    ]),
                    Tab::make('Seguimiento')->icon(Heroicon::OutlinedChatBubbleBottomCenterText)->schema([
                        Section::make('Después de la visita')
                            ->schema([
                                TextInput::make('google_review_url')
                                    ->label('Enlace para reseñas de Google')
                                    ->url()
                                    ->helperText('Si está vacío no se envía el correo de reseña.'),
                                TextInput::make('membership_nudge_after')
                                    ->label('Sugerir plan mensual después de (visitas)')
                                    ->numeric()->minValue(0)->required(),
                                Textarea::make('membership_nudge_text')
                                    ->label('Mensaje de plan mensual')
                                    ->placeholder('Con el plan mensual tienes tu lugar asegurado todos los días por $X…')
                                    ->rows(3),
                            ]),
                    ]),
                    Tab::make('Pantalla')->icon(Heroicon::OutlinedTv)->schema([
                        Section::make('Pantalla de recepción')
                            ->description('Una página para un TV o tablet en recepción: la agenda de hoy por espacio y cuáles están libres ahora. Se actualiza sola cada minuto. El enlace es privado.')
                            ->schema([
                                Text::make(fn (): string => filled(Setting::get('screen_token'))
                                    ? route('screen', Setting::get('screen_token'))
                                    : 'Todavía no está activada.')
                                    ->copyable(fn (): bool => filled(Setting::get('screen_token'))),
                                Actions::make([
                                    Action::make('screenToken')
                                        ->label(fn (): string => filled(Setting::get('screen_token')) ? 'Generar un enlace nuevo' : 'Activar pantalla')
                                        ->requiresConfirmation(fn (): bool => filled(Setting::get('screen_token')))
                                        ->modalDescription('El enlace anterior dejará de funcionar.')
                                        ->action(function (): void {
                                            Setting::put(['screen_token' => Str::random(40)]);
                                            Notification::make()->success()->title('Enlace de pantalla listo')->send();
                                        }),
                                ]),
                            ]),
                    ]),
                    Tab::make('Legal')->icon(Heroicon::OutlinedShieldCheck)->schema([
                        Section::make('Política de privacidad')
                            ->description(fn (): string => 'Versión vigente: '.Setting::get('privacy_version').'. Al cambiar el texto se crea una nueva versión, que queda registrada con cada consentimiento.')
                            ->schema([
                                MarkdownEditor::make('privacy_policy')->hiddenLabel()->placeholder('Si está vacío se muestra un texto base para revisar con su abogado.'),
                            ]),
                        Section::make('Condiciones de reserva')
                            ->schema([
                                MarkdownEditor::make('booking_terms')->hiddenLabel(),
                            ]),
                    ]),
                ])->persistTabInQueryString(),
            ]);
    }

    public function save(): void
    {
        $values = $this->form->getState();

        if (($values['privacy_policy'] ?? null) !== Setting::get('privacy_policy')) {
            $values['privacy_version'] = now()->format('Y-m-d H:i');
        }

        Setting::put(collect($values)->only(array_keys(Setting::DEFAULTS))->all());

        Notification::make()->success()->title('Ajustes guardados')->send();
    }
}
