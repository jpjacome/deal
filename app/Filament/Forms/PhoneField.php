<?php

namespace App\Filament\Forms;

use App\Rules\ValidPhoneNumber;
use App\Support\PhoneNumber;
use Filament\Forms\Components\TextInput;

/**
 * Phone input for the admin: shows and stores the full international number (+593991234567).
 * Numbers typed without a country code are taken as Ecuadorian.
 */
class PhoneField
{
    public const EXAMPLE = '+593 99 123 4567';

    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->tel()
            ->placeholder(self::EXAMPLE)
            ->helperText('Con código de país. Si no lo pones, se asume Ecuador (+593).')
            ->rule(new ValidPhoneNumber)
            ->dehydrateStateUsing(fn (?string $state): ?string => PhoneNumber::normalize($state) ?? $state);
    }
}
