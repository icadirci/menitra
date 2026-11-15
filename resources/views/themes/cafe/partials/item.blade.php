{{-- One rounded product card, text left and picture right (MenuKiln); opens the detail sheet. --}}
<li data-item x-show="matches($el.dataset.search)" data-search="{{ mb_strtolower($item->name.' '.$item->description) }}">
    <button type="button" id="row-{{ $item->publicId }}" class="c-card {{ $live->isSoldOut($item) || ! $item->isAvailable ? 'is-muted' : '' }}"
            @click="show('{{ $item->publicId }}')" aria-haspopup="dialog">
        <span class="c-card__body">
            <span class="c-card__name">
                {{ $item->name }}
                @if ($item->isPopular)
                    <span class="c-pill c-pill--soft">{{ __('menu.popular') }}</span>
                @endif
            </span>
            @if ($item->description)
                <span class="c-muted c-clamp">{{ $item->description }}</span>
            @endif
            <span class="c-card__meta">
                <x-menu.price :item="$item" class="menu-price c-price" />
                @foreach ($item->dietaryLabels as $label)
                    <span class="c-code">{{ $label->label }}</span>
                @endforeach
                <x-menu.item-status :item="$item" :live="$live" :popular="false" class="c-status" />
            </span>
        </span>
        @if ($theme->option('show_images') !== false && $item->imageUrl)
            <span class="c-card__pic">
                @include('themes.cafe.partials.picture', ['url' => $item->imageUrl, 'label' => $item->name, 'width' => 92, 'height' => 92])
            </span>
        @endif
    </button>
</li>
