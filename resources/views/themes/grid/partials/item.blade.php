{{-- One photo card in the two-column grid (MenuPetal); opens the detail sheet. --}}
<li data-item x-show="matches($el.dataset.search)" data-search="{{ mb_strtolower($item->name.' '.$item->description) }}">
    <button type="button" id="row-{{ $item->publicId }}" class="g-card {{ $live->isSoldOut($item) || ! $item->isAvailable ? 'is-muted' : '' }}"
            @click="show('{{ $item->publicId }}')" aria-haspopup="dialog">
        <span class="g-card__pic">
            @include('themes.grid.partials.picture', ['url' => $item->imageUrl, 'label' => $item->name, 'width' => 170, 'height' => 170])
            @if ($item->isPopular)
                <span class="g-pill g-pill--badge">{{ __('menu.popular') }}</span>
            @endif
        </span>
        <span class="g-card__body">
            <span class="g-card__name">{{ $item->name }}</span>
            <x-menu.price :item="$item" class="menu-price g-price" />
            @if ($item->dietaryLabels !== [] || $live->isSoldOut($item) || ! $item->isAvailable)
                <span class="g-tags">
                    @foreach ($item->dietaryLabels as $label)
                        <span class="g-tag">{{ $label->label }}</span>
                    @endforeach
                    <x-menu.item-status :item="$item" :live="$live" :popular="false" class="g-status" />
                </span>
            @endif
        </span>
    </button>
</li>
