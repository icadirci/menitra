{{-- One product row (MenuNoon list); opens the detail sheet. --}}
<li data-item x-show="matches($el.dataset.search)" data-search="{{ mb_strtolower($item->name.' '.$item->description) }}">
    <button type="button" id="row-{{ $item->publicId }}" class="n-item {{ $live->isSoldOut($item) || ! $item->isAvailable ? 'is-muted' : '' }}"
            @click="show('{{ $item->publicId }}')" aria-haspopup="dialog">
        @if ($theme->option('show_images') !== false && $item->imageUrl)
            <img class="n-thumb" src="{{ $item->imageUrl }}" alt="" width="52" height="52" loading="lazy" decoding="async">
        @endif
        <span class="n-item__body">
            <span class="n-item__name">
                {{ $item->name }}
                @if ($item->isPopular)
                    <span class="n-flag"><span class="n-flag__dot" aria-hidden="true"></span>{{ __('menu.popular') }}</span>
                @endif
            </span>
            @if ($item->description)
                <span class="n-item__desc">{{ $item->description }}</span>
            @endif
            @if ($item->dietaryLabels !== [] || $live->isSoldOut($item) || ! $item->isAvailable)
                <span class="n-tags">
                    @foreach ($item->dietaryLabels as $label)
                        <span class="n-diet">{{ $label->label }}</span>
                    @endforeach
                    <x-menu.item-status :item="$item" :live="$live" :popular="false" class="n-status" />
                </span>
            @endif
        </span>
        <x-menu.price :item="$item" class="menu-price n-price" />
    </button>
</li>
