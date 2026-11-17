<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Exceptions\PaymentGatewayException;
use App\Services\Billing\Iyzico\IyzicoGateway;
use App\Services\Billing\Iyzico\IyzicoSignature;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Signature handling of the iyzico adapter (no network).
 */
class IyzicoGatewayTest extends TestCase
{
    public function test_prices_are_signed_without_trailing_zeros(): void
    {
        $this->assertSame('50', IyzicoSignature::price('50.00'));
        $this->assertSame('10', IyzicoSignature::price('10.0'));
        $this->assertSame('10.5', IyzicoSignature::price('10.50'));
        $this->assertSame('10.51', IyzicoSignature::price('10.510'));
        $this->assertSame('10.5105', IyzicoSignature::price('10.5105'));
        $this->assertSame('300', IyzicoSignature::price(300.0));
        $this->assertSame('1299.9', IyzicoSignature::price(1299.9));
        $this->assertSame('300', IyzicoSignature::price(300));
    }

    public function test_response_signatures_are_verified_with_the_secret_key(): void
    {
        $signature = new IyzicoSignature('secret');
        $fields = ['SUCCESS', '123', 'TRY', 'basket', 'conv', '300', '300', 'token'];
        $valid = hash_hmac('sha256', implode(':', $fields), 'secret');

        $this->assertTrue($signature->verifyResponse($fields, $valid));
        $this->assertTrue($signature->verifyResponse($fields, strtoupper($valid)));
        $this->assertFalse($signature->verifyResponse($fields, hash_hmac('sha256', implode(':', $fields), 'other')));
        $this->assertFalse($signature->verifyResponse(['FAILURE', ...array_slice($fields, 1)], $valid));
        $this->assertFalse($signature->verifyResponse($fields, null));
        $this->assertFalse((new IyzicoSignature(''))->verifyResponse($fields, hash_hmac('sha256', implode(':', $fields), '')));
    }

    public function test_checkout_webhooks_are_parsed_only_with_a_valid_v3_signature(): void
    {
        $gateway = new IyzicoGateway('key', 'secret', 'https://sandbox-api.iyzipay.com');
        $payload = [
            'paymentConversationId' => 'conv', 'merchantId' => '1', 'token' => 'tok-123', 'status' => 'SUCCESS',
            'iyziReferenceCode' => 'r', 'iyziEventType' => 'CHECKOUT_FORM_AUTH', 'iyziEventTime' => 1, 'iyziPaymentId' => 99,
        ];
        $signature = hash_hmac('sha256', 'secretCHECKOUT_FORM_AUTH99tok-123convSUCCESS', 'secret');

        $notification = $gateway->parseWebhook($this->jsonRequest($payload, $signature));
        $this->assertSame('tok-123', $notification?->token);
        $this->assertSame('conv', $notification?->reference);

        $this->assertNull($gateway->parseWebhook($this->jsonRequest($payload, str_repeat('0', 64))));
        $this->assertNull($gateway->parseWebhook($this->jsonRequest(['iyziEventType' => 'API_AUTH'] + $payload, $signature)));
        $this->assertNull($gateway->parseWebhook($this->jsonRequest(['token' => ['x']] + $payload, $signature)));
    }

    public function test_an_unconfigured_gateway_never_calls_the_provider(): void
    {
        $this->expectException(PaymentGatewayException::class);

        (new IyzicoGateway('', '', 'https://sandbox-api.iyzipay.com'))->retrieveCheckout('token');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function jsonRequest(array $payload, string $signature): Request
    {
        return Request::create('/billing/iyzico/webhook', 'POST', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_IYZ_SIGNATURE_V3' => $signature,
        ], content: json_encode($payload));
    }
}
