<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\BillingInterval;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShopOrderStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\CheckoutNotAllowedException;
use App\Exceptions\CrossTenantWriteException;
use App\Filament\Admin\Pages\ManagePaymentSettings;
use App\Filament\Admin\Resources\BankTransfers\Pages\ListBankTransfers;
use App\Filament\App\Pages\BankTransferPayment;
use App\Filament\App\Pages\Checkout;
use App\Mail\BankTransferNotifiedMail;
use App\Mail\PaymentReceiptMail;
use App\Models\AdminAuditEvent;
use App\Models\Location;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\Plan;
use App\Models\QrCode;
use App\Models\ShopOrder;
use App\Models\ShopProduct;
use App\Models\ShopSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Rules\Iban;
use App\Services\Admin\PaymentAdminService;
use App\Services\Billing\BankTransferService;
use App\Services\Billing\CheckoutService;
use App\Services\Billing\PaymentProcessor;
use App\Services\Shop\ShopCart;
use App\Services\Shop\ShopCatalog;
use App\Services\Shop\ShopCheckoutService;
use App\Services\Subscription\SubscriptionService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\BuildsBilling;
use Tests\Support\BuildsMenus;
use Tests\TestCase;

/**
 * Payment methods switched in /admin and bank transfers (havale/EFT)
 * confirmed manually (docs/architecture/subscriptions.md § Bank transfers).
 */
class BankTransferTest extends TestCase
{
    use BuildsBilling, BuildsMenus, RefreshDatabase;

    private const string IBAN = 'TR330006100519786457841326';

    private Tenant $tenant;

    private User $owner;

    private Plan $starter;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->fakeGateway();
        $this->tenant = Tenant::factory()->create(['slug' => 'yaman-kahvesi']);
        $this->starter = $this->pricedPlan('starter', 30000, 240000);
        app(SubscriptionService::class)->startTrial($this->tenant, $this->starter);
        Location::factory()->for($this->tenant)->create();
        $this->owner = $this->ownerOf($this->tenant);
        $this->billingProfile($this->tenant);
        $this->paymentSettings(['card_enabled' => false, 'bank_transfer_enabled' => true]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function paymentSettings(array $values, ?string $instructions = 'Açıklamaya {reference} yazın. Tutar: {amount}'): void
    {
        $settings = PaymentSetting::persisted();
        $settings->fill([
            'bank_account_holder' => 'Menitra Yazılım A.Ş.',
            'bank_name' => 'Örnek Bank',
            'bank_iban' => self::IBAN,
            'notification_email' => 'odeme@example.com',
            ...$values,
        ])->save();

        if ($instructions !== null) {
            $settings->translations()->updateOrCreate(['locale' => 'tr'], ['bank_transfer_instructions' => $instructions]);
        }
    }

    private function startTransfer(?Tenant $tenant = null, BillingInterval $interval = BillingInterval::Yearly): Payment
    {
        $tenant ??= $this->tenant;

        return $this->inTenant($tenant, function () use ($tenant, $interval): Payment {
            app(CheckoutService::class)->start($tenant, $this->ownerOf($tenant), $this->starter, $interval, '85.34.78.112', 'tr', 1, PaymentMethod::BankTransfer);

            return Payment::query()->latest('id')->firstOrFail();
        });
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        app(TenantContext::class)->clear();

        return $admin;
    }

    private function inPanel(User $user, string $component, array $params = []): Testable
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('app');
        Filament::setTenant($this->tenant);
        app(TenantContext::class)->set($this->tenant);

        return Livewire::test($component, $params);
    }

    private function fresh(Payment $payment): Payment
    {
        return Payment::withoutTenantScope()->findOrFail($payment->getKey());
    }

    public function test_the_iban_rule_checks_the_turkish_format_and_checksum(): void
    {
        $this->assertTrue(Iban::isValid(Iban::normalize('tr33 0006 1005 1978 6457 8413 26')));
        $this->assertFalse(Iban::isValid('TR330006100519786457841327'));
        $this->assertFalse(Iban::isValid('DE89370400440532013000'));
    }

