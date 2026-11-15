{{--
    Classic theme — location landing page: menus at the branch, hours, contact.
    Inputs: $menu (MenuSnapshot, menu = null), $live, $theme.
--}}
@extends('layouts.menu')

@section('title', $menu->brand->name.' · '.$menu->location->name)
@section('description', \Illuminate\Support\Str::limit((string) $menu->brand->description, 160))

@section('content')
    <div class="k-page">
        @include('themes.classic.partials.header')
        @include('themes.classic.partials.info')

        <section class="k-block" aria-labelledby="k-menus-title">
            <div class="k-heading"><h2 id="k-menus-title">{{ __('menu.menus') }}</h2><span aria-hidden="true"></span></div>
            @forelse ($menu->menus as $summary)
                <a class="k-card" href="{{ $summary->url }}?lang={{ $menu->locale }}">
                    <span class="k-card__body">
                        <span class="k-card__title">{{ $summary->name }}</span>
                        @if ($summary->description)
                            <span class="k-muted">{{ $summary->description }}</span>
                        @endif
                        <span class="k-tags">
                            <span class="menu-badge menu-badge--diet">{{ $summary->typeLabel }}</span>
                            @if ($summary->servingHours !== [] && $live->isServing($summary))
                                <span class="menu-badge menu-badge--accent">{{ __('menu.serving_now') }}</span>
                            @endif
                        </span>
                    </span>
                    <span class="k-card__cta">{{ __('menu.view_menu') }} →</span>
                </a>
            @empty
                <p class="k-empty">{{ __('menu.no_menus') }}</p>
            @endforelse
        </section>

        <section class="k-block" aria-labelledby="k-hours-title">
            <div class="k-heading"><h2 id="k-hours-title">{{ __('menu.hours') }}</h2><span aria-hidden="true"></span></div>
            <x-menu.opening-hours :location="$menu->location" :live="$live" class="k-hours" />
        </section>

        @include('themes.classic.partials.footer')
    </div>
@endsection
