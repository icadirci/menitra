@props([
    'size' => 34,
    'wordmark' => true,
])

{{--
    Menitra logo (direction "B · Modül", docs/design/brand.md): a 5 × 5 grid of QR
    modules forming an "M", with a saffron module in the centre (the plate on the
    table). The wordmark is the logo artwork, always lower case, with the same
    saffron module as the dot of the i; it is decorative, the accessible name is
    the translated brand name.
--}}
<span {{ $attributes->class('inline-flex items-center') }} style="gap: {{ round($size * 0.3) }}px">
    <x-ui.logo-mark :size="$size" />
    @if ($wordmark)
        <span
            class="font-display leading-none font-bold tracking-[-0.05em] whitespace-nowrap"
            style="font-size: {{ $size }}px"
            aria-hidden="true"
        >men<span class="relative inline-block">ı<span class="absolute rounded-[0.05em] bg-saffron-500" style="left: 50%; top: 0.04em; width: 0.19em; height: 0.19em; margin-left: -0.095em"></span></span>tra</span>
        <span class="sr-only">{{ __('auth.brand') }}</span>
    @endif
</span>