    public function test_only_super_admins_edit_payment_settings_and_the_account_is_validated(): void
    {
        $staff = User::factory()->platformStaff()->create();
        $this->actingAs($staff)->get('/admin/payment-settings')->assertForbidden();

        $this->superAdmin();
        $this->get('/admin/payment-settings')->assertOk();

        Livewire::test(ManagePaymentSettings::class)
            ->fillForm(['bank_transfer_enabled' => true, 'bank_iban' => 'TR00 1234', 'bank_account_holder' => 'X'])
            ->call('save')
            ->assertHasFormErrors(['bank_iban']);
        Livewire::test(ManagePaymentSettings::class)
            ->fillForm(['bank_transfer_enabled' => true, 'bank_iban' => null, 'bank_account_holder' => null])
            ->call('save')
            ->assertHasFormErrors(['bank_iban', 'bank_account_holder']);

        Livewire::test(ManagePaymentSettings::class)
            ->fillForm([
                'card_enabled' => true,
                'bank_transfer_enabled' => true,
                'bank_account_holder' => 'Menitra Yazılım A.Ş.',
                'bank_iban' => 'tr33 0006 1005 1978 6457 8413 26',
                'translations' => ['tr' => ['bank_transfer_instructions' => 'Açıklama: {reference}'], 'en' => ['bank_transfer_instructions' => 'Reference: {reference}']],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = PaymentSetting::current();
        $this->assertSame(self::IBAN, $settings->bank_iban);
        $this->assertTrue($settings->isEnabled(PaymentMethod::Card));
        $this->assertSame('Reference: MN1', $settings->instructions('en', 'MN1', '₺1', 'X'));
    }

    public function test_a_disabled_method_is_refused_on_the_server(): void
    {
        // Card payments are switched off in setUp.
        try {
            $this->startCheckout($this->tenant, $this->starter);
            $this->fail('A disabled card method must be refused.');
        } catch (CheckoutNotAllowedException $exception) {
            $this->assertSame(__('billing.errors.method_unavailable'), $exception->getMessage());
        }

        $this->assertSame([], $this->gateway->started);

        // Without a bank account a switched-on transfer isn't offered either.
        $this->paymentSettings(['bank_iban' => null]);
        $this->expectException(CheckoutNotAllowedException::class);
        $this->startTransfer();
    }

    public function test_an_owner_starts_a_transfer_without_the_provider(): void
    {
        $payment = $this->startTransfer();

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(PaymentMethod::BANK_TRANSFER_PROVIDER, $payment->provider);
        $this->assertTrue($payment->isOpenBankTransfer());
        $this->assertMatchesRegularExpression('/^MN[2-9A-HJKMNP-Z]{8}$/', (string) $payment->transfer_reference);
        $this->assertNull($payment->provider_token);
        $this->assertSame(240000, $payment->amount);
        $this->assertSame([], $this->gateway->started);

        // A second payment for the subscription waits until this one is decided.
        $this->paymentSettings(['card_enabled' => true]);
        $this->expectException(CheckoutNotAllowedException::class);
        $this->expectExceptionMessage(__('billing.errors.transfer_open'));
        $this->startCheckout($this->tenant, $this->starter);
    }

    public function test_the_checkout_page_offers_the_enabled_methods_and_redirects_to_the_transfer(): void
    {
        $this->paymentSettings(['card_enabled' => true]);

        $this->inPanel($this->owner, Checkout::class)
            ->assertSee(__('billing.methods.bank_transfer'))
            ->fillForm(['plan' => 'starter', 'interval' => 'yearly', 'method' => 'bank_transfer', 'terms' => true])
            ->call('pay')
            ->assertHasNoFormErrors()
            ->assertRedirect(BankTransferPayment::getUrl(['payment' => Payment::query()->latest('id')->value('public_id')], panel: 'app', tenant: $this->tenant));

        $this->assertSame([], $this->gateway->started);
    }

    public function test_the_owner_sees_the_account_reports_the_transfer_and_the_team_is_told(): void
    {
        $payment = $this->startTransfer();

        $this->inPanel($this->owner, BankTransferPayment::class, ['payment' => $payment->public_id])
            ->assertSee('TR33 0006 1005 1978 6457 8413 26')
            ->assertSee('Menitra Yazılım A.Ş.')
            ->assertSee('Açıklamaya '.$payment->transfer_reference.' yazın.')
            ->callAction('notify')
            ->assertActionHidden('notify')
            ->assertActionHidden('cancelTransfer');

        $this->assertNotNull($this->fresh($payment)->transfer_notified_at);
        $this->assertTrue($this->fresh($payment)->isPending());
        Mail::assertQueued(BankTransferNotifiedMail::class, fn (BankTransferNotifiedMail $mail): bool => $mail->hasTo('odeme@example.com')
            && $mail->reference === $payment->transfer_reference
            && ! str_contains($mail->render(), '10000000146'));

        // Reported transfers can't be cancelled by the owner any more.
        $this->expectException(ValidationException::class);
        $this->inTenant($this->tenant, fn () => app(BankTransferService::class)->cancel($this->tenant, $this->owner, $payment));
    }

    public function test_only_the_owner_of_the_payments_tenant_reaches_it(): void
    {
        $payment = $this->startTransfer();
        $manager = TenantMembership::factory()->for($this->tenant)->manager()->create()->user;

        try {
            $this->inTenant($this->tenant, fn () => app(BankTransferService::class)->markNotified($this->tenant, $manager, $payment));
            $this->fail('A manager must not report a transfer.');
        } catch (AuthorizationException) {
        }

        $other = Tenant::factory()->create();
        app(SubscriptionService::class)->startTrial($other, $this->starter);

        try {
            $this->inTenant($other, fn () => app(BankTransferService::class)->markNotified($other, $this->ownerOf($other), $payment));
            $this->fail('Another tenant must not report this transfer.');
        } catch (CrossTenantWriteException) {
        }

        $this->actingAs($this->ownerOf($other));
        Filament::setCurrentPanel('app');
        Filament::setTenant($other);
        app(TenantContext::class)->set($other);

        Livewire::test(BankTransferPayment::class, ['payment' => $payment->public_id])->assertNotFound();

        $this->assertNull($this->fresh($payment)->transfer_notified_at);
    }

    public function test_an_approved_transfer_activates_the_subscription_once_and_is_audited(): void
    {
        $payment = $this->startTransfer();
        $this->inTenant($this->tenant, fn () => app(BankTransferService::class)->markNotified($this->tenant, $this->owner, $payment));
        $trialEnd = $this->inTenant($this->tenant, fn () => Subscription::query()->firstOrFail()->trial_ends_at);

        $this->superAdmin();
        $this->get('/admin/bank-transfers')->assertOk()->assertSee((string) $payment->transfer_reference);
        Livewire::test(ListBankTransfers::class)
            ->callTableAction('approve', $payment->getKey())
            ->assertHasNoTableActionErrors();

        $payment = $this->fresh($payment);
        $this->assertSame(PaymentStatus::Succeeded, $payment->status);
        $this->assertSame(PaymentKind::Activation, $payment->kind);
        $this->assertNotNull($payment->receipt_number);
        $this->assertFalse(app(TenantContext::class)->check(), 'The admin request keeps no tenant context.');

        $subscription = $this->inTenant($this->tenant, fn () => Subscription::query()->firstOrFail());
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->current_period_starts_at->equalTo($trialEnd), 'Trial days are kept.');
        $this->assertSame(PaymentMethod::BANK_TRANSFER_PROVIDER, $subscription->provider);
        Mail::assertQueued(PaymentReceiptMail::class, 1);

        $event = AdminAuditEvent::query()->sole();
        $this->assertSame('payment.bank_transfer_approved', $event->action->value);
        $this->assertSame($payment->transfer_reference, $event->meta['reference']);

        // Never applied twice.
        Livewire::test(ListBankTransfers::class)
            ->filterTable('status', PaymentStatus::Succeeded->value)
            ->assertTableActionHidden('approve', $payment->getKey());
        $this->expectException(ValidationException::class);
        $this->inTenant($this->tenant, fn () => app(PaymentProcessor::class)->approveBankTransfer($payment));
    }

    public function test_a_rejected_transfer_changes_nothing_and_shows_the_reason(): void
    {
        $payment = $this->startTransfer();
        $this->superAdmin();

        Livewire::test(ListBankTransfers::class)
            ->callTableAction('reject', $payment->getKey(), ['reason' => 'Tutar hesaba ulaşmadı'])
            ->assertHasNoTableActionErrors();

        $payment = $this->fresh($payment);
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('transfer_rejected', $payment->failure_code);
        $this->assertSame(SubscriptionStatus::Trialing, $this->inTenant($this->tenant, fn () => Subscription::query()->firstOrFail()->status));
        $this->assertSame('payment.bank_transfer_rejected', AdminAuditEvent::query()->sole()->action->value);

        $this->inPanel($this->owner, BankTransferPayment::class, ['payment' => $payment->public_id])
            ->assertSee('Tutar hesaba ulaşmadı')
            ->assertActionHidden('notify');

        // A decided transfer no longer blocks a new payment.
        $this->assertNotNull($this->startTransfer()->transfer_reference);
    }

    public function test_platform_staff_only_read_bank_transfers(): void
    {
        $payment = $this->startTransfer();
        $staff = User::factory()->platformStaff()->create();
        $this->actingAs($staff);
        Filament::setCurrentPanel('admin');

        $this->get('/admin/bank-transfers')->assertOk()->assertSee((string) $payment->transfer_reference);
        Livewire::test(ListBankTransfers::class)
            ->assertTableActionHidden('approve', $payment->getKey())
            ->assertTableActionHidden('reject', $payment->getKey());

        $this->expectException(AuthorizationException::class);
        app(PaymentAdminService::class)->approve($staff, $payment);
    }

    public function test_the_owner_cancels_an_unreported_transfer(): void
    {
        $payment = $this->startTransfer();

        $this->inPanel($this->owner, BankTransferPayment::class, ['payment' => $payment->public_id])
            ->callAction('cancelTransfer');

        $payment = $this->fresh($payment);
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('cancelled', $payment->failure_code);
    }

    public function test_reconciliation_leaves_bank_transfers_alone(): void
    {
        $payment = $this->startTransfer();
        $this->travelTo(CarbonImmutable::now()->addDays(3));

        Artisan::call('billing:reconcile-payments');

        $this->assertTrue($this->fresh($payment)->isPending());
    }

    public function test_a_shop_order_paid_by_transfer_is_paid_on_approval(): void
    {
        ShopSetting::current()->fill(['is_enabled' => true, 'shipping_amount' => 0])->save();
        $product = ShopProduct::query()->create(['price_amount' => 12000, 'is_active' => true, 'max_per_order' => 50]);
        $product->translations()->create(['locale' => 'tr', 'name' => 'Ahşap stand']);
        $this->app->forgetInstance(ShopCatalog::class);
        $location = $this->inTenant($this->tenant, fn () => Location::query()->firstOrFail());
        $code = $this->inTenant($this->tenant, fn (): QrCode => QrCode::query()->create(['location_id' => $location->getKey(), 'label' => 'Moda']));

        $this->inTenant($this->tenant, fn () => app(ShopCart::class)->add($this->tenant, $this->owner, $product->fresh(), 2, [$code->getKey()]));
        $address = ['recipient' => 'Ayşe Yaman', 'phone' => '0535 000 00 00', 'address' => 'Moda Cad. No: 12 Kat: 1', 'district' => 'Kadıköy', 'city' => 'İstanbul', 'postal_code' => '34710'];
        $url = $this->inTenant($this->tenant, fn () => app(ShopCheckoutService::class)->start($this->tenant, $this->owner, $address, null, 24000, '85.34.78.112', 'tr', PaymentMethod::BankTransfer));

        $payment = $this->inTenant($this->tenant, fn () => Payment::query()->whereNotNull('shop_order_id')->firstOrFail());
        $this->assertStringContainsString('payments/bank-transfer', $url);
        $this->assertSame(PaymentKind::ShopOrder, $payment->kind);
        $this->assertSame([], $this->gateway->started);

        // The same cart can't be ordered again while the transfer waits.
        try {
            $this->inTenant($this->tenant, fn () => app(ShopCheckoutService::class)->start($this->tenant, $this->owner, $address, null, 24000, '85.34.78.112', 'tr', PaymentMethod::BankTransfer));
            $this->fail('A second order must wait for the open transfer.');
        } catch (CheckoutNotAllowedException $exception) {
            $this->assertSame(__('billing.errors.transfer_open'), $exception->getMessage());
        }

        $this->superAdmin();
        Livewire::test(ListBankTransfers::class)->callTableAction('approve', $payment->getKey())->assertHasNoTableActionErrors();

        $order = $this->inTenant($this->tenant, fn () => ShopOrder::query()->firstOrFail());
        $this->assertSame(ShopOrderStatus::Paid, $order->status);
        $this->assertSame(0, $this->inTenant($this->tenant, fn () => app(ShopCart::class)->quantity()));
        $this->assertSame(SubscriptionStatus::Trialing, $this->inTenant($this->tenant, fn () => Subscription::query()->firstOrFail()->status));
    }
}
