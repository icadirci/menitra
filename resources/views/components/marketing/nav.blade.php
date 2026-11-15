@props(['page' => 'home'])

@php
    use App\Support\MarketingUrl;

    // Dedicated pages carry 'page' (marked as current); the rest are home page sections until their pages exist.
    $links = [
        ['label' => __('marketing.nav.features'), 'href' => MarketingUrl::to('features'), 'page' => 'features'],
        ['label' => __('marketing.nav.examples'), 'href' => MarketingUrl::to('home', fragment: 'examples')],
        ['label' => __('marketing.nav.pricing'), 'href' => MarketingUrl::to('pricing'), 'page' => 'pricing'],
        ['label' => __('marketing.nav.faq'), 'href' => MarketingUrl::to('home', fragment: 'faq')],
        ['label' => __('marketing.nav.contact'), 'href' => MarketingUrl::to('contact'), 'page' => 'contact'],
    ];
@endphp

<header
    class="sticky top-0 z-40 border-b border-line bg-paper/95 backdrop-blur supports-[backdrop-filter]:bg-paper/85"
    x-data="{ open: false }"
    x-on:keydown.escape.window="open = false"
>
    <div class="mx-auto flex h-[76px] max-w-[1200px] items-center justify-between gap-6 px-5 sm:px-6">
        <a href="{{ MarketingUrl::to('home') }}" class="text-ink no-underline" aria-label="{{ __('auth.home_link') }}">
            <x-ui.logo />
        </a>

        <nav class="hidden items-center gap-1 md:flex" aria-label="{{ __('marketing.nav.label') }}">
            @foreach ($links as $link)
                <a
                    href="{{ $link['href'] }}"
                    @if (($link['page'] ?? null) === $page) aria-current="page" @endif
                    @class([
                        'rounded-input px-3.5 py-2.5 text-[15px] font-medium no-underline hover:bg-forest-100 hover:text-forest-900',
                        'bg-forest-100 text-forest-900' => ($link['page'] ?? null) === $page,
                        'text-graphite' => ($link['page'] ?? null) !== $page,
                    ])
                >{{ $link['label'] }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            <x-marketing.language-switcher :page="$page" class="hidden lg:flex" />
            <a href="{{ route('login') }}" class="hidden h-11 items-center rounded-input px-4 text-[15px] font-semibold text-ink no-underline hover:bg-white sm:inline-flex">{{ __('marketing.nav.login') }}</a>
            <x-ui.button :href="route('register')" size="sm" class="shadow-none">{{ __('marketing.nav.get_started') }}</x-ui.button>
            <button
                type="button"
                class="inline-flex size-11 cursor-pointer items-center justify-center rounded-input border border-[#DAD8CF] bg-white text-ink md:hidden"
                x-on:click="open = ! open"
                x-bind:aria-expanded="open.toString()"
                aria-controls="mobile-nav"
            >
                <span class="sr-only" x-text="open ? @js(__('marketing.nav.close_menu')) : @js(__('marketing.nav.open_menu'))">{{ __('marketing.nav.open_menu') }}</span>
                <x-ui.icon name="menu" x-show="! open" />
                <x-ui.icon name="close" x-show="open" x-cloak />
            </button>
        </div>
    </div>

    <div id="mobile-nav" class="border-t border-line bg-paper md:hidden" x-show="open" x-cloak x-transition.opacity>
        <nav class="mx-auto flex max-w-[1200px] flex-col gap-1 px-5 py-4" aria-label="{{ __('marketing.nav.label') }}">
            @foreach ($links as $link)
                <a
                    href="{{ $link['href'] }}"
                    @if (($link['page'] ?? null) === $page) aria-current="page" @endif
                    @class([
                        'flex min-h-12 items-center rounded-input px-3 text-base font-semibold text-ink no-underline hover:bg-forest-100',
                        'bg-forest-100' => ($link['page'] ?? null) === $page,
                    ])
                    x-on:click="open = false"
                >{{ $link['label'] }}</a>
            @endforeach
            <a href="{{ route('login') }}" class="flex min-h-12 items-center rounded-input px-3 text-base font-semibold text-ink no-underline hover:bg-forest-100">{{ __('marketing.nav.login') }}</a>
            <x-marketing.language-switcher :page="$page" class="mt-2 px-3" />
        </nav>
    </div>
</header>
