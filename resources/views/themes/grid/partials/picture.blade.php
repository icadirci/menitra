{{-- Photo or a pastel placeholder with the item's initial (photo-first layout). --}}
<span class="g-pic">
    @if ($url)
        <img src="{{ $url }}" alt="{{ $alt ?? '' }}" width="{{ $width }}" height="{{ $height }}" loading="{{ $loading ?? 'lazy' }}" decoding="async">
    @else
        <span class="g-pic__initial" aria-hidden="true">{{ mb_substr($label, 0, 1) }}</span>
    @endif
</span>
