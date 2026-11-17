{{-- Ornament band, language switcher, arch cover and title (MenuKonak). --}}
@include('themes.classic.partials.ornament', ['id' => 'k-band-top', 'size' => 30])
<div class="k-top">
    <x-menu.language-switcher :locales="$menu->availableLocales" :current="$menu->locale" class="k-lang" />
    <div class="k-arch">
        <div class="k-arch__inner">
            <x-menu.home-link :menu="$menu" class="menu-home--fill">
                @if ($menu->location->coverUrl ?? $menu->brand->coverUrl)
                    <img src="{{ $menu->location->coverUrl ?? $menu->brand->coverUrl }}" alt="{{ __('menu.cover_alt', ['name' => $menu->brand->name]) }}" width="254" height="338" loading="eager" fetchpriority="high">
                @elseif ($menu->brand->logoUrl)
                    <img class="k-arch__logo" src="{{ $menu->brand->logoUrl }}" alt="{{ __('menu.logo_alt', ['name' => $menu->brand->name]) }}" width="160" height="160" loading="eager">
                @else
                    <span class="k-arch__placeholder" aria-hidden="true">
                        @include('themes.classic.partials.star')
                        <span>{{ mb_substr($menu->brand->name, 0, 1) }}</span>
                    </span>
                @endif
            </x-menu.home-link>
        </div>
    </div>
</div>
<header class="k-title">
    @if ($menu->brand->tagline)
        <span class="k-kicker"><span aria-hidden="true"></span>{{ $menu->brand->tagline }}<span aria-hidden="true"></span></span>
    @endif
    <h1>{{ $menu->brand->name }}</h1>
    @if ($menu->brand->description)
        <p>{{ $menu->brand->description }}</p>
    @endif
</header>
