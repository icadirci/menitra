{{-- Translated dietary labels (and optionally allergens) of an item. --}}
@props(['labels', 'variant' => 'diet'])
@foreach ($labels as $label)
    <span {{ $attributes->merge(['class' => "menu-badge menu-badge--{$variant}"]) }}>{{ $label->label }}</span>
@endforeach
