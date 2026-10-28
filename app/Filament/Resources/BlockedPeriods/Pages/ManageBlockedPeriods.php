<?php

namespace App\Filament\Resources\BlockedPeriods\Pages;

use App\Filament\Resources\BlockedPeriods\BlockedPeriodResource;
use App\Models\BlockedPeriod;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBlockedPeriods extends ManageRecords
{
    protected static string $resource = BlockedPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo bloqueo')
                ->after(fn (BlockedPeriod $record) => BlockedPeriodResource::warnAboutBookings($record)),
        ];
    }
}
