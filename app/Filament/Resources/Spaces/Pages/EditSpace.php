<?php

namespace App\Filament\Resources\Spaces\Pages;

use App\Filament\Resources\Spaces\SpaceResource;
use App\Models\Space;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSpace extends EditRecord
{
    protected static string $resource = SpaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Spaces with booking history can't be deleted; hide them from the site instead.
            DeleteAction::make()->visible(fn (Space $record): bool => ! $record->bookings()->exists()),
        ];
    }
}
