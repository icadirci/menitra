@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'submit',
])

@php
    $classes = [
        'inline-flex items-center justify-center gap-2.5 rounded-button font-semibold no-underline transition-colors',
        'focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-forest-700/20',
        'disabled:cursor-not-allowed disabled:border disabled:border-line disabled:bg-paper disabled:text-muted disabled:shadow-none',
        match ($size) {
            'lg' => 'min-h-14 px-7 text-base',
            'sm' => 'min-h-11 px-4 text-[15px]',
            default => 'min-h-12 px-5 text-[15px]',
        },
        match ($variant) {
            'secondary' => 'border border-line-strong bg-white text-ink hover:border-ink/40',
            'accent' => 'bg-saffron-500 text-ink hover:bg-[#D9932C]',
            'ghost' => 'text-forest-700 hover:text-forest-900 hover:underline underline-offset-4',
            'inverse' => 'border border-paper/35 text-paper hover:bg-white/10',
            default => 'bg-forest-700 text-white shadow-cta hover:bg-forest-900',
        },
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class(array_merge($classes, ['cursor-pointer'])) }}>{{ $slot }}</button>
@endif
