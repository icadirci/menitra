{{-- Price of an item: its price, a "from" price for variants, or nothing. --}}
@props(['item'])
@if ($item->price)
    <span {{ $attributes }}>{{ $item->price->formatted }}</span>
@elseif ($item->fromPrice)
    <span {{ $attributes }}>{{ __('menu.from_price', ['price' => $item->fromPrice->formatted]) }}</span>
@endif
