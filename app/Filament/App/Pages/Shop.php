<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Filament\App\Navigation\AppNavigationGroup;
use App\Filament\App\Support\PanelContext;
use App\Filament\App\Support\ServiceCall;
use App\Models\QrCode;
use App\Models\ShopOrder;
use App\Models\ShopProduct;
use App\Services\Shop\ShopCart;
use App\Services\Shop\ShopCatalog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * QR stand shop: product cards and "add to cart" (docs/architecture/shop.md).
 * Owner-only (TenantPolicy::manageSettings), checked again in ShopCart.
 */
class Shop extends Page
{
    protected static string|UnitEnum|null $navigationGroup = AppNavigationGroup::Business;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?int $navigationSort = 45;

    protected static ?string $slug = 'shop';

    protected string $view = 'filament.app.pages.shop';

    public static function canAccess(): bool
    {
        return PanelContext::membership() !== null
            && (PanelContext::user()?->can('manageSettings', PanelContext::tenant()) ?? false);
    }

    /**
     * Hidden while the shop is closed, unless there are orders to look up.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess()
            && (app(ShopCatalog::class)->isOpen() || ShopOrder::query()->exists());
    }

    public static function getNavigationLabel(): string
    {
        return __('shop.title');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = app(ShopCart::class)->quantity();

        return $count > 0 ? (string) $count : null;
    }

    public function getTitle(): string
    {
        return __('shop.title');
    }

    public function getSubheading(): ?string
    {
        return __('shop.subheading');
    }

    protected function getHeaderActions(): array
    {
        $count = app(ShopCart::class)->quantity();

        return [
            Action::make('orders')
                ->label(__('shop.actions.orders'))
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->color('gray')
                ->url(ShopOrders::getUrl()),
            Action::make('cart')
                ->label(__('shop.actions.cart', ['count' => $count]))
                ->icon(Heroicon::OutlinedShoppingCart)
                ->disabled($count === 0)
                ->url(ShopCheckout::getUrl()),
        ];
    }

    public function detailsAction(): Action
    {
        return Action::make('details')
            ->label(__('shop.actions.details'))
            ->link()
            ->modalHeading(fn (array $arguments): string => $this->product($arguments)?->translate()?->name ?? __('shop.title'))
            ->modalWidth('2xl')
            ->modalContent(function (array $arguments) {
                $product = $this->product($arguments);
                abort_if($product === null, 404);

                return view('filament.app.pages.shop-product', ['product' => $product]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('shop.actions.close'))
            ->extraModalFooterActions(fn (array $arguments): array => $this->product($arguments)?->availableQuantity() > 0
                ? [
                    Action::make('addFromDetails')
                        ->label(__('shop.actions.add'))
                        ->icon(Heroicon::OutlinedPlus)
                        ->action(fn () => $this->replaceMountedAction('addToCart', ['product' => $arguments['product'] ?? null])),
                ]
                : []);
    }

    public function addToCartAction(): Action
    {
        return Action::make('addToCart')
            ->label(__('shop.actions.add'))
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading(fn (array $arguments): string => $this->product($arguments)?->translate()?->name ?? __('shop.title'))
            ->modalSubmitActionLabel(__('shop.actions.add'))
            ->modalWidth('lg')
            ->fillForm(fn (): array => [
                'codes' => QrCode::query()->where('is_active', true)->orderBy('id')->limit(1)->pluck('id')->all(),
                'quantity' => 1,
            ])
            ->schema(fn (array $arguments): array => [
                Select::make('codes')
                    ->label(__('shop.fields.codes'))
                    ->helperText(__('shop.fields.codes_help'))
                    ->multiple()
                    ->options(fn (): array => QrCode::query()->where('is_active', true)->orderBy('id')->get()
                        ->mapWithKeys(fn (QrCode $code): array => [$code->getKey() => trim(($code->label ?? '').' ('.$code->code.')')])->all())
                    ->required()
                    ->visible($this->product($arguments)?->is_personalized === true),
                TextInput::make('quantity')
                    ->label($this->product($arguments)?->is_personalized ? __('shop.fields.quantity_per_code') : __('shop.fields.quantity'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(max(1, (int) $this->product($arguments)?->availableQuantity()))
                    ->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                abort_unless(static::canAccess(), 403);

                $product = $this->product($arguments);
                abort_if($product === null, 404);

                ServiceCall::run(fn () => app(ShopCart::class)->add(
                    PanelContext::tenant(),
                    PanelContext::user(),
                    $product,
                    (int) $data['quantity'],
                    array_values($data['codes'] ?? []),
                ), statePath: null);

                Notification::make()->success()->title(__('shop.added'))
                    ->actions([Action::make('cart')->label(__('shop.actions.checkout'))->url(ShopCheckout::getUrl())])
                    ->send();
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $catalog = app(ShopCatalog::class);
        $settings = $catalog->settings();

        return [
            'products' => $catalog->products(),
            'notes' => array_values(array_filter([
                __('billing.checkout.summary.vat_included'),
                $settings->freeShippingFrom() !== null ? __('shop.free_shipping_from', ['amount' => $settings->freeShippingFrom()->format(omitZeroFraction: true)]) : null,
                $settings->deliveryText(),
            ])),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments  client-controlled; only products on sale are found
     */
    private function product(array $arguments): ?ShopProduct
    {
        return app(ShopCatalog::class)->find($arguments['product'] ?? null);
    }
}
