<?php

declare(strict_types=1);

namespace App\Services\Account;

/**
 * A branch's default QR code on /account: the stable /q/{code} URL, an inline
 * SVG preview and the download links (AccountQrCodeController).
 */
final readonly class AccountQrCode
{
    public function __construct(
        public string $code,
        public ?string $label,
        public string $scanUrl,
        public string $previewDataUri,
        public string $svgDownloadUrl,
        public string $pngDownloadUrl,
    ) {}
}
