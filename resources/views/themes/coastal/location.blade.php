{{--
    Coastal theme — location landing page: menus at the branch, hours, contact.
    Inputs: $menu (MenuSnapshot, menu = null), $live, $theme.
--}}
@extends('layouts.menu')

@section('title', $menu->brand->name.' · '.$menu->location->name)
@section('description', \Illuminate\Support\Str::limit((string) $menu->brand->description, 160))

@section('content')
    <div class="s-page">
        @include('themes.coastal.partials.header')

        <section class="s-section" aria-labelledby="s-menus-title">
            <div class="s-section__head">
                <svg width="22" height="12" viewBox="0 0 22 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M1 6c3-4 5-4 7 0s4 4 6 0 4-4 7 0"></path></svg>
                <h2 id="s-menus-title" class="s-heading">{{ __('menu.menus') }}</h2>
            </div>
            <ul class="s-list s-list--cards">
                @forelse ($menu->menus as $summary)
                    <li>
                        <a class="s-row s-row--card" href="{{ $summary->url }}?lang={{ $menu->locale }}">
                            <span class="s-item__body">
                                <span class="s-item__name">{{ $summary->name }}</span>
                                <span class="s-item__desc">{{ $summary->description ?: $summary->typeLabel }}</span>
                                @if ($summary->servingHours !== [] && $live->isServing($summary))
                                    <span class="s-item__meta"><span class="s-pill">{{ __('menu.serving_now') }}</span></span>
                                @endif
                            </span>
                            <span class="s-row__go">{{ __('menu.view_menu') }} <span class="menu-flip-rtl" aria-hidden="true">→</span></span>
                        </a>
                    </li>
                @empty
                    <li class="s-empty">{{ __('menu.no_menus') }}</li>
                @endforelse
            </ul>
        </section>

        <section class="s-section" aria-labelledby="s-hours-title">
            <div class="s-section__head">
                <svg width="22" height="12" viewBox="0 0 22 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M1 6c3-4 5-4 7 0s4 4 6 0 4-4 7 0"></path></svg>
                <h2 id="s-hours-title" class="s-heading">{{ __('menu.hours') }}</h2>
            </div>
            <div class="s-panel">
                <x-menu.opening-hours :location="$menu->location" :live="$live" class="s-hours" />
            </div>
        </section>

        @include('themes.coastal.partials.footer')
    </div>
@endsection
