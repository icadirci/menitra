@props([
    // Outer frame width in px; the screen content is drawn at 390 px and scaled.
    'width' => 300,
    'label' => null,
])

@php
    $frame = (int) $width;
    $border = $frame >= 260 ? 10 : 8;
    $screenWidth = $frame - $border * 2;
    $screenHeight = (int) round($frame * 2.07) - $border * 2;
    $scale = round($screenWidth / 390, 4);
@endphp

<div
    {{ $attributes->class('relative shrink-0 bg-[#121614] shadow-[0_50px_90px_-40px_rgb(21_25_26/0.55)]') }}
    style="width: {{ $frame }}px; padding: {{ $border }}px; border-radius: {{ round($frame * 0.16) }}px"
    @if ($label) role="img" aria-label="{{ $label }}" @endif
>
    <div class="overflow-hidden" style="width: {{ $screenWidth }}px; height: {{ $screenHeight }}px; border-radius: {{ round($frame * 0.127) }}px" aria-hidden="true">
        <div class="origin-top-left" style="width: 390px; transform: scale({{ $scale }})">
            {{ $slot }}
        </div>
    </div>
</div>
