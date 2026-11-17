{{-- Product detail card (DetailPetal), client-side over rendered data. --}}
<div class="menu-sheet g-sheet" role="dialog" x-data="menuItem({{ $theme->cartEnabled() ? \Illuminate\Support\Js::from($item->cartPayload()) : 'null' }})" aria-modal="true" aria-labelledby="sheet-title-{{ $item->publicId }}"
     x-show="open === '{{ $item->publicId }}'" x-cloak x-transition.opacity tabindex="-1" x-ref="dlg-{{ $item->publicId }}">
    <div class="menu-sheet__inner g-sheet__inner">
        <div class="g-sheet__card">
            <div class="g-sheet__media">
                @include('themes.grid.partials.picture', ['url' => $item->imageUrl, 'label' => $item->name, 'alt' => $item->name, 'width' => 520, 'height' => 520])
                @if ($item->isPopular)
                    <span class="g-pill g-pill--badge">{{ __('menu.popular') }}</span>
                @endif
                <button type="button" class="g-close" @click="close()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"></path></svg>
                    <span class="menu-sr">{{ __('menu.back_to_menu') }}</span>
                </button>
            </div>
            <div class="g-sheet__body">
                <div class="g-sheet__head">
                    <div class="g-sheet__title">
                        <h2 id="sheet-title-{{ $item->publicId }}">{{ $item->name }}</h2>
                        <x-menu.price :item="$item" class="menu-price g-price g-price--lg" />
                    </div>
                    <span class="g-tags"><x-menu.item-status :item="$item" :live="$live" :popular="false" class="g-status" /><span class="g-muted">{{ $category->name }}</span></span>
                    @if ($item->description)
                        <p class="g-muted">{{ $item->description }}</p>
                    @endif
                </div>
                <x-menu.item-details :item="$item" :selectable="$theme->cartEnabled()" class="g-detail" />
            </div>
        </div>
        @if ($theme->cartEnabled())
            <x-menu.cart-add :item="$item" :live="$live" />
        @endif
    </div>
</div>
