{{-- One item: a compact card in the two-column grid ($card) or a row with a rounded picture (PremiumLodos). --}}
<li data-item x-show="matches($el.dataset.search)" data-search="{{ mb_strtolower($item->name.' '.$item->description) }}">
    <button type="button" id="row-{{ $item->publicId }}" class="{{ $card ? 's-card' : 's-row' }} {{ $live->isSoldOut($item) || ! $item->isAvailable ? 'is-muted' : '' }}"
            @click="show('{{ $item->publicId }}')" aria-haspopup="dialog">
        @if (! $card)
            <span class="s-row__pic">
                @if ($item->imageUrl)
                    <img src="{{ $item->imageUrl }}" alt="" width="72" height="72" loading="lazy" decoding="async">
                @else
                    <span class="s-initial" aria-hidden="true">{{ mb_substr($item->name, 0, 1) }}</span>
                @endif
            </span>
        @endif
        <span class="s-item__body">
            <span class="s-item__top">
                <span class="s-item__name">{{ $item->name }}</span>
                @if (! $card)
                    <x-menu.price :item="$item" class="menu-price s-price" />
                @endif
            </span>
            @if ($item->description)
                <span class="s-item__desc s-clamp">{{ $item->description }}</span>
            @endif
            <span class="s-item__meta">
                @if ($card)
                    <x-menu.price :item="$item" class="menu-price s-price" />
                @endif
                @if ($item->isPopular)
                    <span class="s-pill">{{ __('menu.popular') }}</span>
                @endif
                @foreach ($item->dietaryLabels as $label)
                    <span class="s-code">{{ $label->label }}</span>
                @endforeach
                <x-menu.item-status :item="$item" :live="$live" :popular="false" class="s-status" />
            </span>
        </span>
    </button>
</li>
