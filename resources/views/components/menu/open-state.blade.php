{{-- "Open · until 22:00" / "Closed · opens at 08:00" from the live state (never cached). --}}
@props(['live'])
<span {{ $attributes->merge(['class' => 'menu-open-state '.($live->isOpenNow() ? 'is-open' : 'is-closed')]) }}>
    <span class="menu-open-state__dot" aria-hidden="true"></span>
    @if ($live->isOpenNow())
        <strong>{{ __('menu.open_now') }}</strong>@if ($live->closesAt()) · {{ __('menu.closes_at', ['time' => $live->closesAt()]) }}@endif
    @else
        <strong>{{ __('menu.closed_now') }}</strong>@if ($live->opensAt()) · {{ __('menu.opens_at', ['time' => $live->opensAt()]) }}@endif
    @endif
</span>
