<?php

namespace App\Filament\Resources\WaitlistEntries\Pages;

use App\Filament\Resources\WaitlistEntries\WaitlistEntryResource;
use Filament\Resources\Pages\ManageRecords;

class ManageWaitlistEntries extends ManageRecords
{
    protected static string $resource = WaitlistEntryResource::class;
}
