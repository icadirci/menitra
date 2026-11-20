{{--
    Zen theme — location landing page: menus at the branch, hours, contact.
    Inputs: $menu (MenuSnapshot, menu = null), $live, $theme.
--}}
@extends('layouts.menu')

@section('title', $menu->brand->name.' · '.$menu->location->name)
@section('description', \Illuminate\Support\Str::limit((string) $menu->brand->description, 160))

@section('content')
    <div class="z-page">
        @include('themes.zen.partials.header')

        <section class="z-section" aria-labelledby="z-menus-title">
            <div class="z-section__head"><h2 id="z-menus-title" class="z-heading">{{ __('menu.menus') }}</h2></div>
            <ul class="z-items">
                @forelse ($menu->menus as $summary)
                    <li>
                        <a class="z-row" href="{{ $summary->url }}?lang={{ $menu->locale }}">
                            <span class="z-row__body">
                                <span class="z-row__name">{{ $summary->name }}</span>
                                <span class="z-row__desc">{{ $summary->description ?: $summary->typeLabel }}</span>
                                @if ($summary->servingHours !== [] && $live->isServing($summary))
                                    <span class="z-tag">{{ __('menu.serving_now') }}</span>
                                @endif
                            </span>
                            <span class="z-row__go">{{ __('menu.view_menu') }} <span class="menu-flip-rtl" aria-hidden="true">→</span></span>
                        </a>
                    </li>
                @empty
                    <li class="z-empty">{{ __('menu.no_menus') }}</li>
                @endforelse
            </ul>
        </section>

        <section class="z-section" aria-labelledby="z-hours-title">
            <div class="z-section__head"><h2 id="z-hours-title" class="z-heading">{{ __('menu.hours') }}</h2></div>
            <x-menu.opening-hours :location="$menu->location" :live="$live" class="z-hours" />
        </section>

        @include('themes.zen.partials.footer')
    </div>
@endsection
