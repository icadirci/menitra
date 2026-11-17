{{--
    Minimal theme — location landing page: menus at the branch, hours, contact.
    Inputs: $menu (MenuSnapshot, menu = null), $live, $theme.
--}}
@extends('layouts.menu')

@section('title', $menu->brand->name.' · '.$menu->location->name)
@section('description', \Illuminate\Support\Str::limit((string) $menu->brand->description, 160))

@section('content')
    <div class="n-page">
        @include('themes.minimal.partials.header')

        <section class="n-section" aria-labelledby="n-menus-title">
            <h2 id="n-menus-title">{{ __('menu.menus') }}</h2>
            <ul class="n-items">
                @forelse ($menu->menus as $summary)
                    <li>
                        <a class="n-item n-item--link" href="{{ $summary->url }}?lang={{ $menu->locale }}">
                            <span class="n-item__body">
                                <span class="n-item__name">
                                    {{ $summary->name }}
                                    @if ($summary->servingHours !== [] && $live->isServing($summary))
                                        <span class="n-flag">{{ __('menu.serving_now') }}</span>
                                    @endif
                                </span>
                                <span class="n-item__desc">{{ $summary->description ?: $summary->typeLabel }}</span>
                            </span>
                            <span class="n-item__go">{{ __('menu.view_menu') }} <span class="menu-flip-rtl" aria-hidden="true">→</span></span>
                        </a>
                    </li>
                @empty
                    <li class="n-empty">{{ __('menu.no_menus') }}</li>
                @endforelse
            </ul>
        </section>

        <section class="n-section" aria-labelledby="n-hours-title">
            <h2 id="n-hours-title">{{ __('menu.hours') }}</h2>
            <x-menu.opening-hours :location="$menu->location" :live="$live" class="n-hours" />
        </section>

        @include('themes.minimal.partials.footer')
    </div>
@endsection
