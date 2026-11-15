{{-- Eight-point star band (decorative). $id must be unique per page; $size in px. --}}
<svg class="k-band k-band--{{ $variant ?? 'dark' }}" width="100%" height="{{ $size }}" aria-hidden="true" focusable="false">
    <defs>
        <pattern id="{{ $id }}" width="{{ $size }}" height="{{ $size }}" patternUnits="userSpaceOnUse">
            <rect class="k-band__bg" width="{{ $size }}" height="{{ $size }}"></rect>
            <rect class="k-band__star" x="{{ $size * 0.3 }}" y="{{ $size * 0.3 }}" width="{{ $size * 0.4 }}" height="{{ $size * 0.4 }}"></rect>
            <rect class="k-band__star" x="{{ $size * 0.3 }}" y="{{ $size * 0.3 }}" width="{{ $size * 0.4 }}" height="{{ $size * 0.4 }}" transform="rotate(45 {{ $size / 2 }} {{ $size / 2 }})"></rect>
            <circle class="k-band__dot" cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $size * 0.1 }}"></circle>
        </pattern>
    </defs>
    <rect width="100%" height="{{ $size }}" fill="url(#{{ $id }})"></rect>
</svg>
