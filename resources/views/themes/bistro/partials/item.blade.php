{{-- One course row with round picture and dotted leader (MenuArdoise); opens the detail sheet. --}}
<li data-item x-show="matches($el.dataset.search)" data-search="{{ mb_strtolower($item->name.' '.$item->description) }}">
    <button type="button" id="row-{{ $item->publicId }}" class="b-item {{ $live->isSoldOut($item) || ! $item->isAvailable ? 'is-muted' : '' }}"
            @click="show('{{ $item->publicId }}')" aria-haspopup="dialog">
        @if ($theme->option('show_images') !== false && $item->imageUrl)
            <img class="b-thumb" src="{{ $item->imageUrl }}" alt="" width="64" height="64" loading="lazy" decoding="async">
        @endif
        <span class="b-item__body">
            <span class="b-item__line">
                <span class="b-item__name">{{ $item->name }}</span>
                <span class="b-leader" aria-hidden="true"></span>
                <x-menu.price :item="$item" class="menu-price b-price" />
            </span>
            @if ($item->description)
                <span class="b-italic b-item__desc">{{ $item->description }}</span>
            @endif
            @if ($item->isPopular || $item->dietaryLabels !== [] || $live->isSoldOut($item) || ! $item->isAvailable)
                <span class="b-tags">
                    <x-menu.item-status :item="$item" :live="$live" class="b-status" />
                    @foreach ($item->dietaryLabels as $label)
                        <span class="b-label b-label--muted">{{ $label->label }}</span>
                    @endforeach
                </span>
            @endif
        </span>
    </button>
</li>
