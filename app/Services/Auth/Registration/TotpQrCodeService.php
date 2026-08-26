<?php

namespace App\Services\Auth\Registration;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use RuntimeException;
use XMLWriter;

class TotpQrCodeService
{
    public function dataUri(string $otpauthUri): string
    {
        if (! class_exists(XMLWriter::class)) {
            throw new RuntimeException(
                'The XMLWriter PHP extension is required for TOTP QR rendering.'
            );
        }

        $renderer = new ImageRenderer(
            new RendererStyle(280, 4),
            new SvgImageBackEnd()
        );

        $svg = (new Writer($renderer))
            ->writeString($otpauthUri);

        return 'data:image/svg+xml;base64,'
            .base64_encode($svg);
    }
}
