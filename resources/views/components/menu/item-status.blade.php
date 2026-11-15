{{-- Popular and sold-out / unavailable badges of an item (effective sold-out from $live). --}}
@props(['item', 'live', 'popular' => true])
@if ($popular && $item->isPopular)
    <span {{ $attributes->merge(['class' => 'menu-badge menu-badge--accent']) }}>{{ __('menu.popular') }}</span>
@endif
@if ($live->isSoldOut($item))
    <span {{ $attributes->merge(['class' => 'menu-badge menu-badge--outline']) }}>{{ $live->soldOutUntil($item) ? __('menu.sold_out_until', ['time' => $live->soldOutUntil($item)]) : __('menu.sold_out') }}</span>
@elseif (! $item->isAvailable)
    <span {{ $attributes->merge(['class' => 'menu-badge menu-badge--outline']) }}>{{ __('menu.unavailable_item') }}</span>
@endif
