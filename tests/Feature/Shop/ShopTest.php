<?php

declare(strict_types=1);

namespace Tests\Feature\Shop;

use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Enums\ShopOrderStatus;
use App\Exceptions\CheckoutNotAllowedException;
use App\Exceptions\CrossTenantWriteException;
use App\Filament\Admin\Pages\ManageShopSettings;
use App\Filament\Admin\Resources\ShopOrders\Pages\ListShopOrders;
use App\Filament\Admin\Resources\ShopProducts\Pages\CreateShopProduct;
use App\Filament\App\Pages\Shop;
use App\Filament\App\Pages\ShopCheckout;
use App\Filament\App\Pages\ShopOrders;
use App\Mail\NewShopOrderMail;
use App\Mail\PaymentReceiptMail;
use App\Mail\ShopOrderMail;
use App\Models\AdminAuditEvent;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\QrCode;
use App\Models\ShopCartItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopProduct;
use App\Models\ShopSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Billing\PaymentProcessor;
use App\Services\Shop\ShopCart;
use App\Services\Shop\ShopCatalog;
use App\Services\Shop\ShopCheckoutService;
use App\Tenancy\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\BuildsBilling;
use Tests\Support\BuildsMenus;
use Tests\TestCase;

/**
 * QR stand shop: products in /admin, cart and checkout in /app, payment
 * through the verified provider result, fulfilment in /admin.
 */
class ShopTest extends TestCase
{
    use BuildsBilling, BuildsMenus, RefreshDatabase;

    private const array ADDRESS = [
        'recipient' => 'Ayşe Yaman',
        'phone' => '0535 000 00 00',
        'address' => 'Moda Cad. No: 12 Kat: 1',
        'district' => 'Kadıköy',
        'city' => 'İstanbul',
        'postal_code' => '34710',
    ];

    private Tenant $tenant;

    private User $owner;

    private QrCode $code;

