<?php

declare(strict_types=1);

namespace App\Services\Billing\Iyzico;

use App\Contracts\PaymentGateway;
use App\Exceptions\PaymentGatewayException;
use App\Support\Billing\CheckoutOutcome;
use App\Support\Billing\CheckoutRequest;
use App\Support\Billing\CheckoutResult;
use App\Support\Billing\CheckoutSession;
use App\Support\Billing\WebhookNotification;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Iyzipay\ApiResource;
use Iyzipay\DefaultHttpClient;
use Iyzipay\Model\Address;
use Iyzipay\Model\BasketItem;
use Iyzipay\Model\BasketItemType;
use Iyzipay\Model\Buyer;
use Iyzipay\Model\CheckoutForm;
use Iyzipay\Model\CheckoutFormInitialize;
use Iyzipay\Model\Currency;
use Iyzipay\Model\Locale;
use Iyzipay\Model\PaymentGroup;
use Iyzipay\Options;
use Iyzipay\Request\CreateCheckoutFormInitializeRequest;
use Iyzipay\Request\RetrieveCheckoutFormRequest;

/**
 * iyzico Checkout Form adapter (official SDK iyzico/iyzipay-php).
 *
 * - Every response is checked for status and its HMAC signature; an unsigned
 *   or mismatching answer is an exception, never a result.
 * - Only allow-listed, masked fields leave the adapter (CheckoutResult).
 * - Single payment, no installments. Virtual basket items for plans, physical
 *   ones with a shipping address for shop orders.
 */
final class IyzicoGateway implements PaymentGateway
{
    private const string WEBHOOK_EVENT = 'CHECKOUT_FORM_AUTH';

