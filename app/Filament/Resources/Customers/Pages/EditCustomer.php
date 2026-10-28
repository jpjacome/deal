<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * @property Customer $record
 */
class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Data-protection requests (LOPDP): remove personal data but keep booking history for the records.
            Action::make('anonymize')
                ->label('Anonimizar')
                ->icon(Heroicon::OutlinedEyeSlash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Anonimizar cliente')
                ->modalDescription('Se borran el nombre, correo, celular y empresa de forma permanente. Las reservas se conservan sin datos personales.')
                ->action(function (): void {
                    $this->record->forceFill([
                        'name' => 'Cliente anonimizado',
                        'email' => 'anonimo-'.Str::lower(Str::random(12)).'@invalid.local',
                        'phone' => null,
                        'company' => null,
                        'whatsapp_opt_in' => false,
                    ])->save();

                    $this->refreshFormData(['name', 'email', 'phone', 'company', 'whatsapp_opt_in']);

                    Notification::make()->success()->title('Cliente anonimizado')->send();
                }),
        ];
    }
}
