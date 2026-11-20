{{-- One course row: running number (CSS counter), serif name, description, small square picture (PremiumKori). --}}
<li data-item x-show="matches($el.dataset.search)" data-search="{{ mb_strtolower($item->name.' '.$item->description) }}"
    data-allergens="{{ collect($item->allergens)->pluck('key')->implode(' ') }}"
    :class="{ 'is-flagged': avoid.some(a => $el.dataset.allergens.split(' ').includes(a)) }">
    <button type="button" id="row-{{ $item->publicId }}" class="z-row {{ $live->isSoldOut($item) || ! $item->isAvailable ? 'is-muted' : '' }}"
            @click="show('{{ $item->publicId }}')" aria-haspopup="dialog">
        <span class="z-row__num" aria-hidden="true"></span>
        <span class="z-row__body">
            <span class="z-row__name">
                {{ $item->name }}
                @if ($item->isPopular)
                    <span class="z-tag">{{ __('menu.popular') }}</span>
                @endif
            </span>
            @if ($item->description)
                <span class="z-row__desc z-clamp">{{ $item->description }}</span>
            @endif
            @if ($item->allergens !== [])
                <span class="z-warn" x-show="avoid.some(a => $el.closest('[data-item]').dataset.allergens.split(' ').includes(a))" x-cloak>
                    {{ __('menu.contains', ['list' => collect($item->allergens)->pluck('label')->implode(', ')]) }}
                </span>
            @endif
            <span class="z-row__meta">
                <x-menu.price :item="$item" class="menu-price z-price" />
                @foreach ($item->dietaryLabels as $label)
                    <span class="z-code">{{ $label->label }}</span>
                @endforeach
                <x-menu.item-status :item="$item" :live="$live" :popular="false" class="z-status" />
            </span>
        </span>
        @if ($theme->option('show_images') !== false && $item->imageUrl)
            <span class="z-row__pic">
                <img src="{{ $item->imageUrl }}" alt="" width="64" height="64" loading="lazy" decoding="async">
            </span>
        @endif
    </button>
</li>
