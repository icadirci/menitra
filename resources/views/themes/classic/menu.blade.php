{{--
    Classic theme — menu page. Design reference: docs/design/source/MenuKonak + DetailKonak.
    Inputs: $menu (MenuSnapshot), $live (LiveMenuState), $theme (ThemeSettings). No other data source.
--}}
@extends('layouts.menu')

@section('title', __('menu.menu_of', ['name' => $menu->brand->name]).' · '.$menu->menu->name)
@section('description', \Illuminate\Support\Str::limit((string) $menu->brand->description, 160))

@section('content')
    <div class="k-page" x-data="menuPage" data-search="{{ collect($menu->menu->categories)->flatMap(fn ($c) => $c->items)->map(fn ($i) => mb_strtolower($i->name.' '.$i->description))->implode('|') }}">
        @include('themes.classic.partials.header')
        @include('themes.classic.partials.info')

        @if (count($menu->menus) > 1)
            <nav class="k-menus" aria-label="{{ __('menu.menus') }}">
                @foreach ($menu->menus as $summary)
                    <a href="{{ $summary->url }}?lang={{ $menu->locale }}" @if ($summary->slug === $menu->menu->slug) aria-current="page" @endif>
                        {{ $summary->name }}
                        @if ($live->isServing($summary) && $summary->servingHours !== [])
                            <span class="k-dot" title="{{ __('menu.serving_now') }}"></span>
                        @endif
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="k-tools">
            <label class="k-search">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zM20 20l-4-4"></path></svg>
                <span class="sr-only">{{ __('menu.search') }}</span>
                <input type="search" x-model.debounce.150ms="query" placeholder="{{ __('menu.search_placeholder') }}" autocomplete="off" enterkeyhint="search">
            </label>
            @if (count($menu->menu->categories) > 1)
                <nav class="k-cats" aria-label="{{ __('menu.categories') }}" x-show="query === ''">
                    @foreach ($menu->menu->categories as $category)
                        <a href="#{{ $category->anchor }}">{{ $category->name }}</a>
                    @endforeach
                </nav>
            @endif
        </div>

        @if ($menu->menu->featured !== [])
            <section class="k-featured" x-show="query === ''" aria-labelledby="k-featured-title">
                <div class="k-heading"><h2 id="k-featured-title">{{ __('menu.featured') }}</h2><span aria-hidden="true"></span></div>
                <ul class="k-featured__list">
                    @foreach ($menu->menu->featured as $item)
                        <li>
                            <button type="button" class="k-feature" @click="show('{{ $item->publicId }}')" aria-haspopup="dialog">
                                <span class="k-feature__pic">
                                    @include('themes.classic.partials.picture', ['url' => $item->imageUrl, 'label' => $item->name, 'width' => 150, 'height' => 200, 'class' => 'k-pic--feature'])
                                    @if ($item->isPopular)
                                        <span class="menu-badge menu-badge--accent">{{ __('menu.popular') }}</span>
                                    @endif
                                </span>
                                <span class="k-feature__name">{{ $item->name }}</span>
                                <x-menu.price :item="$item" class="k-price" />
                            </button>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @forelse ($menu->menu->categories as $category)
            <section id="{{ $category->anchor }}" class="k-section" x-show="matches($el.dataset.search)"
                     data-search="{{ collect($category->items)->map(fn ($i) => mb_strtolower($i->name.' '.$i->description))->implode('|') }}">
                <div class="k-section__head">
                    @include('themes.classic.partials.star')
                    <h2>{{ $category->name }}</h2>
                </div>
                @if ($category->description)
                    <p class="k-section__desc">{{ $category->description }}</p>
                @endif
                <ul class="k-items">
                    @foreach ($category->items as $item)
                        @include('themes.classic.partials.item-row')
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="k-empty">{{ __('menu.empty_menu') }}</p>
        @endforelse

        <p class="k-empty" x-show="query !== '' && ! matches($root.dataset.search)" x-cloak>{{ __('menu.no_results') }}</p>

        @include('themes.classic.partials.footer')

        @foreach ($menu->menu->categories as $category)
            @foreach ($category->items as $item)
                @include('themes.classic.partials.item-sheet')
            @endforeach
        @endforeach
    </div>
@endsection
