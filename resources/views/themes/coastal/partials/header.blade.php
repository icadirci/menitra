{{-- Tiled cover with a wave edge, title block and the rounded info card (PremiumLodos). --}}
<div class="s-cover">
    <svg class="s-cover__tile" aria-hidden="true" width="100%" height="100%"><defs><pattern id="s-tile" width="26" height="26" patternUnits="userSpaceOnUse"><path d="M0 13h26M13 0v26" stroke="currentColor" stroke-width="1"></path><circle cx="13" cy="13" r="3" fill="none" stroke="currentColor"></circle></pattern></defs><rect width="100%" height="100%" fill="url(#s-tile)"></rect></svg>
    @if ($menu->location->coverUrl ?? $menu->brand->coverUrl)
        <img class="s-cover__img" src="{{ $menu->location->coverUrl ?? $menu->brand->coverUrl }}" alt="{{ __('menu.cover_alt', ['name' => $menu->brand->name]) }}" width="560" height="260" loading="eager" fetchpriority="high">
    @endif
    <div class="s-cover__top">
        <x-menu.home-link :menu="$menu" class="menu-home--logo">
            @if ($menu->brand->logoUrl)
                <img class="s-logo" src="{{ $menu->brand->logoUrl }}" alt="{{ __('menu.logo_alt', ['name' => $menu->brand->name]) }}" width="46" height="46">
            @else
                <span class="s-logo" aria-hidden="true">{{ mb_substr($menu->brand->name, 0, 1) }}</span>
            @endif
        </x-menu.home-link>
        <x-menu.language-switcher :locales="$menu->availableLocales" :current="$menu->locale" />
    </div>
    <svg class="s-wave" aria-hidden="true" viewBox="0 0 390 40" preserveAspectRatio="none"><path d="M0 22c24-14 41-14 65 0s41 14 65 0 41-14 65 0 41 14 65 0 41-14 65 0 41 14 65 0V40H0z"></path></svg>
</div>
<header class="s-title">
    <span class="s-kicker">{{ $menu->brand->tagline ? $menu->brand->tagline.' · '.$menu->location->name : $menu->location->name }}</span>
    <h1>{{ $menu->brand->name }}</h1>
    @if ($menu->brand->description)
        <p>{{ $menu->brand->description }}</p>
    @endif
</header>
<div class="s-info">
    <div class="s-info__row">
        <x-menu.open-state :live="$live" class="s-open" />
        @if ($live->todayHours())
            <span class="s-muted">{{ __('menu.today') }} {{ $live->todayHours() }}</span>
        @endif
    </div>
    @if ($menu->location->address)
        <div class="s-info__addr">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21zM12 7a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z"></path></svg>
            <span>{{ $menu->location->address }}</span>
        </div>
    @endif
    <x-menu.contact-actions :location="$menu->location" class="s-actions" />
</div>
