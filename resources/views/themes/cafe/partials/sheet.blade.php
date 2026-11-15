{{-- Product detail sheet (DetailKiln): picture with a rounded sheet over it. --}}
<div class="menu-sheet c-sheet" role="dialog" aria-modal="true" aria-labelledby="sheet-title-{{ $item->publicId }}"
     x-show="open === '{{ $item->publicId }}'" x-cloak x-transition.opacity tabindex="-1" x-ref="dlg-{{ $item->publicId }}">
    <div class="menu-sheet__inner">
        <div class="c-sheet__media {{ $item->imageUrl ? '' : 'c-sheet__media--plain' }}">
            @if ($item->imageUrl)
                <img src="{{ $item->imageUrl }}" alt="{{ $item->name }}" width="560" height="560" loading="lazy" decoding="async">
            @endif
            <button type="button" class="c-round" @click="close()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"></path></svg>
                <span class="menu-sr">{{ __('menu.back_to_menu') }}</span>
            </button>
        </div>
        <div class="c-sheet__body">
            <span class="c-handle" aria-hidden="true"></span>
            <div class="c-sheet__head">
                <span class="c-card__meta">
                    <x-menu.item-status :item="$item" :live="$live" class="c-status" />
                    <span class="c-muted">{{ $category->name }}</span>
                </span>
                <div class="c-sheet__title">
                    <h2 id="sheet-title-{{ $item->publicId }}">{{ $item->name }}</h2>
                    <x-menu.price :item="$item" class="menu-price c-price c-price--lg" />
                </div>
                @if ($item->description)
                    <p class="c-muted">{{ $item->description }}</p>
                @endif
            </div>
            <x-menu.item-details :item="$item" class="c-detail" />
        </div>
    </div>
</div>
