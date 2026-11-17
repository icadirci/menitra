{{--
    Café theme — location landing page: menus at the branch, hours, contact.
    Inputs: $menu (MenuSnapshot, menu = null), $live, $theme.
--}}
@extends('layouts.menu')

@section('title', $menu->brand->name.' · '.$menu->location->name)
@section('description', \Illuminate\Support\Str::limit((string) $menu->brand->description, 160))

@section('content')
    <div class="c-page">
        @include('themes.cafe.partials.header')

        <section class="c-section" aria-labelledby="c-menus-title">
            <div class="c-section__head"><h2 id="c-menus-title" class="c-heading">{{ __('menu.menus') }}</h2></div>
            <ul class="c-items">
                @forelse ($menu->menus as $summary)
                    <li>
                        <a class="c-card" href="{{ $summary->url }}?lang={{ $menu->locale }}">
                            <span class="c-card__body">
                                <span class="c-card__name">{{ $summary->name }}</span>
                                <span class="c-muted">{{ $summary->description ?: $summary->typeLabel }}</span>
                                @if ($summary->servingHours !== [] && $live->isServing($summary))
                                    <span class="c-pill c-pill--soft">{{ __('menu.serving_now') }}</span>
                                @endif
                            </span>
                            <span class="c-card__go">{{ __('menu.view_menu') }} <span class="menu-flip-rtl" aria-hidden="true">→</span></span>
                        </a>
                    </li>
                @empty
                    <li class="c-empty">{{ __('menu.no_menus') }}</li>
                @endforelse
            </ul>
        </section>

        <section class="c-section" aria-labelledby="c-hours-title">
            <div class="c-section__head"><h2 id="c-hours-title" class="c-heading">{{ __('menu.hours') }}</h2></div>
            <div class="c-panel">
                <x-menu.opening-hours :location="$menu->location" :live="$live" class="c-hours" />
            </div>
        </section>

        @include('themes.cafe.partials.footer')
    </div>
@endsection
