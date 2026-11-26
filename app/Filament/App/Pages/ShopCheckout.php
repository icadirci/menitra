<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Enums\PaymentMethod;
use App\Exceptions\CheckoutNotAllowedException;
use App\Filament\App\Support\PanelContext;
use App\Filament\App\Support\ServiceCall;
use App\Models\Payment;
use App\Models\TenantBillingProfile;
use App\Services\Billing\BankTransferService;
use App\Services\Billing\BillingProfileService;
use App\Services\Billing\PaymentMethods;
use App\Services\Shop\DeliveryAddress;
use App\Services\Shop\ShopCart;
use App\Services\Shop\ShopCatalog;
use App\Services\Shop\ShopCheckoutService;
use App\Services\Shop\ShopQuote;
use App\Support\MarketingUrl;
use App\Support\TurkishProvinces;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Cart, delivery address and payment of a shop order. Everything shown is
 * recomputed by ShopCheckoutService; the total the owner saw is passed along
 * and a different one is refused. Owner-only, checked again in the services.
 */
class ShopCheckout extends Page
{
    protected static ?string $slug = 'shop/checkout';

    protected static bool $shouldRegisterNavigation = false;

    protected Width|string|null $maxContentWidth = Width::SevenExtraLarge;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** The total on the owner's screen; only ever compared with the server's own. */
    public int $shownTotal = 0;

    private TenantBillingProfile|false|null $profile = false;

    /** @var list<PaymentMethod>|null */
    private ?array $methods = null;

    private Payment|false|null $openTransfer = false;

    public static function canAccess(): bool
    {
        return Shop::canAccess();
    }

