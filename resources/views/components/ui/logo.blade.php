@props([
    'size' => 34,
    'wordmark' => true,
])

{{-- Brand mark: a QR-like grid with a saffron dot. The wordmark is the brand name. --}}
<span {{ $attributes->class('inline-flex items-center gap-2.5') }}>
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 32 32" aria-hidden="true" focusable="false" class="shrink-0">
        <rect width="32" height="32" rx="9" fill="#1F4D3B" />
        <rect x="7" y="7" width="7" height="7" rx="2" fill="#F6F5F1" />
        <rect x="18" y="7" width="7" height="7" rx="2" fill="#F6F5F1" />
        <rect x="7" y="18" width="7" height="7" rx="2" fill="#F6F5F1" />
        <circle cx="21.5" cy="21.5" r="3.6" fill="#E7A33E" />
    </svg>
    @if ($wordmark)
        <span class="font-display font-bold tracking-[-0.04em]" style="font-size: {{ round($size * 0.74) }}px">{{ __('auth.brand') }}</span>
    @endif
</span>
