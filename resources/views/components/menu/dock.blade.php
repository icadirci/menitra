{{--
    Bottom dock of a menu page, shared by every theme (inside menuPage). In the
    thumb zone, it appears once the guest has scrolled (or the order list has
    items) and offers the way back for the current context: all categories (in
    a category card), clearing the search, or the branch's menus; plus "back to
    top" and, with the `cart` option, the order list. Also renders the order
    list sheet and the toast. The phone's back button does the same (menu.js).
--}}
@props(['menu', 'cart' => false])

<div class="menu-dock" x-show="open === null && (scrolled{{ $cart ? ' || $store.cart.count > 0' : '' }})" x-cloak x-transition.opacity>
    <div class="menu-dock__bar">
        <button type="button" class="menu-dock__btn" x-show="scrolled && cat !== null" @click="closeCategory()">
            <span class="menu-flip-rtl" aria-hidden="true">←</span>
            <span>{{ __('menu.all_categories') }}</span>
        </button>
        <button type="button" class="menu-dock__btn" x-show="scrolled && cat === null && query !== ''" @click="clearSearch()">
            <span aria-hidden="true">×</span>
            <span>{{ __('menu.clear_search') }}</span>
        </button>
        @if (count($menu->menus) > 1)
            <a class="menu-dock__btn" href="{{ $menu->location->url }}?lang={{ $menu->locale }}" x-show="scrolled && cat === null && query === ''">
                <span class="menu-flip-rtl" aria-hidden="true">←</span>
                <span>{{ __('menu.menus') }}</span>
            </a>
        @endif

        @if ($cart)
            <button type="button" id="menu-cart-button" class="menu-dock__cart" x-show="$store.cart.count > 0" @click="showCart()" aria-haspopup="dialog">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 7h12l-1 13H7L6 7zM9 7a3 3 0 0 1 6 0"></path></svg>
                <span>{{ __('menu.cart') }}</span>
                <span class="menu-dock__count" x-text="$store.cart.count"></span>
                <span class="menu-price menu-dock__total" x-show="$store.cart.total > 0" x-text="$store.cart.format($store.cart.total)"></span>
            </button>
        @endif

        <button type="button" class="menu-dock__btn menu-dock__btn--icon" x-show="scrolled" @click="toTop()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M6 11l6-6 6 6"></path></svg>
            <span class="menu-sr">{{ __('menu.back_to_top') }}</span>
        </button>
    </div>
</div>

<div class="menu-toast" role="status" aria-live="polite">
    <template x-if="toast">
        <div class="menu-toast__box">
            <span x-text="toast.message"></span>
            <button type="button" x-show="toast.undo" @click="undoOrdered()">{{ __('menu.cart_undo') }}</button>
        </div>
    </template>
</div>

@if ($cart)
    <div class="menu-sheet menu-cart" role="dialog" aria-modal="true" aria-labelledby="menu-cart-title"
         x-show="open === 'cart'" x-cloak x-transition.opacity tabindex="-1" x-ref="dlg-cart">
        <div class="menu-sheet__inner menu-cart__inner">
            <div class="menu-cart__bar">
                <button type="button" class="menu-cart__back" @click="close()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="menu-flip-rtl" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"></path></svg>
                    {{ __('menu.back_to_menu') }}
                </button>
                <h2 id="menu-cart-title">{{ __('menu.cart') }}</h2>
                <span aria-hidden="true"></span>
            </div>

            <p class="menu-cart__note">{{ __('menu.cart_note') }}</p>

            <p class="menu-cart__empty" x-show="$store.cart.lines.length === 0">{{ __('menu.cart_empty') }}</p>

            <ul class="menu-cart__lines" x-show="$store.cart.lines.length > 0">
                <template x-for="line in $store.cart.lines" :key="line.key">
                    <li class="menu-cart__line">
                        <span class="menu-cart__info">
                            <strong x-text="line.name"></strong>
                            <small x-show="line.variant || line.extras.length" x-text="[line.variant, ...line.extras].filter(Boolean).join(' · ')"></small>
                            <span class="menu-price" x-show="line.unit !== null" x-text="line.unit === null ? '' : $store.cart.format(line.unit * line.qty)"></span>
                        </span>
                        <span class="menu-qty" role="group" aria-label="{{ __('menu.cart_quantity') }}">
                            <button type="button" @click="$store.cart.change(line.key, -1)">
                                <span aria-hidden="true">−</span><span class="menu-sr">{{ __('menu.cart_decrease') }}</span>
                            </button>
                            <span x-text="line.qty"></span>
                            <button type="button" @click="$store.cart.change(line.key, 1)" :disabled="line.qty >= 99">
                                <span aria-hidden="true">+</span><span class="menu-sr">{{ __('menu.cart_increase') }}</span>
                            </button>
                        </span>
                    </li>
                </template>
            </ul>

            <div class="menu-cart__footer" x-show="$store.cart.lines.length > 0">
                <div class="menu-cart__total" x-show="$store.cart.total > 0">
                    <span>{{ __('menu.cart_total') }}</span>
                    <strong class="menu-price" x-text="$store.cart.format($store.cart.total)"></strong>
                </div>
                <p class="menu-cart__hint" x-show="$store.cart.hasUnpriced">{{ __('menu.cart_unpriced') }}</p>
                <button type="button" class="menu-cart__done" @click="ordered(@js(__('menu.cart_ordered')))">{{ __('menu.cart_done') }}</button>
            </div>
        </div>
    </div>
@endif
