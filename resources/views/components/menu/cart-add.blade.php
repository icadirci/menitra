{{--
    "Add to order list" bar at the bottom of a product sheet (theme option
    `cart`), shared by every theme. Lives inside the sheet's menuItem scope
    (variant, extras, quantity); sold-out or unavailable items get no bar.
--}}
@props(['item', 'live'])

@if ($item->isAvailable && ! $live->isSoldOut($item))
    <div {{ $attributes->merge(['class' => 'menu-cart-add']) }}>
        <p class="menu-cart-add__error" x-show="missing !== null" x-cloak role="alert">{{ __('menu.cart_choose_required') }}</p>
        <div class="menu-cart-add__row">
            <span class="menu-qty" role="group" aria-label="{{ __('menu.cart_quantity') }}">
                <button type="button" @click="qty = Math.max(1, qty - 1)" :disabled="qty <= 1">
                    <span aria-hidden="true">−</span><span class="menu-sr">{{ __('menu.cart_decrease') }}</span>
                </button>
                <span x-text="qty">1</span>
                <button type="button" @click="qty = Math.min(99, qty + 1)" :disabled="qty >= 99">
                    <span aria-hidden="true">+</span><span class="menu-sr">{{ __('menu.cart_increase') }}</span>
                </button>
            </span>
            <button type="button" class="menu-cart-add__button" @click="add(@js(__('menu.cart_added')))">
                <span>{{ __('menu.cart_add') }}</span>
                <span class="menu-price" x-show="unit !== null" x-text="unit === null ? '' : $store.cart.format(unit * qty)"></span>
            </button>
        </div>
    </div>
@endif