    private readonly IyzicoSignature $signature;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $secretKey,
        private readonly string $baseUrl,
    ) {
        $this->signature = new IyzicoSignature($secretKey);
        ApiResource::setHttpClient(DefaultHttpClient::create(new IyzicoCurl));
    }

    public function name(): string
    {
        return 'iyzico';
    }

    public function initializeCheckout(CheckoutRequest $request): CheckoutSession
    {
        $this->ensureConfigured();

        $iyzico = new CreateCheckoutFormInitializeRequest;
        $iyzico->setLocale($request->locale === 'tr' ? Locale::TR : Locale::EN);
        $iyzico->setConversationId($request->reference);
        $iyzico->setBasketId($request->reference);
        $iyzico->setPrice($request->amount->toDecimalString());
        $iyzico->setPaidPrice($request->amount->toDecimalString());
        $iyzico->setCurrency($this->currency($request->amount));
        $iyzico->setPaymentGroup($request->shipping === null ? PaymentGroup::SUBSCRIPTION : PaymentGroup::PRODUCT);
        $iyzico->setCallbackUrl($request->callbackUrl);
        $iyzico->setEnabledInstallments([1]);
        $iyzico->setBuyer($this->buyer($request));
        $iyzico->setBillingAddress($this->address($request->billing));

        if ($request->shipping !== null) {
            $iyzico->setShippingAddress($this->address(['name' => $request->shipping['recipient'] ?? null] + $request->shipping));
        }

        $iyzico->setBasketItems(array_map(function ($line): BasketItem {
            $item = new BasketItem;
            $item->setId($line->id);
            $item->setName(Str::limit($line->name, 120, ''));
            $item->setCategory1($line->physical ? 'QR stand' : 'Abonelik');
            $item->setItemType($line->physical ? BasketItemType::PHYSICAL : BasketItemType::VIRTUAL);
            $item->setPrice($line->amount->toDecimalString());

            return $item;
        }, $request->lines));

        $response = CheckoutFormInitialize::create($iyzico, $this->options());

        if ($response->getStatus() !== 'success') {
            throw new PaymentGatewayException(
                'iyzico checkout initialize failed: '.($response->getErrorCode() ?? 'no response')
                    .($response->getErrorMessage() !== null ? ' ('.$this->message($response->getErrorMessage()).')' : ''),
                $response->getErrorCode(),
            );
        }

        $token = (string) $response->getToken();

        if ($response->getConversationId() !== $request->reference
            || ! $this->signature->verifyResponse([$response->getConversationId(), $token], $response->getSignature())) {
            throw new PaymentGatewayException('iyzico checkout initialize: invalid response signature.');
        }

        $url = (string) $response->getPaymentPageUrl();

        if ($token === '' || ! str_starts_with($url, 'https://')) {
            throw new PaymentGatewayException('iyzico checkout initialize: missing token or payment page URL.');
        }

        return new CheckoutSession($token, $url);
    }

    public function retrieveCheckout(string $token, string $reference): CheckoutResult
    {
        $this->ensureConfigured();

        $request = new RetrieveCheckoutFormRequest;
        $request->setLocale(Locale::TR);
        $request->setConversationId($reference);
        $request->setToken($token);

        $response = CheckoutForm::retrieve($request, $this->options());

        if ($response->getStatus() !== 'success') {
            if ($response->getStatus() === null) {
                throw new PaymentGatewayException('iyzico checkout retrieve: no response.');
            }

            // A request-level failure for a token we created: not paid (yet).
            return new CheckoutResult(
                outcome: CheckoutOutcome::Pending,
                errorCode: $this->code($response->getErrorCode()),
                errorMessage: $this->message($response->getErrorMessage()),
            );
        }

        $paymentStatus = $response->getPaymentStatus();

        if ($paymentStatus === null || $paymentStatus === '') {
            // The form exists but nothing was paid yet.
            return new CheckoutResult(outcome: CheckoutOutcome::Pending, reference: $response->getConversationId());
        }

        $valid = $response->getToken() === $token && $this->signature->verifyResponse([
            $paymentStatus,
            $response->getPaymentId(),
            $response->getCurrency(),
            $response->getBasketId(),
            $response->getConversationId(),
            IyzicoSignature::price($response->getPaidPrice()),
            IyzicoSignature::price($response->getPrice()),
            $response->getToken(),
        ], $response->getSignature());

        if (! $valid) {
            throw new PaymentGatewayException('iyzico checkout retrieve: invalid response signature.');
        }

        $outcome = match (true) {
            $paymentStatus !== 'SUCCESS' => CheckoutOutcome::Failed,
            (int) $response->getFraudStatus() === 1 => CheckoutOutcome::Succeeded,
            (int) $response->getFraudStatus() === -1 => CheckoutOutcome::Failed,
            default => CheckoutOutcome::Pending, // 0 = fraud review, a notification follows
        };

        return new CheckoutResult(
            outcome: $outcome,
            reference: $response->getConversationId(),
            basketId: $response->getBasketId(),
            paymentId: $response->getPaymentId() !== null ? (string) $response->getPaymentId() : null,
            paidAmount: $this->money($response->getPaidPrice(), (string) $response->getCurrency()),
            cardAssociation: $this->code($response->getCardAssociation()),
            cardFamily: $this->code($response->getCardFamily()),
            cardLastFour: preg_match('/^\d{4}$/', (string) $response->getLastFourDigits()) === 1 ? $response->getLastFourDigits() : null,
            errorCode: $outcome === CheckoutOutcome::Failed ? ($this->code($response->getErrorCode()) ?? ((int) $response->getFraudStatus() === -1 ? 'fraud_rejected' : null)) : null,
            errorMessage: $outcome === CheckoutOutcome::Failed ? $this->message($response->getErrorMessage()) : null,
        );
    }

    public function parseWebhook(Request $request): ?WebhookNotification
    {
        $payload = $request->json()->all();

        $fields = [];

        foreach (['iyziEventType', 'iyziPaymentId', 'token', 'paymentConversationId', 'status'] as $key) {
            $value = $payload[$key] ?? null;

            if (! is_string($value) && ! is_int($value)) {
                return null;
            }

            $fields[$key] = (string) $value;
        }

        if ($fields['iyziEventType'] !== self::WEBHOOK_EVENT) {
            return null;
        }

        $valid = $this->signature->verifyCheckoutWebhook(
            $fields['iyziEventType'],
            $fields['iyziPaymentId'],
            $fields['token'],
            $fields['paymentConversationId'],
            $fields['status'],
            $request->header('X-IYZ-SIGNATURE-V3'),
        );

        return $valid ? new WebhookNotification($fields['token'], $fields['paymentConversationId']) : null;
    }

    private function options(): Options
    {
        $options = new Options;
        $options->setApiKey($this->apiKey);
        $options->setSecretKey($this->secretKey);
        $options->setBaseUrl($this->baseUrl);

        return $options;
    }

    private function ensureConfigured(): void
    {
        if ($this->apiKey === '' || $this->secretKey === '' || ! str_starts_with($this->baseUrl, 'https://')) {
            throw new PaymentGatewayException('iyzico is not configured (IYZICO_API_KEY, IYZICO_SECRET_KEY, IYZICO_BASE_URL).');
        }
    }

    private function currency(Money $amount): string
    {
        return match ($amount->currency) {
            'TRY' => Currency::TL,
            default => throw new InvalidArgumentException("Unsupported currency [{$amount->currency}]."),
        };
    }

    private function buyer(CheckoutRequest $request): Buyer
    {
        [$name, $surname] = $this->splitName($request->buyerName);
        $billing = $request->billing;

        $buyer = new Buyer;
        $buyer->setId($request->buyerId);
        $buyer->setName($name);
        $buyer->setSurname($surname);
        $buyer->setEmail($request->buyerEmail);
        $buyer->setIdentityNumber((string) $billing['tax_id']);
        $buyer->setRegistrationAddress((string) $billing['address']);
        $buyer->setCity((string) $billing['city']);
        $buyer->setCountry('Turkey');
        $buyer->setIp($request->buyerIp);

        if (($billing['phone'] ?? null) !== null) {
            $buyer->setGsmNumber((string) $billing['phone']);
        }

        if (($billing['postal_code'] ?? null) !== null) {
            $buyer->setZipCode((string) $billing['postal_code']);
        }

        if ($request->buyerRegisteredAt !== null) {
            $buyer->setRegistrationDate($request->buyerRegisteredAt->format('Y-m-d H:i:s'));
        }

        return $buyer;
    }

    /**
     * @param  array<string, string|null>  $billing
     */
    private function address(array $billing): Address
    {
        $address = new Address;
        $address->setContactName((string) $billing['name']);
        $address->setCity((string) $billing['city']);
        $address->setCountry('Turkey');
        $address->setAddress(trim(((string) $billing['address']).' '.((string) ($billing['district'] ?? ''))));

        if (($billing['postal_code'] ?? null) !== null) {
            $address->setZipCode((string) $billing['postal_code']);
        }

        return $address;
    }

    /**
     * @return array{string, string}
     */
    private function splitName(string $fullName): array
    {
        $fullName = trim(preg_replace('/\s+/u', ' ', $fullName) ?? '');
        $position = mb_strrpos($fullName, ' ');

        if ($position === false) {
            return [$fullName, $fullName];
        }

        return [mb_substr($fullName, 0, $position), mb_substr($fullName, $position + 1)];
    }

    private function money(string|int|float|null $value, string $currency): ?Money
    {
        if ($value === null || $currency !== Currency::TL) {
            return null;
        }

        try {
            return Money::fromDecimalString(IyzicoSignature::price($value), 'TRY');
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function code(?string $value): ?string
    {
        return $value === null || $value === '' ? null : Str::limit($value, 60, '');
    }

    private function message(?string $value): ?string
    {
        return $value === null || $value === '' ? null : Str::limit(strip_tags($value), 250, '');
    }
}
