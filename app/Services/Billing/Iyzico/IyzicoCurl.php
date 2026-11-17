<?php

declare(strict_types=1);

namespace App\Services\Billing\Iyzico;

use Iyzipay\Curl;

/**
 * The SDK's curl wrapper sets no timeouts, so a hanging provider would block
 * a web request or worker indefinitely. TLS verification stays on (curl default).
 */
final class IyzicoCurl extends Curl
{
    public const int CONNECT_TIMEOUT = 10;

    public const int TIMEOUT = 30;

    /**
     * @param  array<int, mixed>  $options
     */
    public function exec($url, $options)
    {
        $handle = curl_init($url);
        curl_setopt_array($handle, $options + [
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        return curl_exec($handle);
    }
}
