{{--
    Category cards (layout option `category_layout` = `tiles`), shared by every theme.
    Shown on the menu's start view; a tap opens that category (menuPage.openCategory),
    the "all categories" button returns. Without a picture the card uses the theme's
    primary colour. Themes may set --menu-tile-radius.
--}}
@props(['categories'])

@if ($categories !== [])
    <nav class="menu-tiles" aria-label="{{ __('menu.categories') }}" x-show="query === '' && cat === null">
        <ul class="menu-tiles__grid">
            @foreach ($categories as $category)
                <li>
                    <a href="#{{ $category->anchor }}" class="menu-tile {{ $category->imageUrl ? 'menu-tile--image' : '' }}"
                       data-tile="{{ $category->anchor }}" @click.prevent="openCategory('{{ $category->anchor }}')">
                        @if ($category->imageUrl)
                            <img src="{{ $category->imageUrl }}" alt="" width="400" height="300" loading="{{ $loop->index < 4 ? 'eager' : 'lazy' }}" decoding="async">
                        @endif
                        <span class="menu-tile__name">{{ $category->name }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="menu-tiles-bar" x-show="query === '' && cat !== null" x-cloak>
        <button type="button" id="menu-tiles-back" class="menu-tiles-back" @click="closeCategory()">
            <span class="menu-flip-rtl" aria-hidden="true">←</span>
            {{ __('menu.all_categories') }}
        </button>
    </div>
@endif
