<?php

namespace App\Filament\Resources\Customers\Schemas;

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
                            ->required()
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
                            ->minValue(0),
                    ]),
            ]);
    }
}
