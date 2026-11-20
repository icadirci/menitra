{{--
    Lounge theme — location landing page: menus at the branch, hours, contact.
    Inputs: $menu (MenuSnapshot, menu = null), $live, $theme.
--}}
@extends('layouts.menu')

@section('title', $menu->brand->name.' · '.$menu->location->name)
@section('description', \Illuminate\Support\Str::limit((string) $menu->brand->description, 160))

@section('content')
    <div class="l-page">
        @include('themes.lounge.partials.header')

        <section class="l-section" aria-labelledby="l-menus-title">
            <div class="l-section__head">
                <span class="l-diamond" aria-hidden="true"></span>
                <h2 id="l-menus-title" class="l-heading">{{ __('menu.menus') }}</h2>
                <span class="l-rule" aria-hidden="true"></span>
            </div>
            <ul class="l-items">
                @forelse ($menu->menus as $summary)
                    <li>
                        <a class="l-row" href="{{ $summary->url }}?lang={{ $menu->locale }}">
                            <span class="l-row__body">
                                <span class="l-row__name">{{ $summary->name }}</span>
                                <span class="l-row__desc">{{ $summary->description ?: $summary->typeLabel }}</span>
                                @if ($summary->servingHours !== [] && $live->isServing($summary))
                                    <span class="l-row__meta"><span class="l-badge">{{ __('menu.serving_now') }}</span></span>
                                @endif
                            </span>
                            <span class="l-row__go">{{ __('menu.view_menu') }} <span class="menu-flip-rtl" aria-hidden="true">→</span></span>
                        </a>
                    </li>
                @empty
                    <li class="l-empty">{{ __('menu.no_menus') }}</li>
                @endforelse
            </ul>
        </section>

        <section class="l-section" aria-labelledby="l-hours-title">
            <div class="l-section__head">
                <span class="l-diamond" aria-hidden="true"></span>
                <h2 id="l-hours-title" class="l-heading">{{ __('menu.hours') }}</h2>
                <span class="l-rule" aria-hidden="true"></span>
            </div>
            <x-menu.opening-hours :location="$menu->location" :live="$live" class="l-hours" />
        </section>

        @include('themes.lounge.partials.footer')
    </div>
@endsection
