<?php

declare(strict_types=1);

namespace App\Services\QrCodes;

use App\Enums\QrBackground;
use App\Models\QrCode;
use App\Services\Menu\MenuUrlGenerator;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as QRCodeGenerator;
use chillerlan\QRCode\QROptions;
use GdImage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Printable images of a stable QR code (chillerlan/php-qrcode). They encode
 * only the stable URL restano.app/q/{code}, never a menu URL, so they never
 * change and are rendered on demand instead of being stored.
 */
final class QrCodeImage
{
    /** Pixels per module: a typical code (37 modules incl. quiet zone) ≈ 900 px. */
    private const int PNG_SCALE = 24;

    /** Share of the background's white box the code (incl. quiet zone) fills; the rest keeps it off the rounded corners. */
    private const float FRAME_FILL = 0.96;

    private const int PREVIEW_WIDTH = 360;

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
     * The code drawn into the white box of a printable background (PNG at the
     * background's own resolution).
     */
    public function pngOnBackground(QrCode $qrCode, QrBackground $background): string
    {
        return $this->encode($this->composeOnBackground($qrCode, $background), 'png');
    }

    /**
     * Small JPEG data URI of the same composition for the download modal.
     */
    public function backgroundPreviewDataUri(QrCode $qrCode, QrBackground $background): string
    {
        $preview = imagescale($this->composeOnBackground($qrCode, $background), self::PREVIEW_WIDTH);

        if (! $preview instanceof GdImage) {
            throw new RuntimeException('The QR background preview could not be scaled.');
        }

        return 'data:image/jpeg;base64,'.base64_encode($this->encode($preview, 'jpeg'));
    }

    /**
     * SVG as a data URI for an <img> preview in the panel.
     */
    public function svgDataUri(QrCode $qrCode): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($qrCode));
    }

    public function filename(QrCode $qrCode, string $extension, ?QrBackground $background = null): string
    {
        $label = Str::slug((string) $qrCode->label);

        return 'qr-'.($label !== '' ? Str::limit($label, 60, '').'-' : '').Str::lower((string) $qrCode->code)
            .($background !== null ? '-'.$background->value : '').'.'.$extension;
    }

    private function composeOnBackground(QrCode $qrCode, QrBackground $background): GdImage
    {
        $canvas = imagecreatefrompng($background->path());
        $code = $this->render($qrCode, [
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'scale' => self::PNG_SCALE,
            'returnResource' => true,
            // Light modules and quiet zone show the box's own (off-)white.
            'drawLightModules' => false,
            'imageTransparent' => true,
        ]);

        if (! $canvas instanceof GdImage || ! $code instanceof GdImage) {
            throw new RuntimeException("The QR background [{$background->value}] could not be rendered.");
        }

        // A whole number of pixels per module (nearest neighbour), so module
        // edges stay sharp, centred in the box.
        [$x, $y, $width, $height] = $background->frame();
        $modules = intdiv(imagesx($code), self::PNG_SCALE);
        $size = intdiv((int) floor(min($width, $height) * self::FRAME_FILL), $modules) * $modules;

        imagecopyresized(
            $canvas,
            $code,
            $x + intdiv($width - $size, 2),
            $y + intdiv($height - $size, 2),
            0,
            0,
            $size,
            $size,
            imagesx($code),
            imagesy($code),
        );

        return $canvas;
    }

    private function encode(GdImage $image, string $type): string
    {
        ob_start();
        $type === 'png' ? imagepng($image) : imagejpeg($image, null, 85);

        return (string) ob_get_clean();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function render(QrCode $qrCode, array $options): mixed
    {
        return (new QRCodeGenerator(new QROptions([
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'outputBase64' => false,
            ...$options,
        ])))->render($this->url($qrCode));
    }
}
