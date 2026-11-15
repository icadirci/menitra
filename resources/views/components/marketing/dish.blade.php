@props([
    'kind' => 'latte',
    'bg' => '#E9DCCB',
    'ratio' => '1 / 1',
    'pad' => '8%',
])

{{-- Decorative food illustration for the marketing site (never tenant content). --}}
<div {{ $attributes->class('relative flex items-center justify-center overflow-hidden') }} style="aspect-ratio: {{ $ratio }}; background: {{ $bg }}; padding: {{ $pad }}" aria-hidden="true">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_28%_22%,rgb(255_255_255/0.3),transparent_58%),radial-gradient(circle_at_80%_100%,rgb(0_0_0/0.1),transparent_55%)]"></div>
    @include('components.marketing.dishes.'.$kind, ['liquid' => '#B07A4F'])
</div>
