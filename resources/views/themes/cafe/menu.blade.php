{{--
    Café theme — menu page. Design reference: docs/design/source/MenuKiln + DetailKiln.
    Inputs: $menu (MenuSnapshot), $live (LiveMenuState), $theme (ThemeSettings). No other data source.
    Layout options ($theme->option()): show_images, show_featured.
--}}
@extends('layouts.menu')

@section('title', __('menu.menu_of', ['name' => $menu->brand->name]).' · '.$menu->menu->name)
@section('description', \Illuminate\Support\Str::limit((string) $menu->brand->description, 160))

@section('content')
    <div class="c-page" x-data="menuPage" data-search="{{ collect($menu->menu->categories)->flatMap(fn ($c) => $c->items)->map(fn ($i) => mb_strtolower($i->name.' '.$i->description))->implode('|') }}">
        @include('themes.cafe.partials.header')

        @if (count($menu->menus) > 1)
            <nav class="c-menus" aria-label="{{ __('menu.menus') }}">
                @foreach ($menu->menus as $summary)
                    <a href="{{ $summary->url }}?lang={{ $menu->locale }}" @if ($summary->slug === $menu->menu->slug) aria-current="page" @endif>
                        {{ $summary->name }}
                        @if ($summary->servingHours !== [] && $live->isServing($summary))
                            <span class="c-dot" title="{{ __('menu.serving_now') }}"></span>
                        @endif
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="c-tools">
            <label class="c-search">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zM20 20l-4-4"></path></svg>
                <span class="menu-sr">{{ __('menu.search') }}</span>
                <input type="search" x-model.debounce.150ms="query" placeholder="{{ __('menu.search_placeholder') }}" autocomplete="off" enterkeyhint="search">
            </label>
            @if (count($menu->menu->categories) > 1)
                <nav class="c-chips" aria-label="{{ __('menu.categories') }}" x-show="query === ''">
                    @foreach ($menu->menu->categories as $category)
                        <a href="#{{ $category->anchor }}">{{ $category->name }}</a>
                    @endforeach
                </nav>
            @endif
        </div>

        @if ($menu->menu->featured !== [] && $theme->option('show_featured') !== false)
            <section class="c-featured" x-show="query === ''" aria-labelledby="c-featured-title">
                <h2 id="c-featured-title" class="c-heading">{{ __('menu.featured') }}</h2>
                <ul class="c-featured__list">
                    @foreach ($menu->menu->featured as $item)
                        <li>
                            <button type="button" class="c-feature" @click="show('{{ $item->publicId }}')" aria-haspopup="dialog">
                                <span class="c-feature__pic">
                                    @include('themes.cafe.partials.picture', ['url' => $theme->option('show_images') !== false ? $item->imageUrl : null, 'label' => $item->name, 'width' => 210, 'height' => 158])
                                    @if ($item->isPopular)
                                        <span class="c-pill c-pill--dark">{{ __('menu.popular') }}</span>
                                    @endif
                                </span>
                                <span class="c-feature__body">
                                    <span class="c-feature__name">{{ $item->name }}</span>
                                    @if ($item->description)
                                        <span class="c-muted c-clamp">{{ $item->description }}</span>
                                    @endif
                                    <x-menu.price :item="$item" class="menu-price c-price" />
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @forelse ($menu->menu->categories as $category)
            <section id="{{ $category->anchor }}" class="c-section" x-show="matches($el.dataset.search)"
                     data-search="{{ collect($category->items)->map(fn ($i) => mb_strtolower($i->name.' '.$i->description))->implode('|') }}">
                <div class="c-section__head">
                    <h2 class="c-heading">{{ $category->name }}</h2>
                    <span class="c-muted">{{ count($category->items) }}</span>
                </div>
                @if ($category->description)
                    <p class="c-section__desc c-muted">{{ $category->description }}</p>
                @endif
                <ul class="c-items">
                    @foreach ($category->items as $item)
                        @include('themes.cafe.partials.item')
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="c-empty">{{ __('menu.empty_menu') }}</p>
        @endforelse

        <p class="c-empty" x-show="query !== '' && ! matches($root.dataset.search)" x-cloak>{{ __('menu.no_results') }}</p>

        @include('themes.cafe.partials.footer')

        @foreach ($menu->menu->categories as $category)
            @foreach ($category->items as $item)
                @include('themes.cafe.partials.sheet')
            @endforeach
        @endforeach
    </div>
@endsection
