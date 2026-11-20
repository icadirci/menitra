{{-- Product detail (PremiumDetailKori): back link, square picture, large serif title, facts. --}}
<div class="menu-sheet z-sheet" role="dialog" x-data="menuItem({{ $theme->cartEnabled() ? \Illuminate\Support\Js::from($item->cartPayload()) : 'null' }})" aria-modal="true" aria-labelledby="sheet-title-{{ $item->publicId }}"
     x-show="open === '{{ $item->publicId }}'" x-cloak x-transition.opacity tabindex="-1" x-ref="dlg-{{ $item->publicId }}">
    <div class="menu-sheet__inner">
        <div class="z-sheet__bar">
            <button type="button" class="z-back" @click="close()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="menu-flip-rtl" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"></path></svg>
                {{ __('menu.back_to_menu') }}
            </button>
            <span class="z-sheet__cat">{{ $category->name }}</span>
        </div>
        @if ($item->imageUrl)
            <div class="z-sheet__media">
                <img src="{{ $item->imageUrl }}" alt="{{ $item->name }}" width="520" height="520" loading="lazy" decoding="async">
            </div>
        @endif
        <div class="z-sheet__body">
            <div class="z-sheet__head">
                <span class="z-row__meta">
                    <x-menu.item-status :item="$item" :live="$live" class="z-status" />
                </span>
                <h2 id="sheet-title-{{ $item->publicId }}">{{ $item->name }}</h2>
                <x-menu.price :item="$item" class="menu-price z-price z-price--lg" />
                @if ($item->description)
                    <p>{{ $item->description }}</p>
                @endif
            </div>
            <x-menu.item-details :item="$item" :selectable="$theme->cartEnabled()" class="z-detail" />
        </div>
        @if ($theme->cartEnabled())
            <x-menu.cart-add :item="$item" :live="$live" />
        @endif
    </div>
</div>
