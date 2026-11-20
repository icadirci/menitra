{{--
    Lounge theme — menu page. Design reference: docs/design/source/PremiumLumen + PremiumDetailLumen.
    Inputs: $menu (MenuSnapshot), $live (LiveMenuState), $theme (ThemeSettings). No other data source.
    Layout options ($theme->option()): category_layout (list | tiles, via $theme->categoryTiles()), cart (order list, via $theme->cartEnabled()), show_images, show_featured.
--}}
@extends('layouts.menu')

@section('title', __('menu.menu_of', ['name' => $menu->brand->name]).' · '.$menu->menu->name)
@section('description', \Illuminate\Support\Str::limit((string) $menu->brand->description, 160))

@section('content')
    <div class="l-page" x-data="menuPage({{ $theme->categoryTiles() ? 'true' : 'false' }})" @if ($theme->cartEnabled()) data-cart="{{ $menu->location->url }}" data-currency="{{ $menu->brand->currency }}" @endif data-search="{{ collect($menu->menu->categories)->flatMap(fn ($c) => $c->items)->map(fn ($i) => mb_strtolower($i->name.' '.$i->description))->implode('|') }}">
        @include('themes.lounge.partials.header')

        @if (count($menu->menus) > 1)
            <nav class="l-menus" aria-label="{{ __('menu.menus') }}">
                @foreach ($menu->menus as $summary)
                    <a href="{{ $summary->url }}?lang={{ $menu->locale }}" @if ($summary->slug === $menu->menu->slug) aria-current="page" @endif>
                        {{ $summary->name }}
                        @if ($summary->servingHours !== [] && $live->isServing($summary))
                            <span class="l-dot" title="{{ __('menu.serving_now') }}"></span>
                        @endif
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="l-tools">
            <label class="l-search">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zM20 20l-4-4"></path></svg>
                <span class="menu-sr">{{ __('menu.search') }}</span>
                <input type="search" x-model.debounce.150ms="query" placeholder="{{ __('menu.search_placeholder') }}" autocomplete="off" enterkeyhint="search">
            </label>
            @if (count($menu->menu->categories) > 1 && ! $theme->categoryTiles())
                <nav class="l-chips" aria-label="{{ __('menu.categories') }}" x-show="query === ''">
                    @foreach ($menu->menu->categories as $category)
                        <a href="#{{ $category->anchor }}" @click.prevent="jump('{{ $category->anchor }}')">{{ $category->name }}</a>
                    @endforeach
                </nav>
            @endif
        </div>

        @if ($menu->menu->featured !== [] && $theme->option('show_featured') !== false)
            <section class="l-featured" x-show="query === '' && cat === null" aria-labelledby="l-featured-title">
                <h2 id="l-featured-title" class="l-label">{{ __('menu.featured') }}</h2>
                <ul class="l-featured__list">
                    @foreach ($menu->menu->featured as $item)
                        <li>
                            <button type="button" class="l-pick {{ $live->isSoldOut($item) || ! $item->isAvailable ? 'is-muted' : '' }}" @click="show('{{ $item->publicId }}')" aria-haspopup="dialog">
                                @if ($theme->option('show_images') !== false && $item->imageUrl)
                                    <span class="l-pick__pic">
                                        <img src="{{ $item->imageUrl }}" alt="" width="140" height="187" loading="lazy" decoding="async">
                                    </span>
                                @endif
                                <span class="l-pick__body">
                                    @if ($item->isPopular)
                                        <span class="l-badge">{{ __('menu.popular') }}</span>
                                    @endif
                                    <span class="l-pick__name">{{ $item->name }}</span>
                                    @if ($item->description)
                                        <span class="l-pick__desc l-clamp">{{ $item->description }}</span>
                                    @endif
                                    <x-menu.price :item="$item" class="menu-price l-price" />
                                </span>
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
            <section id="{{ $category->anchor }}" class="l-section" x-show="visible($el)" @if ($theme->categoryTiles()) x-cloak @endif
                     data-search="{{ collect($category->items)->map(fn ($i) => mb_strtolower($i->name.' '.$i->description))->implode('|') }}">
                <div class="l-section__head">
                    <span class="l-diamond" aria-hidden="true"></span>
                    <h2 class="l-heading">{{ $category->name }}</h2>
                    <span class="l-rule" aria-hidden="true"></span>
                    <span class="l-count">{{ count($category->items) }}</span>
                </div>
                @if ($category->description)
                    <p class="l-section__desc">{{ $category->description }}</p>
                @endif
                <ul class="l-items">
                    @foreach ($category->items as $item)
                        @include('themes.lounge.partials.item')
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="l-empty">{{ __('menu.empty_menu') }}</p>
        @endforelse

        <p class="l-empty l-empty--box" x-show="query !== '' && ! matches($root.dataset.search)" x-cloak>{{ __('menu.no_results') }}</p>

        @include('themes.lounge.partials.footer')

        <x-menu.dock :menu="$menu" :cart="$theme->cartEnabled()" />

        @foreach ($menu->menu->categories as $category)
            @foreach ($category->items as $item)
                @include('themes.lounge.partials.sheet')
            @endforeach
        @endforeach
    </div>
@endsection
