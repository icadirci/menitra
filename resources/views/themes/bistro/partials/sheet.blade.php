{{-- Product detail sheet (DetailArdoise), client-side over rendered data. --}}
<div class="menu-sheet b-sheet" role="dialog" x-data="menuItem({{ $theme->cartEnabled() ? \Illuminate\Support\Js::from($item->cartPayload()) : 'null' }})" aria-modal="true" aria-labelledby="sheet-title-{{ $item->publicId }}"
     x-show="open === '{{ $item->publicId }}'" x-cloak x-transition.opacity tabindex="-1" x-ref="dlg-{{ $item->publicId }}">
    <div class="menu-sheet__inner">
        <div class="b-sheet__bar">
            <button type="button" class="b-back" @click="close()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="menu-flip-rtl" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"></path></svg>
                {{ __('menu.back_to_menu') }}
            </button>
            <span class="b-label b-label--muted">{{ $category->name }}</span>
        </div>
        @if ($item->imageUrl)
            <div class="b-cover b-cover--detail">
                <img src="{{ $item->imageUrl }}" alt="{{ $item->name }}" width="520" height="520" loading="lazy" decoding="async">
                <span class="b-frame" aria-hidden="true"></span>
            </div>
        @endif
        <div class="b-sheet__body">
            <div class="b-sheet__head">
                @if ($item->isFeatured)
                    <span class="b-label">{{ __('menu.featured') }}</span>
                @endif
                <h2 id="sheet-title-{{ $item->publicId }}">{{ $item->name }}</h2>
                <x-menu.price :item="$item" class="menu-price b-price b-price--lg" />
                <span class="b-tags b-tags--center"><x-menu.item-status :item="$item" :live="$live" class="b-status" /></span>
                @if ($item->description)
                    <p class="b-italic">{{ $item->description }}</p>
                @endif
            </div>
            <x-menu.item-details :item="$item" :selectable="$theme->cartEnabled()" class="b-detail" />
        </div>
        @if ($theme->cartEnabled())
            <x-menu.cart-add :item="$item" :live="$live" />
        @endif
    </div>
</div>
