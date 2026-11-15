{{-- One product row (MenuKonak list); opens the detail sheet. --}}
<li class="k-item" data-item x-show="matches($el.dataset.search)" data-search="{{ mb_strtolower($item->name.' '.$item->description) }}">
    <button type="button" id="row-{{ $item->publicId }}" class="k-item__button {{ $live->isSoldOut($item) || ! $item->isAvailable ? 'is-muted' : '' }}"
            @click="show('{{ $item->publicId }}')" aria-haspopup="dialog">
        @if ($theme->option('show_images') !== false)
            @include('themes.classic.partials.picture', ['url' => $item->imageUrl, 'label' => $item->name, 'width' => 76, 'height' => 95, 'class' => 'k-pic--row'])
        @endif
        <span class="k-item__body">
            <span class="k-item__head">
                <span class="k-item__name">{{ $item->name }}</span>
                <x-menu.price :item="$item" class="k-price" />
            </span>
            @if ($item->description)
                <span class="k-item__desc">{{ $item->description }}</span>
            @endif
            <span class="k-tags">
                @if ($item->isPopular)
                    <span class="menu-badge menu-badge--accent">{{ __('menu.popular') }}</span>
                @endif
                <x-menu.badges :labels="$item->dietaryLabels" />
                @if ($live->isSoldOut($item))
                    <span class="menu-badge menu-badge--outline">{{ $live->soldOutUntil($item) ? __('menu.sold_out_until', ['time' => $live->soldOutUntil($item)]) : __('menu.sold_out') }}</span>
                @elseif (! $item->isAvailable)
                    <span class="menu-badge menu-badge--outline">{{ __('menu.unavailable_item') }}</span>
                @endif
            </span>
        </span>
    </button>
</li>
