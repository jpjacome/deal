<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Country calling codes offered in forms (Ecuador first; Cuenca has many visitors from these countries).
     *
     * @var array<string, string>
     */
    public const COUNTRIES = [
        '593' => '🇪🇨 +593',
        '1' => '🇺🇸 +1',
        '57' => '🇨🇴 +57',
        '51' => '🇵🇪 +51',
        '52' => '🇲🇽 +52',
        '54' => '🇦🇷 +54',
        '56' => '🇨🇱 +56',
        '55' => '🇧🇷 +55',
        '58' => '🇻🇪 +58',
        '34' => '🇪🇸 +34',
        '44' => '🇬🇧 +44',
        '49' => '🇩🇪 +49',
        '33' => '🇫🇷 +33',
        '39' => '🇮🇹 +39',
    ];

    /**
     * Normalize a phone number to E.164. A leading "+" (or "00") always wins; otherwise the
     * country code is added and a national trunk "0" dropped.
     *
     * ("099 123 4567", "593") → "+593991234567", ("(305) 555-0134", "1") → "+13055550134". Null when invalid.
     */
    public static function normalize(?string $input, string $countryCode = '593'): ?string
    {
        $input = trim((string) $input);
        $digits = preg_replace('/\D/', '', $input);

        if ($digits === '') {
            return null;
        }

        $number = match (true) {
            str_starts_with($input, '+'), str_starts_with($input, '00') => '+'.ltrim($digits, '0'),
            str_starts_with($digits, $countryCode) && strlen($digits) > 10 => '+'.$digits,
            default => '+'.$countryCode.ltrim($digits, '0'),
        };

        return preg_match('/^\+[1-9]\d{7,14}$/', $number) ? $number : null;
    }
}
