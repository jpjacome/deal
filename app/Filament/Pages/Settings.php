<?php

namespace App\Filament\Pages;

use App\Filament\Forms\PhoneField;
use App\Models\Setting;
use App\Rules\ValidPhoneNumber;
use App\Support\PhoneNumber;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
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
use Illuminate\Support\Facades\Mail;
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
                        Section::make('Correos del equipo')
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->description('Cada persona de esta lista recibe por correo: reservas nuevas, cancelaciones de clientes, solicitudes pendientes y la agenda de cada mañana. Si la lista está vacía, se avisa a los administradores del panel.')
                            ->schema([
                                Repeater::make('notify_emails')
                                    ->hiddenLabel()
                                    ->simple(
                                        TextInput::make('email')
                                            ->email()
                                            ->placeholder('reservas@deal.ec')
                                            ->validationAttribute('correo'),
                                    )
                                    ->addActionLabel('Agregar correo')
                                    ->addAction(fn (Action $action): Action => $action->icon(Heroicon::OutlinedPlus))
                                    ->reorderable(false)
                                    ->defaultItems(0),
                                Actions::make([
                                    Action::make('sendTestEmail')
                                        ->label('Guardar y enviar correo de prueba')
                                        ->icon(Heroicon::OutlinedPaperAirplane)
                                        ->color('gray')
                                        ->action(fn () => $this->sendTestEmail()),
                                ]),
                            ]),
                        Section::make('WhatsApp del equipo')
                            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                            ->description('Estos números reciben un WhatsApp con cada reserva nueva y cada cancelación de un cliente. Solo funciona cuando la API de WhatsApp de Deal esté activa.')
                            ->schema([
                                Repeater::make('notify_whatsapp')
                                    ->hiddenLabel()
                                    ->simple(
                                        TextInput::make('number')
                                            ->tel()
                                            ->placeholder(PhoneField::EXAMPLE)
                                            ->rule(new ValidPhoneNumber)
                                            ->validationAttribute('número'),
                                    )
                                    ->addActionLabel('Agregar número')
                                    ->addAction(fn (Action $action): Action => $action->icon(Heroicon::OutlinedPlus))
                                    ->reorderable(false)
                                    ->defaultItems(0)
                                    ->helperText('Con código de país; si no lo pones, se asume Ecuador (+593).'),
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
                                // Not active yet: one click to turn it on.
                                Text::make('La pantalla todavía no está activada. Al activarla se crea un enlace privado para abrir en el TV o la tablet.')
                                    ->visible(fn (): bool => ! $this->screenIsActive()),
                                Actions::make([
                                    Action::make('activateScreen')
                                        ->label('Activar pantalla')
                                        ->icon(Heroicon::OutlinedTv)
                                        ->action(fn () => $this->setScreenToken(Str::random(40), 'Pantalla activada')),
                                ])->visible(fn (): bool => ! $this->screenIsActive()),

                                // Active: the link, how to use it, and how to renew or turn it off.
                                Text::make(fn (): string => $this->screenUrl() ?? '')
                                    ->copyable()
                                    ->copyMessage('Enlace copiado')
                                    ->badge()
                                    ->visible(fn (): bool => $this->screenIsActive()),
                                Text::make('Abre este enlace en el navegador del TV o la tablet de recepción y ponlo en pantalla completa (tecla F11 en una computadora). No hace falta iniciar sesión.')
                                    ->visible(fn (): bool => $this->screenIsActive()),
                                Actions::make([
                                    Action::make('openScreen')
                                        ->label('Abrir pantalla')
                                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                                        ->url(fn (): ?string => $this->screenUrl(), shouldOpenInNewTab: true),
                                    Action::make('renewScreen')
                                        ->label('Generar enlace nuevo')
                                        ->icon(Heroicon::OutlinedArrowPath)
                                        ->color('gray')
                                        ->requiresConfirmation()
                                        ->modalHeading('¿Generar un enlace nuevo?')
                                        ->modalDescription('El enlace actual dejará de funcionar y tendrás que abrir el nuevo en el TV o la tablet. Úsalo si el enlace llegó a alguien que no debía tenerlo.')
                                        ->modalSubmitActionLabel('Generar enlace nuevo')
                                        ->action(fn () => $this->setScreenToken(Str::random(40), 'Enlace nuevo listo')),
                                    Action::make('deactivateScreen')
                                        ->label('Desactivar')
                                        ->icon(Heroicon::OutlinedNoSymbol)
                                        ->color('danger')
                                        ->link()
                                        ->requiresConfirmation()
                                        ->modalHeading('¿Desactivar la pantalla?')
                                        ->modalDescription('El enlace dejará de funcionar. Puedes volver a activarla cuando quieras.')
                                        ->modalSubmitActionLabel('Desactivar')
                                        ->action(fn () => $this->setScreenToken(null, 'Pantalla desactivada')),
                                ])->visible(fn (): bool => $this->screenIsActive()),
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

        // Clean lists: no blanks or duplicates; emails lowercased, phones in full international format.
        $values['notify_emails'] = $this->cleanList($values['notify_emails'] ?? [], fn (string $email): string => Str::lower($email));
        $values['notify_whatsapp'] = $this->cleanList($values['notify_whatsapp'] ?? [], fn (string $number): string => PhoneNumber::normalize($number) ?? $number);

        if (($values['privacy_policy'] ?? null) !== Setting::get('privacy_policy')) {
            $values['privacy_version'] = now()->format('Y-m-d H:i');
        }

        Setting::put(collect($values)->only(array_keys(Setting::DEFAULTS))->all());

        // Show the cleaned lists back in the form.
        $this->form->fill(Setting::values());

        Notification::make()->success()->title('Ajustes guardados')->send();
    }

    public function screenIsActive(): bool
    {
        return filled(Setting::get('screen_token'));
    }

    public function screenUrl(): ?string
    {
        return $this->screenIsActive() ? route('screen', Setting::get('screen_token')) : null;
    }

    private function setScreenToken(?string $token, string $message): void
    {
        Setting::put(['screen_token' => $token]);

        Notification::make()->success()->title($message)->send();
    }

    /**
     * Save, then email every team address so the owner can check the alerts arrive.
     */
    public function sendTestEmail(): void
    {
        $this->save();

        $emails = (array) Setting::get('notify_emails');

        if ($emails === []) {
            Notification::make()->warning()->title('Agrega al menos un correo para enviar la prueba')->send();

            return;
        }

        Mail::raw(
            "Hola,\n\nEste es un correo de prueba de ".config('app.name').'. Si lo recibes, los avisos de reservas te llegarán a esta dirección.',
            fn ($message) => $message->to($emails)->subject('Prueba de avisos · '.config('app.name')),
        );

        Notification::make()->success()->title('Correo de prueba enviado')->body(implode(', ', $emails))->send();
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @return list<string>
     */
    private function cleanList(array $items, Closure $normalize): array
    {
        return collect($items)
            ->map(fn ($item): string => trim((string) $item))
            ->filter()
            ->map($normalize)
            ->unique()
            ->values()
            ->all();
    }
}