    public function getTitle(): string
    {
        return __('shop.checkout.title');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('shop.actions.back_to_shop'))
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(Shop::getUrl()),
        ];
    }

    public function mount(): void
    {
        $this->form->fill([...app(DeliveryAddress::class)->prefill(), 'note' => null, 'method' => ($this->methods()[0] ?? PaymentMethod::Card)->value, 'terms' => false]);
        $this->shownTotal = $this->quote()->total->minor;
    }

    public function form(Schema $schema): Schema
    {
        $openTransfer = $this->openTransfer();
        $payable = app(ShopCatalog::class)->isOpen() && ! $this->quote()->isEmpty() && $this->methods() !== [] && $openTransfer === null;

        return $schema
            ->statePath('data')
            ->components([
                Callout::make(__('billing.bank_transfer.open.title'))
                    ->description(__('billing.bank_transfer.open.body_shop', ['reference' => $openTransfer?->transfer_reference]))
                    ->warning()
                    ->columnSpanFull()
                    ->actions([
                        Action::make('openTransfer')
                            ->label(__('billing.bank_transfer.open.action'))
                            ->url(fn (): ?string => $openTransfer !== null ? BankTransferPayment::getUrl(['payment' => $openTransfer->public_id]) : null),
                    ])
                    ->visible($openTransfer !== null),
                Callout::make(__('billing.errors.method_unavailable'))
                    ->warning()
                    ->columnSpanFull()
                    ->visible($openTransfer === null && $this->methods() === []),
                Callout::make(app(ShopCatalog::class)->isOpen() ? __('shop.checkout.empty') : __('billing.errors.shop_closed'))
                    ->info()
                    ->columnSpanFull()
                    ->visible(! app(ShopCatalog::class)->isOpen() || $this->quote()->isEmpty()),
                Grid::make(['default' => 1, 'lg' => 3])
                    ->columnSpanFull()
                    ->visible($payable)
                    ->schema([
                        Group::make([
                            Section::make(__('shop.checkout.cart'))
                                ->icon(Heroicon::OutlinedShoppingCart)
                                ->schema([
                                    View::make('filament.app.shop.cart')
                                        ->viewData(fn (): array => ['items' => $this->quote()->items]),
                                ]),
                            $this->addressSection(),
                            $this->billingSection(),
                        ])->columnSpan(['default' => 1, 'lg' => 2]),
                        Group::make([$this->summarySection()])->columnSpan(['default' => 1, 'lg' => 1]),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('pay'),
        ]);
    }

    public function removeItem(int $id): void
    {
        abort_unless(static::canAccess(), 403);

        app(ShopCart::class)->remove(PanelContext::tenant(), PanelContext::user(), $id);
        $this->shownTotal = $this->quote()->total->minor;
    }

    /**
     * Runs outside any database transaction (HTTP call to the provider).
     */
    public function pay(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        try {
            $url = ServiceCall::run(fn (): string => app(ShopCheckoutService::class)->start(
                PanelContext::tenant(),
                PanelContext::user(),
                $data,
                $data['note'] ?? null,
                $this->shownTotal,
                (string) request()->ip(),
                app()->getLocale(),
                PaymentMethod::tryFrom((string) ($data['method'] ?? '')) ?? ($this->methods()[0] ?? PaymentMethod::Card),
            ));
        } catch (CheckoutNotAllowedException $exception) {
            $this->shownTotal = $this->quote()->total->minor;
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        $this->redirect($url);
    }

    private function addressSection(): Section
    {
        return Section::make(__('shop.checkout.address'))
            ->icon(Heroicon::OutlinedTruck)
            ->description(__('shop.checkout.address_help'))
            ->columns(['default' => 1, 'md' => 2])
            ->headerActions([
                Action::make('useBilling')
                    ->label(__('shop.actions.use_billing'))
                    ->link()
                    ->visible($this->profile() !== null)
                    ->action(function (): void {
                        $this->data = [...($this->data ?? []), ...app(DeliveryAddress::class)->fromBilling()];
                    }),
            ])
            ->schema([
                TextInput::make('recipient')->label(__('shop.address.recipient'))->required()->minLength(3)->maxLength(120),
                TextInput::make('phone')->label(__('shop.address.phone'))->tel()->placeholder('05xx xxx xx xx')->required()->maxLength(32),
                Textarea::make('address')->label(__('shop.address.address'))->required()->rows(3)->minLength(10)->maxLength(400)->columnSpanFull(),
                Select::make('city')
                    ->label(__('shop.address.city'))
                    ->options(array_combine(TurkishProvinces::ALL, TurkishProvinces::ALL))
                    ->searchable()
                    ->required(),
                TextInput::make('district')->label(__('shop.address.district'))->required()->maxLength(80),
                TextInput::make('postal_code')->label(__('shop.address.postal_code'))->inputMode('numeric')->maxLength(5),
                Textarea::make('note')->label(__('shop.checkout.note'))->rows(2)->maxLength(500)->columnSpanFull(),
            ]);
    }

    private function billingSection(): Section
    {
        $profile = $this->profile();

        return Section::make(__('billing.checkout.sections.billing'))
            ->icon(Heroicon::OutlinedIdentification)
            ->headerActions([
                Action::make('editBilling')
                    ->label($profile === null ? __('billing.checkout.billing.add') : __('billing.checkout.billing.edit'))
                    ->link()
                    ->url(ManageBillingProfile::getUrl(['return' => 'shop'])),
            ])
            ->schema($profile === null ? [
                Callout::make(__('billing.checkout.billing.missing'))->warning(),
            ] : [
                TextEntry::make('billing_name')->label($profile->type->label())->state($profile->name),
                TextEntry::make('billing_address')
                    ->label(__('billing.profile.fields.address'))
                    ->state(collect([$profile->address, $profile->district, $profile->city])->filter()->implode(', ')),
            ]);
    }

    private function summarySection(): Section
    {
        return Section::make(__('billing.checkout.sections.summary'))
            ->icon(Heroicon::OutlinedReceiptPercent)
            ->schema([
                TextEntry::make('summary_subtotal')
                    ->label(__('shop.checkout.subtotal'))
                    ->inlineLabel()
                    ->state(fn (): string => $this->quote()->subtotal->format()),
                TextEntry::make('summary_shipping')
                    ->label(__('shop.checkout.shipping'))
                    ->inlineLabel()
                    ->state(fn (): string => $this->quote()->shipping->isZero() ? __('shop.free') : $this->quote()->shipping->format()),
                TextEntry::make('summary_total')
                    ->label(__('shop.checkout.total'))
                    ->inlineLabel()
                    ->state(fn (): string => $this->quote()->total->format())
                    ->helperText(__('billing.checkout.summary.vat_included'))
                    ->size(TextSize::Large)
                    ->weight(FontWeight::Bold),
                Text::make(fn (): ?string => app(ShopCatalog::class)->settings()->deliveryText())
                    ->icon(Heroicon::OutlinedTruck)
                    ->visible(app(ShopCatalog::class)->settings()->deliveryText() !== null),
                Checkout::methodField($this->methods()),
                Checkbox::make('terms')
                    ->label(new HtmlString(e(__('shop.checkout.terms')).' <a href="'.e(MarketingUrl::to('terms')).'" target="_blank" rel="noopener" class="underline">'.e(__('billing.checkout.fields.terms_link')).'</a>'))
                    ->accepted()
                    ->validationMessages(['accepted' => __('billing.checkout.fields.terms_required')]),
                Actions::make([
                    Action::make('pay')
                        ->label(fn (Get $get): string => Checkout::isTransfer($get)
                            ? __('billing.bank_transfer.pay_amount', ['amount' => $this->quote()->total->format()])
                            : __('billing.checkout.pay_amount', ['amount' => $this->quote()->total->format()]))
                        ->icon(fn (Get $get): Heroicon => Checkout::isTransfer($get) ? Heroicon::BuildingLibrary : Heroicon::LockClosed)
                        ->size('lg')
                        ->disabled($this->profile() === null)
                        ->submit('pay'),
                ])->fullWidth(),
                Text::make(__('billing.checkout.trust.secure'))->icon(Heroicon::OutlinedShieldCheck)->color('gray')->size(TextSize::Small)
                    ->visible(fn (Get $get): bool => ! Checkout::isTransfer($get)),
                Text::make(__('billing.checkout.trust.card'))->icon(Heroicon::OutlinedCreditCard)->color('gray')->size(TextSize::Small)
                    ->visible(fn (Get $get): bool => ! Checkout::isTransfer($get)),
                Text::make(__('billing.bank_transfer.trust'))->icon(Heroicon::OutlinedBuildingLibrary)->color('gray')->size(TextSize::Small)
                    ->visible(fn (Get $get): bool => Checkout::isTransfer($get)),
            ]);
    }

    /**
     * @return list<PaymentMethod>
     */
    private function methods(): array
    {
        return $this->methods ??= app(PaymentMethods::class)->enabled();
    }

    private function openTransfer(): ?Payment
    {
        if ($this->openTransfer === false) {
            $this->openTransfer = app(BankTransferService::class)->open(shop: true);
        }

        return $this->openTransfer;
    }

    private function quote(): ShopQuote
    {
        return app(ShopCart::class)->quote();
    }

    private function profile(): ?TenantBillingProfile
    {
        return $this->profile === false ? $this->profile = app(BillingProfileService::class)->current() : $this->profile;
    }
}
