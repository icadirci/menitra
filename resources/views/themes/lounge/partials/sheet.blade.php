{{-- Product detail (PremiumDetailLumen): picture under a sunburst, centred title with the price, details. --}}
<div class="menu-sheet l-sheet" role="dialog" x-data="menuItem({{ $theme->cartEnabled() ? \Illuminate\Support\Js::from($item->cartPayload()) : 'null' }})" aria-modal="true" aria-labelledby="sheet-title-{{ $item->publicId }}"
     x-show="open === '{{ $item->publicId }}'" x-cloak x-transition.opacity tabindex="-1" x-ref="dlg-{{ $item->publicId }}">
    <div class="menu-sheet__inner">
        <div class="l-sheet__media">
            <svg class="l-burst" aria-hidden="true" viewBox="0 0 390 400" preserveAspectRatio="xMidYMax slice"><g class="l-burst__rays"><path d="M195 400L0 120M195 400L40 0M195 400L120 0M195 400L195 0M195 400L270 0M195 400L350 0M195 400L390 120"></path></g></svg>
            <div class="l-sheet__bar">
                <button type="button" class="l-back" @click="close()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="menu-flip-rtl" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"></path></svg>
                    <span class="menu-sr">{{ __('menu.back_to_menu') }}</span>
                </button>
                <span class="l-label">{{ $category->name }}</span>
                <span class="l-sheet__spacer" aria-hidden="true"></span>
            </div>
            @if ($item->imageUrl)
                <div class="l-sheet__pic">
                    <img src="{{ $item->imageUrl }}" alt="{{ $item->name }}" width="250" height="333" loading="lazy" decoding="async">
                </div>
            @endif
        </div>
        <div class="l-sheet__body">
            <div class="l-sheet__head">
                <span class="l-row__meta">
                    <x-menu.item-status :item="$item" :live="$live" class="l-status-badge" />
                </span>
                <h2 id="sheet-title-{{ $item->publicId }}">{{ $item->name }}</h2>
                <x-menu.price :item="$item" class="menu-price l-price l-price--lg" />
                @if ($item->description)
                    <p>{{ $item->description }}</p>
                @endif
            </div>
            <x-menu.item-details :item="$item" :selectable="$theme->cartEnabled()" class="l-detail" />
        </div>
        @if ($theme->cartEnabled())
            <x-menu.cart-add :item="$item" :live="$live" />
        @endif
    </div>
</div>