    private ShopProduct $stand;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->fakeGateway();
        $this->tenant = $this->subscribedTenant('konak');
        $this->owner = $this->ownerOf($this->tenant);
        $this->code = $this->qr($this->tenant, $this->location($this->tenant, 'moda', true), 'Moda');
        $this->billingProfile($this->tenant);
        $this->settings(['is_enabled' => true, 'shipping_amount' => 5000, 'free_shipping_from' => 100000, 'notification_email' => 'ops@example.com']);
        $this->stand = $this->product();
    }

    private function settings(array $values): void
    {
        ShopSetting::current()->fill($values)->save();
        $this->app->forgetInstance(ShopCatalog::class);
    }

    private function product(array $attributes = []): ShopProduct
    {
        $product = ShopProduct::query()->create([...['price_amount' => 12000, 'is_active' => true, 'max_per_order' => 50], ...$attributes]);
        $product->translations()->create(['locale' => 'tr', 'name' => 'Ahşap stand']);
        $this->app->forgetInstance(ShopCatalog::class);

        return $product->fresh();
    }

    private function qr(Tenant $tenant, Location $location, string $label): QrCode
    {
        return $this->inTenant($tenant, fn (): QrCode => QrCode::query()->create(['location_id' => $location->getKey(), 'label' => $label]));
    }

    private function add(int $quantity, ?QrCode $code = null, ?ShopProduct $product = null): void
    {
        $this->inTenant($this->tenant, fn () => app(ShopCart::class)->add($this->tenant, $this->owner, $product ?? $this->stand, $quantity, [($code ?? $this->code)->getKey()]));
    }

    private function start(?int $expected = null, array $address = self::ADDRESS): Payment
    {
        return $this->inTenant($this->tenant, function () use ($expected, $address): Payment {
            app(ShopCheckoutService::class)->start($this->tenant, $this->owner, $address, 'Öğleden sonra', $expected ?? app(ShopCart::class)->quote()->total->minor, '85.34.78.112', 'tr');

            return Payment::query()->latest('id')->firstOrFail();
        });
    }

    private function order(): ShopOrder
    {
        return $this->inTenant($this->tenant, fn (): ShopOrder => ShopOrder::query()->with('items')->latest('id')->firstOrFail());
    }

    private function paidOrder(int $quantity = 2): ShopOrder
    {
        $this->add($quantity);
        $payment = $this->start();
        $this->gateway->paid($payment->provider_token, $payment->public_id, $payment->amount, 'pay-'.$payment->getKey());
        $this->post('/billing/iyzico/callback', ['token' => $payment->provider_token])->assertStatus(303);

        return $this->order();
    }

    private function inPanel(User $user, string $component, array $params = []): Testable
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('app');
        Filament::setTenant($this->tenant);
        app(TenantContext::class)->set($this->tenant);

        return Livewire::test($component, $params);
    }

    public function test_a_super_admin_creates_products_and_settings_and_staff_cannot(): void
    {
        Storage::fake('media');
        $staff = User::factory()->platformStaff()->create();
        $this->actingAs($staff)->get('/admin/shop-products')->assertForbidden();
        $this->actingAs($staff)->get('/admin/shop-settings')->assertForbidden();

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->get('/admin/shop-products')->assertOk();
        $this->actingAs($admin)->get('/admin/shop-settings')->assertOk();
        Filament::setCurrentPanel('admin');

        Livewire::test(CreateShopProduct::class)
            ->fillForm([
                'image_path' => UploadedFile::fake()->image('stand.png', 300, 200),
                'price_amount' => '249,90',
                'max_per_order' => 100,
                'stock_quantity' => 40,
                'is_personalized' => true,
                'is_active' => true,
                'translations' => ['tr' => ['name' => 'Pleksi stand', 'description' => 'A6'], 'en' => ['name' => '', 'description' => '']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = ShopProduct::query()->latest('id')->firstOrFail();
        $this->assertSame(24990, $product->price_amount);
        $this->assertSame('Pleksi stand', $product->translate('tr')->name);
        $this->assertStringStartsWith('platform/shop/', $product->image_path);
        Storage::disk('media')->assertExists($product->image_path);
        $this->assertFalse($admin->can('delete', $product));
        $this->get("/admin/shop-products/{$product->getKey()}/edit")->assertOk();

        Livewire::test(CreateShopProduct::class)
            ->fillForm(['price_amount' => '0,00', 'max_per_order' => 1, 'translations' => ['tr' => ['name' => 'Bedava']]])
            ->call('create')
            ->assertHasFormErrors(['price_amount']);

        Livewire::test(ManageShopSettings::class)
            ->fillForm(['is_enabled' => true, 'shipping_amount' => '75', 'free_shipping_from' => '', 'min_delivery_days' => 3, 'max_delivery_days' => 5])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(7500, ShopSetting::current()->shipping_amount);
        $this->assertNull(ShopSetting::current()->free_shipping_from);
    }

    public function test_the_cart_prices_lines_and_shipping_on_the_server(): void
    {
        $second = $this->qr($this->tenant, $this->location($this->tenant, 'cihangir'), 'Cihangir');
        $this->add(2);
        $this->add(1);
        $this->add(1, $second);

        $quote = $this->inTenant($this->tenant, fn () => app(ShopCart::class)->quote());
        $this->assertCount(2, $quote->items, 'One line per code; the same code is merged.');
        $this->assertSame([48000, 5000, 53000], [$quote->subtotal->minor, $quote->shipping->minor, $quote->total->minor]);

        $this->add(5);
        $quote = $this->inTenant($this->tenant, fn () => app(ShopCart::class)->quote());
        $this->assertSame([108000, 0], [$quote->subtotal->minor, $quote->shipping->minor], 'Free from the threshold on.');
    }

    public function test_cart_limits_stock_and_codes_are_enforced(): void
    {
        $other = $this->subscribedTenant('baska');
        $foreign = $this->qr($other, $this->location($other, 'merkez', true), 'Merkez');
        $this->stand->update(['stock_quantity' => 3]);
        $this->app->forgetInstance(ShopCatalog::class);

        foreach ([fn () => $this->add(1, $foreign), fn () => $this->add(4), fn () => $this->add(0)] as $attempt) {
            try {
                $attempt();
                $this->fail('Must be refused.');
            } catch (ValidationException) {
                $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => ShopCartItem::query()->count()));
            }
        }

        $this->add(3);
        $this->stand->update(['stock_quantity' => 2]);
        $this->app->forgetInstance(ShopCatalog::class);
        $this->expectException(CheckoutNotAllowedException::class);
        $this->start();
    }

    public function test_the_database_refuses_another_tenants_code(): void
    {
        $other = $this->subscribedTenant('baska');
        $foreign = $this->qr($other, $this->location($other, 'merkez', true), 'Merkez');

        $this->expectException(QueryException::class);
        DB::table('shop_cart_items')->insert(['tenant_id' => $this->tenant->getKey(), 'shop_product_id' => $this->stand->getKey(), 'qr_code_id' => $foreign->getKey(), 'quantity' => 1]);
    }

    public function test_only_owners_of_the_current_tenant_shop(): void
    {
        $manager = TenantMembership::factory()->for($this->tenant)->manager()->create()->user;
        foreach (['shop', 'shop/checkout', 'shop/orders'] as $path) {
            $this->actingAs($manager)->get("/app/konak/{$path}")->assertForbidden();
            $this->actingAs($this->owner)->get("/app/konak/{$path}")->assertOk();
        }

        app(TenantContext::class)->clear();

        try {
            $this->inTenant($this->tenant, fn () => app(ShopCart::class)->add($this->tenant, $manager, $this->stand, 1, [$this->code->getKey()]));
            $this->fail('Managers must not shop.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $other = $this->subscribedTenant('baska');
        $this->expectException(CrossTenantWriteException::class);
        $this->inTenant($other, fn () => app(ShopCart::class)->add($this->tenant, $this->owner, $this->stand, 1, [$this->code->getKey()]));
    }

    public function test_the_panel_adds_to_the_cart_and_pays_with_the_delivery_address(): void
    {
        $this->inPanel($this->owner, Shop::class)
            ->assertSee('Ahşap stand')
            ->callAction('addToCart', ['codes' => [$this->code->getKey()], 'quantity' => 2], ['product' => $this->stand->public_id])
            ->assertHasNoActionErrors();

        $this->inPanel($this->owner, ShopCheckout::class)
            ->assertSee('Ahşap stand')
            ->fillForm([...self::ADDRESS, 'terms' => false])
            ->call('pay')
            ->assertHasFormErrors(['terms'])
            ->fillForm([...self::ADDRESS, 'phone' => '12', 'terms' => true])
            ->call('pay')
            ->assertHasFormErrors(['phone'])
            ->fillForm([...self::ADDRESS, 'terms' => true])
            ->call('pay')
            ->assertRedirect('https://sandbox-cpp.iyzipay.com/?token=fake');

        $order = $this->order();
        $this->assertSame(ShopOrderStatus::AwaitingPayment, $order->status);
        $this->assertSame(29000, $order->total_amount);
        $this->assertSame('+905350000000', $order->delivery_address['phone']);
        $this->assertSame($this->code->code, $order->items->sole()->qr_code);

        $raw = DB::table('shop_orders')->value('delivery_address');
        $this->assertStringNotContainsString('Moda', $raw, 'The address is stored encrypted.');
        $this->assertArrayNotHasKey('delivery_address', $order->toArray());

        $request = $this->gateway->started[0];
        $this->assertSame(29000, array_sum(array_map(fn ($line): int => $line->amount->minor, $request->lines)));
        $this->assertTrue($request->lines[0]->physical);
        $this->assertSame('Kadıköy', $request->shipping['district']);
    }

    public function test_the_cart_line_can_be_removed_only_within_the_tenant(): void
    {
        $this->add(1);
        $id = $this->inTenant($this->tenant, fn (): int => ShopCartItem::query()->sole()->getKey());
        $other = $this->subscribedTenant('baska');

        $this->inTenant($other, fn () => app(ShopCart::class)->remove($other, $this->ownerOf($other), $id));
        $this->assertDatabaseHas('shop_cart_items', ['id' => $id]);

        $this->inPanel($this->owner, ShopCheckout::class)->call('removeItem', $id);
        $this->assertDatabaseMissing('shop_cart_items', ['id' => $id]);
    }

    public function test_a_changed_price_closed_shop_or_missing_billing_stops_the_checkout(): void
    {
        $this->add(1);

        foreach ([
            'shop_price_changed' => fn () => $this->start(1),
            'shop_closed' => function (): void {
                $this->settings(['is_enabled' => false]);
                $this->start();
            },
            'billing_profile_missing' => function (): void {
                $this->settings(['is_enabled' => true]);
                DB::table('tenant_billing_profiles')->delete();
                $this->start();
            },
        ] as $reason => $attempt) {
            try {
                $attempt();
                $this->fail($reason);
            } catch (CheckoutNotAllowedException $exception) {
                $this->assertSame(__("billing.errors.{$reason}"), $exception->getMessage());
            }
        }

        $this->assertDatabaseCount('shop_orders', 0);
        $this->assertSame([], $this->gateway->started);
    }

    public function test_a_provider_error_fails_the_order_and_keeps_the_cart(): void
    {
        $this->add(1);
        $this->gateway->failInitialize = true;

        try {
            $this->start();
            $this->fail();
        } catch (CheckoutNotAllowedException) {
            $this->assertSame(ShopOrderStatus::PaymentFailed, $this->order()->status);
            $this->assertDatabaseCount('shop_cart_items', 1);
        }
    }

    public function test_a_paid_order_takes_stock_clears_the_cart_and_notifies(): void
    {
        $this->stand->update(['stock_quantity' => 10]);
        $this->app->forgetInstance(ShopCatalog::class);
        $this->add(2);
        $payment = $this->start();
        $this->gateway->paid($payment->provider_token, $payment->public_id, 29000);
        $before = $this->inTenant($this->tenant, fn () => Subscription::query()->sole()->getAttributes());

        $this->post('/billing/iyzico/callback', ['token' => $payment->provider_token])
            ->assertRedirect('http://localhost/app/konak/shop/orders?payment='.$payment->public_id);
        $this->post('/billing/iyzico/callback', ['token' => $payment->provider_token]);

        $order = $this->order();
        $this->assertSame(ShopOrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(8, $this->stand->fresh()->stock_quantity, 'Taken once.');
        $this->assertDatabaseCount('shop_cart_items', 0);
        $this->assertSame($before, $this->inTenant($this->tenant, fn () => Subscription::query()->sole()->getAttributes()));

        $payment = $this->inTenant($this->tenant, fn () => Payment::query()->sole());
        $this->assertSame(PaymentKind::ShopOrder, $payment->kind);
        $this->assertNotNull($payment->receipt_number);
        Mail::assertQueued(ShopOrderMail::class, fn (ShopOrderMail $mail): bool => $mail->hasTo('fatura@example.com') && $mail->status === ShopOrderStatus::Paid);
        Mail::assertQueued(ShopOrderMail::class, 1);
        Mail::assertQueued(NewShopOrderMail::class, fn (NewShopOrderMail $mail): bool => $mail->hasTo('ops@example.com') && ! str_contains($mail->render(), 'Moda Cad'));
        Mail::assertNotQueued(PaymentReceiptMail::class);

        $this->actingAs($this->owner)->get('/app/konak/shop/orders?payment='.$payment->public_id)
            ->assertOk()->assertSee(__('shop.result.succeeded.title'))->assertSee($order->order_number);
        $this->get('/app/konak/subscription/receipt?payment='.$payment->public_id)
            ->assertOk()->assertSee('Ahşap stand')->assertSee($order->order_number);
        $this->get('/app/konak/subscription')->assertOk()->assertDontSee($payment->receipt_number);
        $this->inPanel($this->owner, ShopOrders::class)->mountTableAction('details', $order)->assertMountedActionModalSee('Kadıköy');
    }

    public function test_wrong_amounts_declines_and_abandoned_checkouts_never_make_an_order_paid(): void
    {
        $this->add(1);
        $wrong = $this->start();
        $this->gateway->paid($wrong->provider_token, $wrong->public_id, 100);
        $this->post('/billing/iyzico/callback', ['token' => $wrong->provider_token]);
        $this->assertSame(ShopOrderStatus::PaymentFailed, $this->order()->status);

        $declined = $this->start();
        $this->gateway->declined($declined->provider_token, $declined->public_id);
        $this->post('/billing/iyzico/callback', ['token' => $declined->provider_token]);
        $this->assertSame(ShopOrderStatus::PaymentFailed, $this->order()->status);

        $abandoned = $this->start();
        $this->travel(3)->hours();
        $this->artisan('billing:reconcile-payments')->assertSuccessful();
        $this->assertSame(PaymentStatus::Expired, $this->inTenant($this->tenant, fn () => Payment::query()->findOrFail($abandoned->getKey())->status));
        $this->assertSame(ShopOrderStatus::PaymentFailed, $this->order()->status);
        $this->assertDatabaseCount('shop_cart_items', 1);
        Mail::assertNothingQueued();

        // Money that arrives after the expiry is still recorded.
        $this->gateway->paid($abandoned->provider_token, $abandoned->public_id, $abandoned->amount);
        $this->inTenant($this->tenant, fn () => app(PaymentProcessor::class)->complete(Payment::query()->findOrFail($abandoned->getKey())));
        $this->assertSame(ShopOrderStatus::Paid, $this->order()->status);
    }

    public function test_the_admin_fulfils_an_order_and_every_step_is_audited(): void
    {
        $order = $this->paidOrder();
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->get('/admin/shop-orders')->assertOk()->assertSee($order->order_number);
        Filament::setCurrentPanel('admin');
        app(TenantContext::class)->clear();

        Livewire::test(ListShopOrders::class)
            ->mountTableAction('details', $order->getKey())
            ->assertMountedActionModalSee('Moda Cad. No: 12 Kat: 1')
            ->assertMountedActionModalSee($this->code->code);
        Livewire::test(ListShopOrders::class)
            ->callTableAction('qrFiles', $order->getKey())
            ->assertFileDownloaded("{$order->order_number}-qr.zip");
        Livewire::test(ListShopOrders::class)->callTableAction('prepare', $order->getKey())->assertHasNoTableActionErrors();
        Livewire::test(ListShopOrders::class)
            ->callTableAction('ship', $order->getKey(), ['carrier' => 'Yurtiçi', 'tracking_number' => '123', 'tracking_url' => 'javascript:alert(1)'])
            ->assertHasTableActionErrors(['tracking_url']);
        Livewire::test(ListShopOrders::class)
            ->callTableAction('ship', $order->getKey(), ['carrier' => 'Yurtiçi', 'tracking_number' => '123', 'tracking_url' => 'https://kargo.example/123'])
            ->assertHasNoTableActionErrors()
            ->assertTableActionHidden('cancel', $order->getKey());
        Livewire::test(ListShopOrders::class)->callTableAction('deliver', $order->getKey())->assertHasNoTableActionErrors();

        $order = $this->order();
        $this->assertSame(ShopOrderStatus::Delivered, $order->status);
        $this->assertSame('123', $order->tracking_number);
        $this->assertFalse(app(TenantContext::class)->check(), 'The admin request keeps no tenant context.');
        $this->assertSame(
            ['shop_order.preparing', 'shop_order.shipped', 'shop_order.delivered'],
            AdminAuditEvent::query()->orderBy('id')->get()->map(fn ($event) => $event->action->value)->all(),
        );
        Mail::assertQueued(ShopOrderMail::class, fn (ShopOrderMail $mail): bool => $mail->status === ShopOrderStatus::Shipped && str_contains($mail->render(), 'https://kargo.example/123'));
    }

    public function test_cancelling_returns_stock_and_the_refund_is_recorded_separately(): void
    {
        $this->stand->update(['stock_quantity' => 10]);
        $this->app->forgetInstance(ShopCatalog::class);
        $order = $this->paidOrder();
        $this->actingAs(User::factory()->superAdmin()->create());
        Filament::setCurrentPanel('admin');

        Livewire::test(ListShopOrders::class)
            ->assertTableActionHidden('refunded', $order->getKey())
            ->callTableAction('cancel', $order->getKey(), ['reason' => 'Ürün <b>hasarlı</b>']);
        $this->assertSame(10, $this->stand->fresh()->stock_quantity);
        Livewire::test(ListShopOrders::class)->callTableAction('refunded', $order->getKey());

        $this->assertSame(ShopOrderStatus::Cancelled, $this->order()->status);
        $this->assertNotNull($this->order()->refunded_at);
        Mail::assertQueued(ShopOrderMail::class, fn (ShopOrderMail $mail): bool => $mail->status === ShopOrderStatus::Cancelled && str_contains($mail->render(), '&lt;b&gt;'));
    }

    public function test_platform_staff_only_read_orders(): void
    {
        $order = $this->paidOrder();
        $this->actingAs(User::factory()->platformStaff()->create())->get('/admin/shop-orders')->assertOk();
        Filament::setCurrentPanel('admin');

        Livewire::test(ListShopOrders::class)
            ->assertTableActionVisible('details', $order->getKey())
            ->assertTableActionHidden('prepare', $order->getKey())
            ->assertTableActionHidden('ship', $order->getKey())
            ->assertTableActionHidden('cancel', $order->getKey());
        $this->actingAs($this->owner)->get('/admin/shop-orders')->assertForbidden();
    }

    public function test_orders_are_isolated_and_survive_a_deleted_code(): void
    {
        $order = $this->paidOrder();
        $other = $this->subscribedTenant('baska');
        $this->assertNull($this->inTenant($other, fn () => ShopOrder::query()->find($order->getKey())));
        $payment = $this->inTenant($this->tenant, fn () => Payment::query()->sole());
        $this->actingAs($this->ownerOf($other))->get('/app/baska/subscription/receipt?payment='.$payment->public_id)->assertNotFound();

        $this->inTenant($this->tenant, fn () => $this->code->delete());
        $item = $this->inTenant($this->tenant, fn () => ShopOrderItem::query()->sole());
        $this->assertNull($item->qr_code_id);
        $this->assertSame($this->code->code, $item->qr_code);
    }

    public function test_stands_picked_at_registration_wait_in_the_cart(): void
    {
        Notification::fake();
        Plan::factory()->create(['code' => config('subscriptions.default_plan')]);
        $payload = ['name' => 'Can', 'email' => 'can@example.com', 'password' => 'a-Strong-passw0rd', 'password_confirmation' => 'a-Strong-passw0rd', 'business_name' => 'Can Kafe', 'slug' => 'can-kafe', 'terms' => '1'];

        $this->get('/register?stand='.$this->stand->public_id)->assertOk()->assertSee("stands[{$this->stand->public_id}]", false);
        $this->post('/register', [...$payload, 'stands' => [$this->stand->public_id => '4', 'bogus' => '3']])
            ->assertRedirect('http://localhost/app/can-kafe/shop/checkout');

        $tenant = Tenant::query()->where('slug', 'can-kafe')->sole();
        $this->inTenant($tenant, function (): void {
            $item = ShopCartItem::query()->sole();
            $this->assertSame(4, $item->quantity);
            $this->assertSame(QrCode::query()->sole()->getKey(), $item->qr_code_id);
        });
        $this->get('/app/can-kafe/shop/checkout')->assertRedirect(route('verification.notice'));
    }

    public function test_bad_registration_selections_are_ignored_and_pay_now_keeps_its_redirect(): void
    {
        Notification::fake();
        Plan::factory()->create(['code' => config('subscriptions.default_plan')]);
        $payload = fn (string $slug): array => ['name' => 'Can', 'email' => "{$slug}@example.com", 'password' => 'a-Strong-passw0rd', 'password_confirmation' => 'a-Strong-passw0rd', 'business_name' => 'Can Kafe', 'slug' => $slug, 'terms' => '1'];

        $this->post('/register', [...$payload('bir-kafe'), 'stands' => [$this->stand->public_id => ['x'], 'a' => '-1']])->assertRedirect('/app');
        $this->post('/logout');
        $this->post('/register', [...$payload('iki-kafe'), 'start' => 'pay', 'stands' => [$this->stand->public_id => '2']])
            ->assertRedirect('http://localhost/app/iki-kafe/subscription/checkout');
        $this->assertDatabaseCount('shop_cart_items', 1);
    }

    public function test_the_marketing_page_sells_only_active_products_of_an_open_shop(): void
    {
        $hidden = $this->product(['is_active' => false]);
        $hidden->translations()->update(['name' => 'Gizli stand']);

        $this->get('/qr-stand')->assertOk()->assertSee('Ahşap stand')->assertDontSee('Gizli stand')
            ->assertSee('/register?stand='.$this->stand->public_id, false);
        $this->get('/en/qr-stands')->assertOk();

        $this->settings(['is_enabled' => false]);
        $this->get('/qr-stand')->assertOk()->assertDontSee('Ahşap stand')->assertSee('noindex', false);
        $this->get('/register')->assertOk()->assertDontSee('stands[', false);
    }
}
