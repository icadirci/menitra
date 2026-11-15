{{--
    Bistro theme — location landing page: menus at the branch, hours, contact.
    Inputs: $menu (MenuSnapshot, menu = null), $live, $theme.
--}}
@extends('layouts.menu')

@section('title', $menu->brand->name.' · '.$menu->location->name)
@section('description', \Illuminate\Support\Str::limit((string) $menu->brand->description, 160))

@section('content')
    <div class="b-page">
        @include('themes.bistro.partials.header')

        <section class="b-section" aria-labelledby="b-menus-title">
            <div class="b-rule"><span aria-hidden="true"></span><h2 id="b-menus-title">{{ __('menu.menus') }}</h2><span aria-hidden="true"></span></div>
            <ul class="b-items">
                @forelse ($menu->menus as $summary)
                    <li>
                        <a class="b-item" href="{{ $summary->url }}?lang={{ $menu->locale }}">
                            <span class="b-item__body">
                                <span class="b-item__line">
                                    <span class="b-item__name">{{ $summary->name }}</span>
                                    <span class="b-leader" aria-hidden="true"></span>
                                    <span class="b-label">{{ __('menu.view_menu') }}</span>
                                </span>
                                <span class="b-italic">{{ $summary->description ?: $summary->typeLabel }}</span>
                                @if ($summary->servingHours !== [] && $live->isServing($summary))
                                    <span class="b-label">{{ __('menu.serving_now') }}</span>
                                @endif
                            </span>
                        </a>
                    </li>
                @empty
                    <li class="b-empty">{{ __('menu.no_menus') }}</li>
                @endforelse
            </ul>
        </section>

        <section class="b-section" aria-labelledby="b-hours-title">
            <div class="b-rule"><span aria-hidden="true"></span><h2 id="b-hours-title">{{ __('menu.hours') }}</h2><span aria-hidden="true"></span></div>
            <x-menu.opening-hours :location="$menu->location" :live="$live" class="b-hours" />
        </section>

        @include('themes.bistro.partials.footer')
    </div>
@endsection
