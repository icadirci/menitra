{{-- Product detail sheet (DetailNoon), client-side over rendered data. --}}
<div class="menu-sheet n-sheet" role="dialog" aria-modal="true" aria-labelledby="sheet-title-{{ $item->publicId }}"
     x-show="open === '{{ $item->publicId }}'" x-cloak x-transition.opacity tabindex="-1" x-ref="dlg-{{ $item->publicId }}">
    <div class="menu-sheet__inner">
        <div class="n-sheet__bar">
            <button type="button" class="n-back" @click="close()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"></path></svg>
                {{ __('menu.back_to_menu') }}
            </button>
            <span class="n-sheet__brand">{{ $menu->brand->name }}</span>
            <span class="n-sheet__spacer"></span>
        </div>
        <div class="n-sheet__body">
            @if ($item->imageUrl)
                <img class="n-sheet__pic" src="{{ $item->imageUrl }}" alt="{{ $item->name }}" width="520" height="390" loading="lazy" decoding="async">
            @endif
            <div class="n-sheet__head">
                <span class="n-kicker">{{ $category->name }}</span>
                <div class="n-sheet__title">
                    <h2 id="sheet-title-{{ $item->publicId }}">{{ $item->name }}</h2>
                    <x-menu.price :item="$item" class="menu-price n-price n-price--lg" />
                </div>
                <span class="n-tags">
                    <x-menu.item-status :item="$item" :live="$live" class="n-status" />
                </span>
                @if ($item->description)
                    <p>{{ $item->description }}</p>
                @endif
            </div>
            <x-menu.item-details :item="$item" class="n-detail" />
        </div>
    </div>
</div>
