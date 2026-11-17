<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\PaymentGateway;
use App\Exceptions\PaymentGatewayException;
use App\Services\Billing\Iyzico\IyzicoGateway;
use App\Support\Billing\CheckoutOutcome;
use App\Support\Billing\CheckoutRequest;
use App\Support\Billing\CheckoutResult;
use App\Support\Billing\CheckoutSession;
use App\Support\Billing\WebhookNotification;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * In-memory provider for tests: records started checkouts and answers
 * retrieveCheckout() with whatever the test set. Webhooks are verified by
 * the real iyzico signature code with WEBHOOK_SECRET.
 */
final class FakePaymentGateway implements PaymentGateway
{
    public const string WEBHOOK_SECRET = 'test-secret-key';

    /** @var list<CheckoutRequest> */
    public array $started = [];

    /** @var array<string, CheckoutResult> token => result */
    public array $results = [];

    public bool $failInitialize = false;

    public bool $failRetrieve = false;

    public int $retrieveCalls = 0;

    public function name(): string
    {
        return 'iyzico';
    }

    public function initializeCheckout(CheckoutRequest $request): CheckoutSession
    {
        if ($this->failInitialize) {
            throw new PaymentGatewayException('provider down', '10051');
        }

        $this->started[] = $request;

        return new CheckoutSession('tok-'.Str::lower((string) Str::ulid()), 'https://sandbox-cpp.iyzipay.com/?token=fake');
    }

    public function retrieveCheckout(string $token): CheckoutResult
    {
        $this->retrieveCalls++;

        if ($this->failRetrieve) {
            throw new PaymentGatewayException('provider down');
        }

        return $this->results[$token] ?? new CheckoutResult(CheckoutOutcome::Pending);
    }

    public function parseWebhook(Request $request): ?WebhookNotification
    {
        return (new IyzicoGateway('key', self::WEBHOOK_SECRET, 'https://sandbox-api.iyzipay.com'))->parseWebhook($request);
    }

    /**
     * The provider's answer for a paid checkout of $reference (the payment's public id).
     */
    public function paid(string $token, string $reference, int $amountMinor, string $paymentId = '28461234'): void
    {
        $this->results[$token] = new CheckoutResult(
            outcome: CheckoutOutcome::Succeeded,
            reference: $reference,
            basketId: $reference,
            paymentId: $paymentId,
            paidAmount: Money::fromMinor($amountMinor),
            cardAssociation: 'MASTER_CARD',
            cardFamily: 'Bonus',
            cardLastFour: '0008',
        );
    }

    public function declined(string $token, string $reference): void
    {
        $this->results[$token] = new CheckoutResult(
            outcome: CheckoutOutcome::Failed,
            reference: $reference,
            errorCode: '10051',
            errorMessage: 'Kart limiti yetersiz',
        );
    }
}
