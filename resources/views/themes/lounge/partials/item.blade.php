{{-- One row with a tall (3:4) picture, name and gold price, description, labels (PremiumLumen). --}}
<li data-item x-show="matches($el.dataset.search)" data-search="{{ mb_strtolower($item->name.' '.$item->description) }}">
    <button type="button" id="row-{{ $item->publicId }}" class="l-row {{ $live->isSoldOut($item) || ! $item->isAvailable ? 'is-muted' : '' }}"
            @click="show('{{ $item->publicId }}')" aria-haspopup="dialog">
        @if ($theme->option('show_images') !== false && $item->imageUrl)
            <span class="l-row__pic">
                <img src="{{ $item->imageUrl }}" alt="" width="68" height="91" loading="lazy" decoding="async">
            </span>
        @endif
        <span class="l-row__body">
            <span class="l-row__top">
                <span class="l-row__name">{{ $item->name }}</span>
                <x-menu.price :item="$item" class="menu-price l-price" />
            </span>
            @if ($item->description)
                <span class="l-row__desc l-clamp">{{ $item->description }}</span>
            @endif
            <span class="l-row__meta">
                @if ($item->dietaryLabels !== [])
                    <span class="l-tags">{{ collect($item->dietaryLabels)->pluck('label')->implode(' · ') }}</span>
                @endif
                @if ($item->isPopular)
                    <span class="l-badge">{{ __('menu.popular') }}</span>
                @endif
                <x-menu.item-status :item="$item" :live="$live" :popular="false" class="l-status-badge" />
            </span>
        </span>
    </button>
</li>
