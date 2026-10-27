<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BookingSource: string implements HasLabel
{
    case Web = 'web';
    case Admin = 'admin';
    case Api = 'api';

    public function getLabel(): string
    {
        return match ($this) {
            self::Web => 'Sitio web',
            self::Admin => 'Panel',
            self::Api => 'App',
        };
    }
}
