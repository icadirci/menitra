{{-- Product detail (PremiumDetailLodos): picture on the accent colour with a wave edge, serif title and price. --}}
<div class="menu-sheet s-sheet" role="dialog" x-data="menuItem({{ $theme->cartEnabled() ? \Illuminate\Support\Js::from($item->cartPayload()) : 'null' }})" aria-modal="true" aria-labelledby="sheet-title-{{ $item->publicId }}"
     x-show="open === '{{ $item->publicId }}'" x-cloak x-transition.opacity tabindex="-1" x-ref="dlg-{{ $item->publicId }}">
    <div class="menu-sheet__inner">
        <div class="s-sheet__media {{ $item->imageUrl ? '' : 's-sheet__media--plain' }}">
            @if ($item->imageUrl)
                <img src="{{ $item->imageUrl }}" alt="{{ $item->name }}" width="560" height="560" loading="lazy" decoding="async">
            @endif
            <button type="button" class="s-round" @click="close()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="menu-flip-rtl" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"></path></svg>
                <span class="menu-sr">{{ __('menu.back_to_menu') }}</span>
            </button>
            <svg class="s-wave" aria-hidden="true" viewBox="0 0 390 30" preserveAspectRatio="none"><path d="M0 16c24-12 41-12 65 0s41 12 65 0 41-12 65 0 41 12 65 0 41-12 65 0 41 12 65 0V30H0z"></path></svg>
        </div>
        <div class="s-sheet__body">
            <div class="s-sheet__head">
                <span class="s-item__meta">
                    <span class="s-muted">{{ $category->name }}</span>
                    <x-menu.item-status :item="$item" :live="$live" class="s-status" />
                </span>
                <div class="s-sheet__title">
                    <h2 id="sheet-title-{{ $item->publicId }}">{{ $item->name }}</h2>
                    <x-menu.price :item="$item" class="menu-price s-price s-price--lg" />
                </div>
                @if ($item->description)
                    <p>{{ $item->description }}</p>
                @endif
            </div>
            <x-menu.item-details :item="$item" :selectable="$theme->cartEnabled()" class="s-detail" />
        </div>
        @if ($theme->cartEnabled())
            <x-menu.cart-add :item="$item" :live="$live" />
        @endif
    </div>
</div>
