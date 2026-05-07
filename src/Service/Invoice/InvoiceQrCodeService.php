<?php

namespace App\Service\Invoice;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final class InvoiceQrCodeService
{
    public function generateQrCodeSvg(string $data): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(240, 10),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        return $writer->writeString($data);
    }

    public function generateQrCodeDataUri(string $data): string
    {
        $svg = $this->generateQrCodeSvg($data);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}