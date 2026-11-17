{{-- Monogram, language switcher, framed cover, centred title and info rows (MenuArdoise). --}}
<div class="b-top">
    <x-menu.home-link :menu="$menu" class="menu-home--logo">
        @if ($menu->brand->logoUrl)
            <img class="b-monogram" src="{{ $menu->brand->logoUrl }}" alt="{{ __('menu.logo_alt', ['name' => $menu->brand->name]) }}" width="44" height="44">
        @else
            <span class="b-monogram" aria-hidden="true">{{ mb_strtoupper(mb_substr($menu->brand->name, 0, 1)) }}</span>
        @endif
    </x-menu.home-link>
    <x-menu.language-switcher :locales="$menu->availableLocales" :current="$menu->locale" class="b-lang" />
</div>
@if ($menu->location->coverUrl ?? $menu->brand->coverUrl)
    <div class="b-cover">
        <img src="{{ $menu->location->coverUrl ?? $menu->brand->coverUrl }}" alt="{{ __('menu.cover_alt', ['name' => $menu->brand->name]) }}" width="520" height="390" loading="eager" fetchpriority="high">
        <span class="b-frame" aria-hidden="true"></span>
    </div>
@endif
<header class="b-title">
    @if ($menu->brand->tagline)
        <span class="b-label">{{ $menu->brand->tagline }}</span>
    @endif
    <h1>{{ $menu->brand->name }}</h1>
    @if ($menu->brand->description)
        <p class="b-italic">{{ $menu->brand->description }}</p>
    @endif
</header>
<div class="b-info">
    <div class="b-info__row"><span class="b-info__key">{{ $menu->location->name }}</span><x-menu.open-state :live="$live" class="b-open" /></div>
    @if ($live->todayHours())
        <div class="b-info__row"><span class="b-info__key">{{ __('menu.today') }}</span><span>{{ $live->todayHours() }}</span></div>
    @endif
    @if ($menu->location->address)
        <div class="b-info__row"><span class="b-info__key">{{ __('menu.address') }}</span><span>{{ $menu->location->address }}</span></div>
    @endif
    <x-menu.contact-actions :location="$menu->location" class="b-links" />
</div>
