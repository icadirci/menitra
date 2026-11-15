{{--
    Gallery (grid) theme — location landing page: menus at the branch, hours, contact.
    Inputs: $menu (MenuSnapshot, menu = null), $live, $theme.
--}}
@extends('layouts.menu')

@section('title', $menu->brand->name.' · '.$menu->location->name)
@section('description', \Illuminate\Support\Str::limit((string) $menu->brand->description, 160))

@section('content')
    <div class="g-page">
        @include('themes.grid.partials.header')

        <section class="g-section" aria-labelledby="g-menus-title">
            <h2 id="g-menus-title">{{ __('menu.menus') }}</h2>
            <ul class="g-list">
                @forelse ($menu->menus as $summary)
                    <li>
                        <a class="g-row" href="{{ $summary->url }}?lang={{ $menu->locale }}">
                            <span class="g-row__body">
                                <span class="g-row__name">{{ $summary->name }}</span>
                                <span class="g-muted">{{ $summary->description ?: $summary->typeLabel }}</span>
                            </span>
                            @if ($summary->servingHours !== [] && $live->isServing($summary))
                                <span class="g-pill g-pill--accent">{{ __('menu.serving_now') }}</span>
                            @endif
                            <span class="g-row__go">{{ __('menu.view_menu') }} →</span>
                        </a>
                    </li>
                @empty
                    <li class="g-empty">{{ __('menu.no_menus') }}</li>
                @endforelse
            </ul>
        </section>

        <section class="g-section" aria-labelledby="g-hours-title">
            <h2 id="g-hours-title">{{ __('menu.hours') }}</h2>
            <div class="g-panel">
                <x-menu.opening-hours :location="$menu->location" :live="$live" class="g-hours" />
            </div>
        </section>

        @include('themes.grid.partials.footer')
    </div>
@endsection
