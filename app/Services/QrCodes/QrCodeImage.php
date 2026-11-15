<?php

declare(strict_types=1);

namespace App\Services\QrCodes;

use App\Models\QrCode;
use App\Services\Menu\MenuUrlGenerator;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as QRCodeGenerator;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Str;

/**
 * Printable images of a stable QR code (chillerlan/php-qrcode). They encode
 * only the stable URL restano.app/q/{code}, never a menu URL, so they never
 * change and are rendered on demand instead of being stored.
 */
final class QrCodeImage
{
    /** Pixels per module: a typical code (37 modules incl. quiet zone) ≈ 900 px. */
    private const int PNG_SCALE = 24;

    public function __construct(private readonly MenuUrlGenerator $urls) {}

    public function url(QrCode $qrCode): string
    {
        return $this->urls->qr((string) $qrCode->code);
    }

    public function svg(QrCode $qrCode): string
    {
        return $this->render($qrCode, [
            'outputType' => QROutputInterface::MARKUP_SVG,
            'drawLightModules' => false,
            'connectPaths' => true,
            'svgAddXmlHeader' => true,
        ]);
    }

    public function png(QrCode $qrCode): string
    {
        return $this->render($qrCode, [
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'scale' => self::PNG_SCALE,
        ]);
    }

    /**
     * SVG as a data URI for an <img> preview in the panel.
     */
    public function svgDataUri(QrCode $qrCode): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($qrCode));
    }

    public function filename(QrCode $qrCode, string $extension): string
    {
        $label = Str::slug((string) $qrCode->label);

        return 'qr-'.($label !== '' ? Str::limit($label, 60, '').'-' : '').Str::lower((string) $qrCode->code).'.'.$extension;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function render(QrCode $qrCode, array $options): string
    {
        return (new QRCodeGenerator(new QROptions([
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'outputBase64' => false,
            ...$options,
        ])))->render($this->url($qrCode));
    }
}
