<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Filament\Resources\Bookings\Tables\BookingsTable;
use App\Models\Booking;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BookingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Reserva')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')->label('Estado')->badge(),
                        TextEntry::make('space.name')->label('Espacio'),
                        TextEntry::make('starts_at')
                            ->label('Fecha y horario')
                            ->formatStateUsing(fn (Booking $record): string => ucfirst(BookingsTable::when($record))),
                        TextEntry::make('attendees')->label('Personas'),
                        TextEntry::make('source')->label('Origen')->badge()->color('gray'),
                        TextEntry::make('created_at')->label('Creada')->dateTime('d/m/Y H:i'),
                        TextEntry::make('checked_in_at')->label('Llegada')->dateTime('H:i')->placeholder('Sin registrar'),
                        TextEntry::make('attendance_confirmed_at')
                            ->label('Confirmó asistencia')
                            ->dateTime('d/m H:i')
                            ->placeholder('—')
                            ->helperText('Con el botón del recordatorio de WhatsApp.'),
                        TextEntry::make('series_id')
                            ->label('Serie semanal')
                            ->formatStateUsing(fn (Booking $record): string => Booking::where('series_id', $record->series_id)->count().' reservas en la serie')
                            ->visible(fn (Booking $record): bool => $record->series_id !== null),
                        TextEntry::make('notes')->label('Notas del cliente')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('status_reason')->label('Motivo del estado')->placeholder('—')->columnSpanFull(),
                    ]),
                Section::make('Cliente')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('customer.name')->label('Nombre'),
                        TextEntry::make('customer.email')->label('Correo')->copyable(),
                        TextEntry::make('customer.phone')->label('Celular')->copyable()->placeholder('—'),
                        TextEntry::make('customer.company')->label('Empresa')->placeholder('—'),
                        TextEntry::make('customer.whatsapp_opt_in')
                            ->label('Avisos por WhatsApp')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Sí' : 'No'),
                        TextEntry::make('customer.no_show_count')
                            ->label('Inasistencias')
                            ->badge()
                            ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                    ]),
                Section::make('Historial')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('events')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i'),
                                TextEntry::make('to_status')->label('Estado')->badge(),
                                TextEntry::make('user.name')->label('Por')->placeholder('Cliente / sistema'),
                                TextEntry::make('note')->label('Nota')->placeholder('—'),
                            ]),
                    ]),
                Section::make('Mensajes enviados')
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        RepeatableEntry::make('notificationLogs')
                            ->hiddenLabel()
                            ->columns(5)
                            ->placeholder('Todavía no se ha enviado ningún mensaje.')
                            ->schema([
                                TextEntry::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i'),
                                TextEntry::make('channel')->label('Canal')->formatStateUsing(fn (string $state): string => $state === 'mail' ? 'Correo' : 'WhatsApp'),
                                TextEntry::make('template')->label('Mensaje'),
                                TextEntry::make('recipient')->label('Para'),
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn (string $state): string => $state === 'failed' ? 'danger' : 'success')
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'delivered' => 'Entregado',
                                        'read' => 'Leído',
                                        'failed' => 'Falló',
                                        default => 'Enviado',
                                    })
                                    ->tooltip(fn ($record): ?string => $record->error),
                            ]),
                    ]),
            ]);
    }
}
