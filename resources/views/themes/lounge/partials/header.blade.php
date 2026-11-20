{{-- Sunburst header with a diamond mark, centred spaced title, open-state banner and venue facts (PremiumLumen). --}}
<header class="l-hero">
    <svg class="l-burst" aria-hidden="true" viewBox="0 0 390 260" preserveAspectRatio="xMidYMax slice"><g class="l-burst__rays"><path d="M195 250L0 40M195 250L40 0M195 250L95 0M195 250L150 0M195 250L195 0M195 250L240 0M195 250L295 0M195 250L350 0M195 250L390 40M195 250L390 110M195 250L0 110"></path></g><path class="l-burst__arc" d="M115 250a80 80 0 0 1 160 0"></path><path class="l-burst__arc l-burst__arc--inner" d="M135 250a60 60 0 0 1 120 0"></path></svg>
    <div class="l-top">
        <x-menu.home-link :menu="$menu" class="menu-home--logo">
            @if ($menu->brand->logoUrl)
                <img class="l-logo" src="{{ $menu->brand->logoUrl }}" alt="{{ __('menu.logo_alt', ['name' => $menu->brand->name]) }}" width="44" height="44">
            @else
                <span class="l-mark" aria-hidden="true"><span>{{ mb_substr($menu->brand->name, 0, 1) }}</span></span>
            @endif
        </x-menu.home-link>
        <x-menu.language-switcher :locales="$menu->availableLocales" :current="$menu->locale" />
    </div>
    <div class="l-title">
        <h1>{{ $menu->brand->name }}</h1>
        <span class="l-kicker">{{ $menu->brand->tagline ? $menu->brand->tagline.' · '.$menu->location->name : $menu->location->name }}</span>
    </div>
</header>

<div class="l-status">
    <x-menu.open-state :live="$live" class="l-open" />
    @if ($live->todayHours())
        <span class="l-status__hours">{{ $live->todayHours() }}</span>
    @endif
</div>

@if ($menu->location->coverUrl ?? $menu->brand->coverUrl)
    <div class="l-cover">
        <img src="{{ $menu->location->coverUrl ?? $menu->brand->coverUrl }}" alt="{{ __('menu.cover_alt', ['name' => $menu->brand->name]) }}" width="528" height="280" loading="eager" fetchpriority="high">
    </div>
@endif

<div class="l-info">
    @if ($menu->brand->description)
        <p class="l-info__text">{{ $menu->brand->description }}</p>
    @endif
    @if ($live->todayHours() || $menu->location->address)
        <dl class="l-facts">
            @if ($live->todayHours())
                <div><dt>{{ __('menu.today') }}</dt><dd>{{ $live->todayHours() }}</dd></div>
            @endif
            @if ($menu->location->address)
                <div><dt>{{ __('menu.address') }}</dt><dd>{{ $menu->location->address }}</dd></div>
            @endif
        </dl>
    @endif
    <x-menu.contact-actions :location="$menu->location" class="l-actions" />
</div>
