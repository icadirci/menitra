@props(['page' => 'home'])

@php
    use App\Support\MarketingUrl;

    $columns = [
        __('marketing.footer.product') => [
            ['label' => __('marketing.nav.features'), 'href' => MarketingUrl::to('features')],
            ['label' => __('marketing.nav.pricing'), 'href' => MarketingUrl::to('home', fragment: 'pricing')],
            ['label' => __('marketing.nav.examples'), 'href' => MarketingUrl::to('home', fragment: 'examples')],
        ],
        __('marketing.footer.account') => [
            ['label' => __('marketing.footer.register'), 'href' => route('register')],
            ['label' => __('marketing.footer.login'), 'href' => route('login')],
            ['label' => __('marketing.footer.reset'), 'href' => route('password.request')],
        ],
    ];
@endphp

<footer class="bg-ink text-paper">
    <div class="mx-auto flex max-w-[1200px] flex-col gap-12 px-5 pt-16 pb-10 sm:px-6">
        <div class="flex flex-wrap justify-between gap-10">
            <div class="flex max-w-[340px] flex-col gap-4">
                <a href="{{ MarketingUrl::to('home') }}" class="text-paper no-underline" aria-label="{{ __('auth.home_link') }}">
                    <x-ui.logo />
                </a>
                <p class="text-[15px] text-[#A9AEAA]">{{ __('marketing.footer.tagline') }}</p>
            </div>

            <div class="flex flex-wrap gap-x-16 gap-y-8">
                @foreach ($columns as $heading => $links)
                    <div class="flex flex-col gap-3">
                        <h2 class="text-[13px] font-semibold tracking-[0.12em] text-[#8E938F] uppercase">{{ $heading }}</h2>
                        @foreach ($links as $link)
                            <a href="{{ $link['href'] }}" class="text-[15px] text-paper no-underline hover:text-saffron-500">{{ $link['label'] }}</a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-white/10 pt-6 text-sm text-[#A9AEAA]">
            <span>{{ __('marketing.footer.copyright', ['year' => now()->year]) }}</span>
            <x-marketing.language-switcher :page="$page" dark />
        </div>
    </div>
</footer>
