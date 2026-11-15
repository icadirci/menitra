{{-- Rounded picture or a soft placeholder with the item's initial. --}}
<span class="c-pic">
    @if ($url)
        <img src="{{ $url }}" alt="{{ $alt ?? '' }}" width="{{ $width }}" height="{{ $height }}" loading="{{ $loading ?? 'lazy' }}" decoding="async">
    @else
        <span class="c-pic__initial" aria-hidden="true">{{ mb_substr($label, 0, 1) }}</span>
    @endif
</span>
