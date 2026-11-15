@props(['page' => 'home'])

{{-- $contact (ContactDetails, /admin site settings) comes from the view composer in AppServiceProvider. --}}

@php
    use App\Support\MarketingUrl;

    $columns = [
        __('marketing.footer.product') => [
            ['label' => __('marketing.nav.features'), 'href' => MarketingUrl::to('features')],
            ['label' => __('marketing.nav.pricing'), 'href' => MarketingUrl::to('pricing')],
            ['label' => __('marketing.footer.themes'), 'href' => MarketingUrl::to('themes')],
        ],
        __('marketing.footer.account') => [
            ['label' => __('marketing.footer.register'), 'href' => route('register')],
            ['label' => __('marketing.footer.login'), 'href' => route('login')],
            ['label' => __('marketing.footer.reset'), 'href' => route('password.request')],
        ],
        __('marketing.footer.company') => [
            ['label' => __('marketing.footer.contact'), 'href' => MarketingUrl::to('contact')],
            ['label' => __('marketing.footer.help'), 'href' => MarketingUrl::to('home', fragment: 'faq')],
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

                @if ($contact->email || $contact->phone)
                    <ul class="flex flex-col gap-2 text-[15px]">
                        @if ($contact->email)
                            <li class="flex items-center gap-2.5">
                                <x-ui.icon name="mail" :size="18" class="text-[#8E938F]" />
                                <a href="mailto:{{ $contact->email }}" class="break-all text-paper no-underline hover:text-saffron-500">{{ $contact->email }}</a>
                            </li>
                        @endif
                        @if ($contact->phone)
                            <li class="flex items-center gap-2.5">
                                <x-ui.icon name="chat" :size="18" class="text-[#8E938F]" />
                                <a href="{{ $contact->telUrl() }}" class="text-paper no-underline hover:text-saffron-500">{{ $contact->phone }}</a>
                                @if ($contact->whatsappUrl())
                                    <a href="{{ $contact->whatsappUrl() }}" class="text-sm text-saffron-500 underline-offset-4 hover:underline" rel="noopener" target="_blank">WhatsApp</a>
                                @endif
                            </li>
                        @endif
                    </ul>
                @endif

                @if ($contact->socials !== [])
                    <ul class="flex flex-wrap gap-2" aria-label="{{ __('marketing.footer.social_label') }}">
                        @foreach ($contact->socials as $social)
                            <li>
                                <a
                                    href="{{ $social['url'] }}"
                                    class="inline-flex size-10 items-center justify-center rounded-input border border-white/10 text-[#E8E9E6] hover:border-saffron-500 hover:text-saffron-500"
                                    rel="noopener me"
                                    target="_blank"
                                    aria-label="{{ __('marketing.footer.social_link', ['network' => $social['network']->label()]) }}"
                                ><x-ui.icon :name="$social['network']->value" :size="18" /></a>
                            </li>
                        @endforeach
                    </ul>
                @endif
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
            <nav class="flex flex-wrap gap-x-5 gap-y-2" aria-label="{{ __('legal.eyebrow') }}">
                <a href="{{ MarketingUrl::to('privacy') }}" class="text-[#A9AEAA] no-underline hover:text-saffron-500">{{ __('marketing.footer.privacy') }}</a>
                <a href="{{ MarketingUrl::to('terms') }}" class="text-[#A9AEAA] no-underline hover:text-saffron-500">{{ __('marketing.footer.terms') }}</a>
            </nav>
            <x-marketing.language-switcher :page="$page" dark />
        </div>
    </div>
</footer>
