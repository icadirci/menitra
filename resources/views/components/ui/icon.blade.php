@props([
    'name',
    'size' => 20,
    'stroke' => 1.8,
])

@php
    // Design system icon set: 24 px grid, round caps and joins (DesignSystem.dc.html).
    $paths = [
        'qr' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 18h2v2h-2zM14 18h2M18 14h2',
        'phone' => 'M8 2.5h8a2 2 0 0 1 2 2v15a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2v-15a2 2 0 0 1 2-2zM11 18.5h2',
        'layers' => 'M12 3l9 4.5-9 4.5-9-4.5L12 3zM3 12l9 4.5 9-4.5M3 16.5l9 4.5 9-4.5',
        'image' => 'M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM3 16l5-5 4 4 3-3 6 6',
        'tag' => 'M3 12V4h8l10 10-8 8L3 12zM7.5 8.5h.01',
        'globe' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z',
        'sliders' => 'M4 7h10M18 7h2M4 17h4M12 17h8M16 5v4M10 15v4',
        'bolt' => 'M13 2L4 14h7l-1 8 9-12h-7l1-8z',
        'sparkle' => 'M12 3l2.2 5.8L20 11l-5.8 2.2L12 19l-2.2-5.8L4 11l5.8-2.2L12 3z',
        'search' => 'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zM20 20l-4-4',
        'clock' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7.5V12l3 2',
        'pin' => 'M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21zM12 7a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z',
        'instagram' => 'M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4zM12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7zM17.5 6.5h.01',
        'leaf' => 'M5 19c0-8 5-14 15-15-1 10-7 15-15 15zM5 19l7-7',
        'check' => 'M5 12.5l4.5 4.5L19 7.5',
        'arrow-right' => 'M5 12h14M13 6l6 6-6 6',
        'arrow-left' => 'M19 12H5M11 6l-6 6 6 6',
        'chevron' => 'M6 9l6 6 6-6',
        'plus' => 'M12 5v14M5 12h14',
        'minus' => 'M6 12h12',
        'close' => 'M6 6l12 12M18 6L6 18',
        'mail' => 'M4 6h16v12H4zM4 7l8 6 8-6',
        'lock' => 'M6 11h12v10H6zM8.5 11V7.5a3.5 3.5 0 0 1 7 0V11M12 15v2',
        'eye' => 'M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z',
        'eye-off' => 'M3 3l18 18M10.6 5.1A10.6 10.6 0 0 1 12 5c6.4 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.1M6.6 6.6C3.8 8.4 2 12 2 12s3.6 7 10 7c1.9 0 3.6-.6 5-1.5M9.9 9.9a3 3 0 0 0 4.2 4.2',
        'user' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0',
        'menu' => 'M4 7h16M4 12h16M4 17h16',
        'chat' => 'M4 5h16v11H9l-5 4V5z',
        'facebook' => 'M15 3h-2.5A3.5 3.5 0 0 0 9 6.5V10H6.5v3.5H9V21h3.5v-7.5H15l.5-3.5h-3V7a1 1 0 0 1 1-1H15V3z',
        'x' => 'M4 4l6.5 8.5L4 20h2l5.4-6.2L16 20h4l-6.8-9L19.5 4h-2l-5 5.7L8 4H4z',
        'linkedin' => 'M4 4h16v16H4zM8 10.5V17M8 7.5h.01M12 17v-6.5M12 13.5c0-1.7 1.3-3 3-3s2 1.3 2 3V17',
        'youtube' => 'M3 8.5A3.5 3.5 0 0 1 6.5 5h11A3.5 3.5 0 0 1 21 8.5v7a3.5 3.5 0 0 1-3.5 3.5h-11A3.5 3.5 0 0 1 3 15.5zM10 9.5v5l4.5-2.5z',
        'tiktok' => 'M14 3v11.5a3.5 3.5 0 1 1-3.5-3.5M14 3c.4 2.6 2.2 4.4 5 4.6',
    ];
@endphp

<svg
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="{{ $stroke }}"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
    {{ $attributes->class('shrink-0') }}
><path d="{{ $paths[$name] }}" /></svg>
