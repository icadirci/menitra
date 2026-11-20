{{-- Seal mark, language switcher, large serif title, cover and the venue facts (PremiumKori). --}}
<div class="z-hero">
    <span class="z-watermark" aria-hidden="true">{{ mb_substr($menu->brand->name, 0, 1) }}</span>
    <div class="z-top">
        <x-menu.home-link :menu="$menu" class="menu-home--logo">
            @if ($menu->brand->logoUrl)
                <img class="z-seal" src="{{ $menu->brand->logoUrl }}" alt="{{ __('menu.logo_alt', ['name' => $menu->brand->name]) }}" width="44" height="44">
            @else
                <span class="z-seal" aria-hidden="true">{{ mb_substr($menu->brand->name, 0, 1) }}</span>
            @endif
        </x-menu.home-link>
        <x-menu.language-switcher :locales="$menu->availableLocales" :current="$menu->locale" />
    </div>
    <header class="z-title">
        <span class="z-kicker">{{ $menu->brand->tagline ? $menu->brand->tagline.' · '.$menu->location->name : $menu->location->name }}</span>
        <h1>{{ $menu->brand->name }}</h1>
    </header>
</div>
@if ($menu->location->coverUrl ?? $menu->brand->coverUrl)
    <div class="z-cover">
        <img src="{{ $menu->location->coverUrl ?? $menu->brand->coverUrl }}" alt="{{ __('menu.cover_alt', ['name' => $menu->brand->name]) }}" width="520" height="325" loading="eager" fetchpriority="high">
    </div>
@endif
<div class="z-intro">
    @if ($menu->brand->description)
        <p class="z-intro__text">{{ $menu->brand->description }}</p>
    @endif
    <dl class="z-facts">
        <dt>{{ __('menu.today') }}</dt>
        <dd><x-menu.open-state :live="$live" class="z-open" /></dd>
        @if ($live->todayHours())
            <dt>{{ __('menu.hours') }}</dt>
            <dd>{{ $live->todayHours() }}</dd>
        @endif
        @if ($menu->location->address)
            <dt>{{ __('menu.address') }}</dt>
            <dd>{{ $menu->location->address }}</dd>
        @endif
    </dl>
    <x-menu.contact-actions :location="$menu->location" class="z-actions" />
</div>
