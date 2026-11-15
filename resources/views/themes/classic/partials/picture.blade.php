{{-- Arched image or a decorative placeholder with the item's initial. --}}
<div class="k-pic {{ $class ?? '' }}">
    @if ($url)
        <img src="{{ $url }}" alt="{{ $alt ?? '' }}" width="{{ $width }}" height="{{ $height }}" loading="{{ $loading ?? 'lazy' }}" decoding="async">
    @else
        <span class="k-pic__placeholder" aria-hidden="true">
            @include('themes.classic.partials.star')
            <span class="k-pic__initial">{{ mb_substr($label, 0, 1) }}</span>
        </span>
    @endif
</div>
