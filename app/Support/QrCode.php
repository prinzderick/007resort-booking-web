<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** Renders the API-issued opaque QR token as SVG. The token is never interpreted here. */
final class QrCode
{
    public static function svg(string $token, int $size = 512): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd));

        return $writer->writeString($token);
    }
}
