{{-- Rounded cover, overlapping logo, centred title, pills and actions (MenuPetal). --}}
<div class="g-cover-wrap">
    <div class="g-cover">
        @if ($menu->location->coverUrl ?? $menu->brand->coverUrl)
            <img src="{{ $menu->location->coverUrl ?? $menu->brand->coverUrl }}" alt="{{ __('menu.cover_alt', ['name' => $menu->brand->name]) }}" width="536" height="250" loading="eager" fetchpriority="high">
        @else
            <span class="g-blob" aria-hidden="true"></span>
        @endif
        <x-menu.language-switcher :locales="$menu->availableLocales" :current="$menu->locale" class="g-lang" />
    </div>
</div>
<header class="g-header">
    <x-menu.home-link :menu="$menu" class="menu-home--logo">
        @if ($menu->brand->logoUrl)
            <img class="g-logo" src="{{ $menu->brand->logoUrl }}" alt="{{ __('menu.logo_alt', ['name' => $menu->brand->name]) }}" width="76" height="76">
        @else
            <span class="g-logo" aria-hidden="true">{{ mb_substr($menu->brand->name, 0, 1) }}</span>
        @endif
    </x-menu.home-link>
    <h1>{{ $menu->brand->name }}</h1>
    @if ($menu->brand->description || $menu->brand->tagline)
        <p class="g-muted">{{ $menu->brand->description ?: $menu->brand->tagline }}</p>
    @endif
    <div class="g-pills">
        <x-menu.open-state :live="$live" class="g-pill g-open" />
        @if ($live->todayHours())
            <span class="g-pill">{{ __('menu.today') }} {{ $live->todayHours() }}</span>
        @endif
    </div>
    @if ($menu->location->address)
        <span class="g-muted">{{ $menu->location->address }}</span>
    @endif
    <x-menu.contact-actions :location="$menu->location" class="g-actions" />
</header>
