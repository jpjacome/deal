<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Setting;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('email')
                            ->label('Correo')
                            ->email()
                            ->unique(ignoreRecord: true),
                        TextInput::make('phone')
                            ->label('Celular')
                            ->tel()
                            ->helperText('Formato internacional, p. ej. +593991234567'),
                        TextInput::make('company')
                            ->label('Empresa'),
                        Toggle::make('whatsapp_opt_in')
                            ->label('Acepta avisos por WhatsApp')
                            ->disabled()
                            ->helperText('Solo el cliente puede dar este consentimiento al reservar.'),
                        TextInput::make('no_show_count')
                            ->label('Inasistencias')
                            ->numeric()
                            ->minValue(0)
                            ->helperText(fn (): string => 'Con '.Setting::get('no_show_threshold').' o más, sus reservas en línea quedan pendientes.'),
                        TagsInput::make('tags')
                            ->label('Etiquetas')
                            ->placeholder('VIP, empresa, frecuente…')
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Notas internas')
                            ->helperText('Solo las ve el equipo.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
