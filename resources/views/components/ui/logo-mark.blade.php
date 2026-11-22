@props([
    'size' => 34,
])

{{--
    Menitra mark on its forest tile (48-unit grid, corner 24 %). Plain SVG
    attributes only, so it also renders in the Filament panels, which load no
    app.css. size = null makes it fill the parent's height.
--}}
<svg
    @if ($size !== null) width="{{ $size }}" height="{{ $size }}" @endif
    viewBox="0 0 48 48"
    aria-hidden="true"
    focusable="false"
    {{ $attributes->merge(['style' => $size === null ? 'height: 100%; width: auto; flex: none' : 'flex: none']) }}
>
    <rect width="48" height="48" rx="11.52" fill="#1F4D3B" />
    <g fill="#F6F5F1">
        <rect x="7.8" y="7.8" width="5.2" height="5.2" rx="1.4" />
        <rect x="7.8" y="14.6" width="5.2" height="5.2" rx="1.4" />
        <rect x="7.8" y="21.4" width="5.2" height="5.2" rx="1.4" />
        <rect x="7.8" y="28.2" width="5.2" height="5.2" rx="1.4" />
        <rect x="7.8" y="35" width="5.2" height="5.2" rx="1.4" />
        <rect x="35" y="7.8" width="5.2" height="5.2" rx="1.4" />
        <rect x="35" y="14.6" width="5.2" height="5.2" rx="1.4" />
        <rect x="35" y="21.4" width="5.2" height="5.2" rx="1.4" />
        <rect x="35" y="28.2" width="5.2" height="5.2" rx="1.4" />
        <rect x="35" y="35" width="5.2" height="5.2" rx="1.4" />
        <rect x="14.6" y="14.6" width="5.2" height="5.2" rx="1.4" />
        <rect x="28.2" y="14.6" width="5.2" height="5.2" rx="1.4" />
    </g>
    <rect x="21.4" y="21.4" width="5.2" height="5.2" rx="2.6" fill="#E7A33E" />
</svg>
