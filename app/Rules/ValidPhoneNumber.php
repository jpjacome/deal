<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A phone number that can be turned into a full international number (Ecuador assumed without country code).
 */
class ValidPhoneNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (filled($value) && PhoneNumber::normalize((string) $value) === null) {
            $fail('Número no válido. Escríbelo con código de país, p. ej. +593 99 123 4567.');
        }
    }
}
