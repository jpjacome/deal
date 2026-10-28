<?php

namespace App\Support;

use chillerlan\QRCode\QRCode as Generator;
use chillerlan\QRCode\QROptions;

class QrCode
{
    /**
     * Inline SVG markup for a QR code.
     */
    public static function svg(string $data): string
    {
        $options = new QROptions([
            'outputType' => 'svg',
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
        ]);

        return (new Generator($options))->render($data);
    }
}
