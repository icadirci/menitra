{{--
    Zen theme — menu page. Design reference: docs/design/source/PremiumKori + PremiumDetailKori.
    Inputs: $menu (MenuSnapshot), $live (LiveMenuState), $theme (ThemeSettings). No other data source.
    Layout options ($theme->option()): category_layout (list | tiles, via $theme->categoryTiles()), cart (order list, via $theme->cartEnabled()), show_images, show_featured,
    course_numbers (running 01, 02 … numbers, CSS counters), allergen_filter ("I avoid" chips that mark items containing the chosen allergens, client-side only).
--}}
@extends('layouts.menu')

@section('title', __('menu.menu_of', ['name' => $menu->brand->name]).' · '.$menu->menu->name)
@section('description', \Illuminate\Support\Str::limit((string) $menu->brand->description, 160))

@section('content')
    <div class="z-page {{ $theme->option('course_numbers') !== false ? 'z-page--numbered' : '' }}" x-data="menuPage({{ $theme->categoryTiles() ? 'true' : 'false' }})" @if ($theme->cartEnabled()) data-cart="{{ $menu->location->url }}" data-currency="{{ $menu->brand->currency }}" @endif data-search="{{ collect($menu->menu->categories)->flatMap(fn ($c) => $c->items)->map(fn ($i) => mb_strtolower($i->name.' '.$i->description))->implode('|') }}">
        @include('themes.zen.partials.header')

        <div class="z-tools">
            @if (count($menu->menus) > 1)
                <nav class="z-menus" aria-label="{{ __('menu.menus') }}">
                    @foreach ($menu->menus as $summary)
                        <a href="{{ $summary->url }}?lang={{ $menu->locale }}" @if ($summary->slug === $menu->menu->slug) aria-current="page" @endif>
                            {{ $summary->name }}
                            @if ($summary->servingHours !== [] && $live->isServing($summary))
                                <span class="z-dot" title="{{ __('menu.serving_now') }}"></span>
                            @endif
                        </a>
                    @endforeach
                </nav>
            @endif
            <label class="z-search">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zM20 20l-4-4"></path></svg>
                <span class="menu-sr">{{ __('menu.search') }}</span>
                <input type="search" x-model.debounce.150ms="query" placeholder="{{ __('menu.search_placeholder') }}" autocomplete="off" enterkeyhint="search">
            </label>
            @if (count($menu->menu->categories) > 1 && ! $theme->categoryTiles())
                <nav class="z-chips" aria-label="{{ __('menu.categories') }}" x-show="query === ''">
                    @foreach ($menu->menu->categories as $category)
                        <a href="#{{ $category->anchor }}" @click.prevent="jump('{{ $category->anchor }}')">{{ $category->name }}</a>
                    @endforeach
                </nav>
            @endif
        </div>

        @if ($menu->menu->featured !== [] && $theme->option('show_featured') !== false)
            <section class="z-featured" x-show="query === '' && cat === null" aria-labelledby="z-featured-title">
                <h2 id="z-featured-title" class="z-featured__title">{{ __('menu.featured') }}</h2>
                <ul class="z-featured__list">
                    @foreach ($menu->menu->featured as $item)
                        <li>
                            <button type="button" class="z-featured__row" @click="show('{{ $item->publicId }}')" aria-haspopup="dialog">
                                <span class="z-featured__body">
                                    <span class="z-featured__name">{{ $item->name }}</span>
                                    @if ($item->description)
                                        <span class="z-featured__desc z-clamp">{{ $item->description }}</span>
                                    @endif
                                </span>
                                <x-menu.price :item="$item" class="menu-price z-featured__price" />
                            </button>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($theme->categoryTiles())
            <x-menu.category-tiles :categories="$menu->menu->categories" />
        @endif

        {{-- The "I avoid" choice lives only in this scope (never stored or sent). --}}
        <div class="z-courses" x-data="{ avoid: [] }">
            @if ($theme->option('allergen_filter') !== false && $menu->menu->allergens() !== [])
                <section class="z-avoid" aria-labelledby="z-avoid-title" x-show="query === ''">
                    <h2 id="z-avoid-title" class="z-label">{{ __('menu.avoid_title') }}</h2>
                    <div class="z-avoid__chips">
                        @foreach ($menu->menu->allergens() as $allergen)
                            <button type="button" class="z-chip" :aria-pressed="avoid.includes('{{ $allergen->key }}').toString()"
                                    @click="avoid = avoid.includes('{{ $allergen->key }}') ? avoid.filter(a => a !== '{{ $allergen->key }}') : [...avoid, '{{ $allergen->key }}']">{{ $allergen->label }}</button>
                        @endforeach
                    </div>
                    <p class="z-avoid__hint" x-show="avoid.length > 0" x-cloak>{{ __('menu.avoid_hint') }}</p>
                </section>
            @endif

            @forelse ($menu->menu->categories as $category)
                <section id="{{ $category->anchor }}" class="z-section" x-show="visible($el)" @if ($theme->categoryTiles()) x-cloak @endif
                         data-search="{{ collect($category->items)->map(fn ($i) => mb_strtolower($i->name.' '.$i->description))->implode('|') }}">
                    <div class="z-section__head">
                        <h2 class="z-heading">{{ $category->name }}</h2>
                        <span class="z-section__count">{{ count($category->items) }}</span>
                    </div>
                    @if ($category->description)
                        <p class="z-section__desc">{{ $category->description }}</p>
                    @endif
                    <ol class="z-items">
                        @foreach ($category->items as $item)
                            @include('themes.zen.partials.item')
                        @endforeach
                    </ol>
                </section>
            @empty
                <p class="z-empty">{{ __('menu.empty_menu') }}</p>
            @endforelse
        </div>

        <p class="z-empty" x-show="query !== '' && ! matches($root.dataset.search)" x-cloak>{{ __('menu.no_results') }}</p>

        @include('themes.zen.partials.footer')

        <x-menu.dock :menu="$menu" :cart="$theme->cartEnabled()" />

        @foreach ($menu->menu->categories as $category)
            @foreach ($category->items as $item)
                @include('themes.zen.partials.sheet')
            @endforeach
        @endforeach
    </div>
@endsection
