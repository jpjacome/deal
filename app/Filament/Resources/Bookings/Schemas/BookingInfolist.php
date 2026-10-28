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
            ]);
    }
}
