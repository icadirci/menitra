<?php

declare(strict_types=1);

namespace App\Services\Billing\Iyzico;

/**
 * iyzico signature checks (docs.iyzico.com § Response Signature Validation,
 * § Webhook). HMAC-SHA256 with the merchant secret key, hex encoded.
 */
final readonly class IyzicoSignature
{
    public function __construct(private string $secretKey) {}

    /**
     * Response signature: the listed fields joined with ':'.
     *
     * @param  list<string|int|float|null>  $fields
     */
    public function verifyResponse(array $fields, ?string $signature): bool
    {
        if ($signature === null || $signature === '' || $this->secretKey === '') {
            return false;
        }

        $data = implode(':', array_map(fn ($value): string => (string) $value, $fields));

        return hash_equals(hash_hmac('sha256', $data, $this->secretKey), strtolower($signature));
    }

    /**
     * X-IYZ-SIGNATURE-V3 of a Checkout Form (HPP) notification: secretKey,
     * iyziEventType, iyziPaymentId, token, paymentConversationId and status
     * concatenated without separator.
     */
    public function verifyCheckoutWebhook(
        string $eventType,
        string $paymentId,
        string $token,
        string $conversationId,
        string $status,
        ?string $signature,
    ): bool {
        if ($signature === null || $signature === '' || $this->secretKey === '') {
            return false;
        }

        $data = $this->secretKey.$eventType.$paymentId.$token.$conversationId.$status;

        return hash_equals(hash_hmac('sha256', $data, $this->secretKey), strtolower($signature));
    }

    /**
     * iyzico signs prices without trailing zeros ("50.00" → "50", "10.50" → "10.5").
     */
    public static function price(string|int|float|null $value): string
    {
        $value = is_float($value) ? self::floatToString($value) : trim((string) $value);

        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return $value;
    }

    /**
     * The shortest round-trip representation, never exponent notation.
     */
    public static function floatToString(float $value): string
    {
        $string = var_export($value, true);

        return str_contains($string, 'E') ? sprintf('%.2F', $value) : $string;
    }
}
