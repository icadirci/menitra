{{--
    Gallery (grid) theme — menu page. Design reference: docs/design/source/MenuPetal + DetailPetal.
    Inputs: $menu (MenuSnapshot), $live (LiveMenuState), $theme (ThemeSettings). No other data source.
    Layout options ($theme->option()): show_featured, card_ratio (square | portrait).
--}}
@extends('layouts.menu')

@section('title', __('menu.menu_of', ['name' => $menu->brand->name]).' · '.$menu->menu->name)
@section('description', \Illuminate\Support\Str::limit((string) $menu->brand->description, 160))

@section('content')
    <div class="g-page g-page--{{ $theme->option('card_ratio') === 'portrait' ? 'portrait' : 'square' }}" x-data="menuPage" data-search="{{ collect($menu->menu->categories)->flatMap(fn ($c) => $c->items)->map(fn ($i) => mb_strtolower($i->name.' '.$i->description))->implode('|') }}">
        @include('themes.grid.partials.header')

        @if (count($menu->menus) > 1)
            <nav class="g-menus" aria-label="{{ __('menu.menus') }}">
                @foreach ($menu->menus as $summary)
                    <a href="{{ $summary->url }}?lang={{ $menu->locale }}" @if ($summary->slug === $menu->menu->slug) aria-current="page" @endif>
                        {{ $summary->name }}
                        @if ($summary->servingHours !== [] && $live->isServing($summary))
                            <span class="g-dot" title="{{ __('menu.serving_now') }}"></span>
                        @endif
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="g-tools">
            <label class="g-search">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zM20 20l-4-4"></path></svg>
                <span class="menu-sr">{{ __('menu.search') }}</span>
                <input type="search" x-model.debounce.150ms="query" placeholder="{{ __('menu.search_placeholder') }}" autocomplete="off" enterkeyhint="search">
            </label>
            @if (count($menu->menu->categories) > 1)
                <nav class="g-chips" aria-label="{{ __('menu.categories') }}" x-show="query === ''">
                    @foreach ($menu->menu->categories as $category)
                        <a href="#{{ $category->anchor }}"><span aria-hidden="true"></span>{{ $category->name }}</a>
                    @endforeach
                </nav>
            @endif
        </div>

        @if ($menu->menu->featured !== [] && $theme->option('show_featured') !== false)
            <section class="g-featured" x-show="query === ''" aria-labelledby="g-featured-title">
                <h2 id="g-featured-title">{{ __('menu.featured') }}</h2>
                <ul class="g-featured__list">
                    @foreach ($menu->menu->featured as $item)
                        <li>
                            <button type="button" class="g-feature" @click="show('{{ $item->publicId }}')" aria-haspopup="dialog">
                                <span class="g-feature__pic">
                                    @include('themes.grid.partials.picture', ['url' => $item->imageUrl, 'label' => $item->name, 'width' => 110, 'height' => 110])
                                </span>
                                <span class="g-feature__body">
                                    @if ($item->isPopular)
                                        <span class="g-kicker">{{ __('menu.popular') }}</span>
                                    @endif
                                    <span class="g-feature__name">{{ $item->name }}</span>
                                    <x-menu.price :item="$item" class="menu-price g-price" />
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @forelse ($menu->menu->categories as $category)
            <section id="{{ $category->anchor }}" class="g-section" x-show="matches($el.dataset.search)"
                     data-search="{{ collect($category->items)->map(fn ($i) => mb_strtolower($i->name.' '.$i->description))->implode('|') }}">
                <h2>{{ $category->name }}</h2>
                @if ($category->description)
                    <p class="g-section__desc">{{ $category->description }}</p>
                @endif
                <ul class="g-grid">
                    @foreach ($category->items as $item)
                        @include('themes.grid.partials.item')
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="g-empty">{{ __('menu.empty_menu') }}</p>
        @endforelse

        <p class="g-empty" x-show="query !== '' && ! matches($root.dataset.search)" x-cloak>{{ __('menu.no_results') }}</p>

        @include('themes.grid.partials.footer')

        @foreach ($menu->menu->categories as $category)
            @foreach ($category->items as $item)
                @include('themes.grid.partials.sheet')
            @endforeach
        @endforeach
    </div>
@endsection
