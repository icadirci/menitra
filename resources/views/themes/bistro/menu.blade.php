{{--
    Bistro theme — menu page. Design reference: docs/design/source/MenuArdoise + DetailArdoise.
    Inputs: $menu (MenuSnapshot), $live (LiveMenuState), $theme (ThemeSettings). No other data source.
    Layout options ($theme->option()): category_layout (list | tiles, via $theme->categoryTiles()), cart (order list, via $theme->cartEnabled()), show_images, show_featured.
--}}
@extends('layouts.menu')

@section('title', __('menu.menu_of', ['name' => $menu->brand->name]).' · '.$menu->menu->name)
@section('description', \Illuminate\Support\Str::limit((string) $menu->brand->description, 160))

@section('content')
    <div class="b-page" x-data="menuPage({{ $theme->categoryTiles() ? 'true' : 'false' }})" @if ($theme->cartEnabled()) data-cart="{{ $menu->location->url }}" data-currency="{{ $menu->brand->currency }}" @endif data-search="{{ collect($menu->menu->categories)->flatMap(fn ($c) => $c->items)->map(fn ($i) => mb_strtolower($i->name.' '.$i->description))->implode('|') }}">
        @include('themes.bistro.partials.header')

        @if (count($menu->menus) > 1)
            <nav class="b-menus" aria-label="{{ __('menu.menus') }}">
                @foreach ($menu->menus as $summary)
                    <a href="{{ $summary->url }}?lang={{ $menu->locale }}" @if ($summary->slug === $menu->menu->slug) aria-current="page" @endif>
                        {{ $summary->name }}
                        @if ($summary->servingHours !== [] && $live->isServing($summary))
                            <span class="b-dot" title="{{ __('menu.serving_now') }}"></span>
                        @endif
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="b-tools">
            <label class="b-search">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zM20 20l-4-4"></path></svg>
                <span class="menu-sr">{{ __('menu.search') }}</span>
                <input type="search" x-model.debounce.150ms="query" placeholder="{{ __('menu.search_placeholder') }}" autocomplete="off" enterkeyhint="search">
            </label>
            @if (count($menu->menu->categories) > 1 && ! $theme->categoryTiles())
                <nav class="b-cats" aria-label="{{ __('menu.categories') }}" x-show="query === ''">
                    @foreach ($menu->menu->categories as $category)
                        <a href="#{{ $category->anchor }}" @click.prevent="jump('{{ $category->anchor }}')">{{ $category->name }}</a>
                    @endforeach
                </nav>
            @endif
        </div>

        @if ($menu->menu->featured !== [] && $theme->option('show_featured') !== false)
            <section class="b-featured" x-show="query === '' && cat === null" aria-labelledby="b-featured-title">
                <h2 id="b-featured-title" class="menu-sr">{{ __('menu.featured') }}</h2>
                @foreach ($menu->menu->featured as $item)
                    <button type="button" class="b-signature" @click="show('{{ $item->publicId }}')" aria-haspopup="dialog">
                        @if ($theme->option('show_images') !== false && $item->imageUrl)
                            <span class="b-signature__pic">
                                <img src="{{ $item->imageUrl }}" alt="" width="520" height="325" loading="lazy" decoding="async">
                            </span>
                        @endif
                        <span class="b-signature__body">
                            <span class="b-label">{{ __('menu.featured') }}</span>
                            <span class="b-signature__line">
                                <span class="b-signature__name">{{ $item->name }}</span>
                                <x-menu.price :item="$item" class="menu-price b-price" />
                            </span>
                            @if ($item->description)
                                <span class="b-italic">{{ $item->description }}</span>
                            @endif
                        </span>
                    </button>
                @endforeach
            </section>
        @endif

        @if ($theme->categoryTiles())
            <x-menu.category-tiles :categories="$menu->menu->categories" />
        @endif

        @forelse ($menu->menu->categories as $category)
            <section id="{{ $category->anchor }}" class="b-section" x-show="visible($el)" @if ($theme->categoryTiles()) x-cloak @endif
                     data-search="{{ collect($category->items)->map(fn ($i) => mb_strtolower($i->name.' '.$i->description))->implode('|') }}">
                <div class="b-rule"><span aria-hidden="true"></span><h2>{{ $category->name }}</h2><span aria-hidden="true"></span></div>
                @if ($category->description)
                    <p class="b-section__desc b-italic">{{ $category->description }}</p>
                @endif
                <ul class="b-items">
                    @foreach ($category->items as $item)
                        @include('themes.bistro.partials.item')
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="b-empty">{{ __('menu.empty_menu') }}</p>
        @endforelse

        <p class="b-empty" x-show="query !== '' && ! matches($root.dataset.search)" x-cloak>{{ __('menu.no_results') }}</p>

        @include('themes.bistro.partials.footer')

        <x-menu.dock :menu="$menu" :cart="$theme->cartEnabled()" />

        @foreach ($menu->menu->categories as $category)
            @foreach ($category->items as $item)
                @include('themes.bistro.partials.sheet')
            @endforeach
        @endforeach
    </div>
@endsection
