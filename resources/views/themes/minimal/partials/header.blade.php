{{-- Cover strip, logo mark, name, open state, address and contact (MenuNoon). --}}
<div class="n-cover {{ ($menu->location->coverUrl ?? $menu->brand->coverUrl) ? '' : 'n-cover--plain' }}">
    @if ($menu->location->coverUrl ?? $menu->brand->coverUrl)
        <img src="{{ $menu->location->coverUrl ?? $menu->brand->coverUrl }}" alt="{{ __('menu.cover_alt', ['name' => $menu->brand->name]) }}" width="560" height="180" loading="eager" fetchpriority="high">
    @endif
    <x-menu.language-switcher :locales="$menu->availableLocales" :current="$menu->locale" class="n-lang" />
</div>
<header class="n-header">
    <div class="n-header__top">
        <div class="n-brand">
            @if ($menu->brand->logoUrl)
                <img class="n-mark" src="{{ $menu->brand->logoUrl }}" alt="{{ __('menu.logo_alt', ['name' => $menu->brand->name]) }}" width="44" height="44">
            @else
                <span class="n-mark" aria-hidden="true">{{ mb_substr($menu->brand->name, 0, 1) }}.</span>
            @endif
            <div>
                <h1>{{ $menu->brand->name }}</h1>
                @if ($menu->brand->tagline)
                    <span class="n-muted">{{ $menu->brand->tagline }}</span>
                @endif
            </div>
        </div>
        <x-menu.open-state :live="$live" class="n-open" />
    </div>
    @if ($menu->brand->description)
        <p class="n-header__desc">{{ $menu->brand->description }}</p>
    @endif
    <div class="n-header__meta">
        @if ($live->todayHours())
            <span>{{ __('menu.today') }} {{ $live->todayHours() }}</span>
        @endif
        @if ($menu->location->address)
            <span>{{ $menu->location->address }}</span>
        @endif
    </div>
    <x-menu.contact-actions :location="$menu->location" class="n-links" />
</header>
