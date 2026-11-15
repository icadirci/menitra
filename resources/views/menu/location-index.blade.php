{{--
    Tenant with several active branches: choose a location. Shared by all
    themes (styled by menu-base.css through the theme's CSS variables).
    Inputs: $index (TenantLocations), $theme (ThemeSettings).
--}}
@extends('layouts.menu')

@section('title', $index->brand->name.' · '.__('menu.locations'))
@section('description', \Illuminate\Support\Str::limit((string) $index->brand->description, 160))

@section('content')
    <main class="menu-index">
        <x-menu.language-switcher :locales="$index->availableLocales" :current="$index->locale" class="menu-index__lang" />
        <header class="menu-index__header">
            @if ($index->brand->logoUrl)
                <img src="{{ $index->brand->logoUrl }}" alt="{{ __('menu.logo_alt', ['name' => $index->brand->name]) }}" width="88" height="88" class="menu-index__logo">
            @endif
            @if ($index->brand->tagline)
                <p class="menu-index__kicker">{{ $index->brand->tagline }}</p>
            @endif
            <h1>{{ $index->brand->name }}</h1>
            <p>{{ __('menu.choose_location') }}</p>
        </header>
        <ul class="menu-index__list">
            @foreach ($index->locations as $location)
                <li>
                    <a href="{{ $location->url }}?lang={{ $index->locale }}" class="menu-index__card">
                        <span>
                            <strong>{{ $location->name }}</strong>
                            @if ($location->address)
                                <small>{{ $location->address }}</small>
                            @endif
                        </span>
                        <span aria-hidden="true">→</span>
                    </a>
                </li>
            @endforeach
        </ul>
        <x-menu.branding-footer :brand="$index->brand" />
    </main>
@endsection
