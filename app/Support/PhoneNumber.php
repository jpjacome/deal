<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Normalize a phone number to E.164, assuming Ecuador (+593) when no country code is given.
     *
     * "099 123 4567" → "+593991234567", "+57 300 1234567" → "+573001234567". Null when invalid.
     */
    public static function normalize(?string $input): ?string
    {
        $input = trim((string) $input);
        $digits = preg_replace('/\D/', '', $input);

        $number = match (true) {
            str_starts_with($input, '+'), str_starts_with($input, '00') => '+'.ltrim($digits, '0'),
            str_starts_with($digits, '593') => '+'.$digits,
            str_starts_with($digits, '0') => '+593'.substr($digits, 1),
            default => '+593'.$digits,
        };

        return preg_match('/^\+[1-9]\d{7,14}$/', $number) ? $number : null;
    }
}
