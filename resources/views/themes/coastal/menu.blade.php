{{--
    Coastal theme — menu page. Design reference: docs/design/source/PremiumLodos + PremiumDetailLodos.
    Inputs: $menu (MenuSnapshot), $live (LiveMenuState), $theme (ThemeSettings). No other data source.
    Layout options ($theme->option()): category_layout (list | tiles, via $theme->categoryTiles()), cart (order list, via $theme->cartEnabled()), show_images, show_featured.
    A category whose items have no pictures (or with show_images off) is shown as a two-column card grid, otherwise as a list with pictures.
--}}
@extends('layouts.menu')

@section('title', __('menu.menu_of', ['name' => $menu->brand->name]).' · '.$menu->menu->name)
@section('description', \Illuminate\Support\Str::limit((string) $menu->brand->description, 160))

@section('content')
    <div class="s-page" x-data="menuPage({{ $theme->categoryTiles() ? 'true' : 'false' }})" @if ($theme->cartEnabled()) data-cart="{{ $menu->location->url }}" data-currency="{{ $menu->brand->currency }}" @endif data-search="{{ collect($menu->menu->categories)->flatMap(fn ($c) => $c->items)->map(fn ($i) => mb_strtolower($i->name.' '.$i->description))->implode('|') }}">
        @include('themes.coastal.partials.header')

        @if (count($menu->menus) > 1)
            <nav class="s-menus" aria-label="{{ __('menu.menus') }}">
                @foreach ($menu->menus as $summary)
                    <a href="{{ $summary->url }}?lang={{ $menu->locale }}" @if ($summary->slug === $menu->menu->slug) aria-current="page" @endif>
                        {{ $summary->name }}
                        @if ($summary->servingHours !== [] && $live->isServing($summary))
                            <span class="s-dot" title="{{ __('menu.serving_now') }}"></span>
                        @endif
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="s-tools">
            <label class="s-search">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zM20 20l-4-4"></path></svg>
                <span class="menu-sr">{{ __('menu.search') }}</span>
                <input type="search" x-model.debounce.150ms="query" placeholder="{{ __('menu.search_placeholder') }}" autocomplete="off" enterkeyhint="search">
            </label>
            @if (count($menu->menu->categories) > 1 && ! $theme->categoryTiles())
                <nav class="s-chips" aria-label="{{ __('menu.categories') }}" x-show="query === ''">
                    @foreach ($menu->menu->categories as $category)
                        <a href="#{{ $category->anchor }}" @click.prevent="jump('{{ $category->anchor }}')">{{ $category->name }}</a>
                    @endforeach
                </nav>
            @endif
        </div>

        @if ($menu->menu->featured !== [] && $theme->option('show_featured') !== false)
            <section class="s-board" x-show="query === '' && cat === null" aria-labelledby="s-featured-title">
                <div class="s-board__head">
                    <h2 id="s-featured-title">{{ __('menu.featured') }}</h2>
                </div>
                <ul class="s-board__list">
                    @foreach ($menu->menu->featured as $item)
                        <li>
                            <button type="button" class="s-board__row {{ $live->isSoldOut($item) || ! $item->isAvailable ? 'is-muted' : '' }}" @click="show('{{ $item->publicId }}')" aria-haspopup="dialog">
                                <span class="s-board__body">
                                    <span class="s-board__name">
                                        {{ $item->name }}
                                        @if ($item->isPopular)
                                            <span class="s-pill s-pill--light">{{ __('menu.popular') }}</span>
                                        @endif
                                    </span>
                                    @if ($item->description)
                                        <span class="s-board__desc s-clamp">{{ $item->description }}</span>
                                    @endif
                                </span>
                                <x-menu.price :item="$item" class="menu-price s-board__price" />
                            </button>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($theme->categoryTiles())
            <x-menu.category-tiles :categories="$menu->menu->categories" />
        @endif

        @forelse ($menu->menu->categories as $category)
            <section id="{{ $category->anchor }}" class="s-section" x-show="visible($el)" @if ($theme->categoryTiles()) x-cloak @endif
                     data-search="{{ collect($category->items)->map(fn ($i) => mb_strtolower($i->name.' '.$i->description))->implode('|') }}">
                <div class="s-section__head">
                    <svg width="22" height="12" viewBox="0 0 22 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M1 6c3-4 5-4 7 0s4 4 6 0 4-4 7 0"></path></svg>
                    <h2 class="s-heading">{{ $category->name }}</h2>
                </div>
                @if ($category->description)
                    <p class="s-section__desc">{{ $category->description }}</p>
                @endif
                @if ($theme->option('show_images') !== false && collect($category->items)->contains(fn ($i) => $i->imageUrl !== null))
                    <ul class="s-list">
                        @foreach ($category->items as $item)
                            @include('themes.coastal.partials.item', ['card' => false])
                        @endforeach
                    </ul>
                @else
                    <ul class="s-grid">
                        @foreach ($category->items as $item)
                            @include('themes.coastal.partials.item', ['card' => true])
                        @endforeach
                    </ul>
                @endif
            </section>
        @empty
            <p class="s-empty">{{ __('menu.empty_menu') }}</p>
        @endforelse

        <p class="s-empty" x-show="query !== '' && ! matches($root.dataset.search)" x-cloak>{{ __('menu.no_results') }}</p>

        @include('themes.coastal.partials.footer')

        <x-menu.dock :menu="$menu" :cart="$theme->cartEnabled()" />

        @foreach ($menu->menu->categories as $category)
            @foreach ($category->items as $item)
                @include('themes.coastal.partials.sheet')
            @endforeach
        @endforeach
    </div>
@endsection
