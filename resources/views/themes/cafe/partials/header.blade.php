{{-- Cover, language switcher and the floating venue card (MenuKiln). --}}
<div class="c-cover">
    @if ($menu->location->coverUrl ?? $menu->brand->coverUrl)
        <img src="{{ $menu->location->coverUrl ?? $menu->brand->coverUrl }}" alt="{{ __('menu.cover_alt', ['name' => $menu->brand->name]) }}" width="560" height="270" loading="eager" fetchpriority="high">
    @endif
    <x-menu.language-switcher :locales="$menu->availableLocales" :current="$menu->locale" class="c-lang" />
</div>
<header class="c-venue">
    <div class="c-venue__top">
        @if ($menu->brand->logoUrl)
            <img class="c-logo" src="{{ $menu->brand->logoUrl }}" alt="{{ __('menu.logo_alt', ['name' => $menu->brand->name]) }}" width="60" height="60">
        @else
            <span class="c-logo" aria-hidden="true">{{ mb_substr($menu->brand->name, 0, 1) }}</span>
        @endif
        <div class="c-venue__name">
            <h1>{{ $menu->brand->name }}</h1>
            <x-menu.open-state :live="$live" class="c-open" />
        </div>
    </div>
    @if ($menu->brand->tagline || $menu->brand->description)
        <p class="c-muted">{{ $menu->brand->description ?: $menu->brand->tagline }}</p>
    @endif
    <div class="c-venue__meta">
        @if ($live->todayHours())
            <div><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7.5V12l3 2"></path></svg><span>{{ __('menu.today') }} {{ $live->todayHours() }}</span></div>
        @endif
        @if ($menu->location->address)
            <div><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21zM12 7a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z"></path></svg><span>{{ $menu->location->address }}</span></div>
        @endif
    </div>
    <x-menu.contact-actions :location="$menu->location" class="c-actions" />
</header>
