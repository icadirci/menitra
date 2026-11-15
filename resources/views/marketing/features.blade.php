@extends('layouts.marketing')

@php
    use App\Enums\MenuTheme;
    use App\Support\MarketingUrl;

    $trialDays = (int) config('subscriptions.trial_days');

    // Theme swatches (identity illustration only, see components/marketing/menu-preview): MenuTheme value => colours and type sample.
    $swatches = [
        'cafe' => ['bg' => '#F3ECE2', 'ink' => '#3B2A20', 'dots' => ['#3B2A20', '#A0714F', '#FBF7F1'], 'font' => "font-family: 'Inter', sans-serif; font-weight: 800"],
        'bistro' => ['bg' => '#0F0E0C', 'ink' => '#EDE6D8', 'dots' => ['#C9A96E', '#EDE6D8', '#3A352D'], 'font' => 'font-family: Georgia, serif; font-style: italic'],
        'minimal' => ['bg' => '#FFFFFF', 'ink' => '#111111', 'dots' => ['#111111', '#B5401C', '#F2F1EE'], 'font' => "font-family: 'Inter', sans-serif; font-weight: 700; letter-spacing: -0.04em"],
        'grid' => ['bg' => '#FFF6F3', 'ink' => '#3D2A35', 'dots' => ['#F6C9D0', '#CFEBDD', '#FBE7A6'], 'font' => 'font-family: Georgia, serif; font-weight: 700'],
    ];
@endphp

@section('title', __('marketing.meta.features_title'))
@section('description', __('marketing.meta.features_description'))
@section('page', 'features')

@section('content')
    {{-- Hero --}}
    <section class="px-5 pt-[clamp(56px,8vw,104px)] pb-[clamp(48px,6vw,80px)] text-center sm:px-6">
        <div class="mx-auto flex max-w-[860px] flex-col items-center gap-[22px]">
            <p class="text-[13px] font-semibold tracking-[0.12em] text-forest-700 uppercase">{{ __('marketing.features_page.eyebrow') }}</p>
            <h1 class="font-display text-[clamp(40px,5.6vw,72px)] leading-none font-bold tracking-[-0.045em] text-balance">{{ __('marketing.features_page.title') }}</h1>
            <p class="max-w-[600px] text-[clamp(17px,1.5vw,19px)] text-pretty text-[#4C514E]">{{ __('marketing.features_page.lead') }}</p>
            <div class="flex flex-wrap justify-center gap-3">
                <x-ui.button :href="route('register')" size="lg">{{ __('marketing.features_page.cta_primary') }}</x-ui.button>
                <x-ui.button :href="MarketingUrl::to('themes')" variant="secondary" size="lg">{{ __('marketing.features_page.cta_secondary') }}</x-ui.button>
            </div>
        </div>
    </section>

    {{-- QR codes --}}
    <x-marketing.feature-row
        :badge="__('marketing.features_page.qr.badge')"
        :title="__('marketing.features_page.qr.title')"
        :body="__('marketing.features_page.qr.body')"
        :points="__('marketing.features_page.qr.points')"
    >
        <div class="flex flex-wrap items-end justify-center gap-5 rounded-[32px] bg-[#E9E1D2] p-[clamp(32px,5vw,56px)]">
            <figure class="flex w-[210px] flex-col items-center gap-3.5 rounded-[20px_20px_6px_6px] bg-white px-5 pt-[22px] pb-5 text-center shadow-[0_30px_50px_-28px_rgb(21_25_26/0.5)]">
                <figcaption class="sr-only">{{ __('marketing.features_page.qr.card_label') }}</figcaption>
                <p class="font-display text-lg font-bold tracking-[-0.02em]" aria-hidden="true">{{ __('marketing.themes.cafe.venue') }}</p>
                <svg width="140" height="140" viewBox="-1 -1 23 23" aria-hidden="true"><rect x="-1" y="-1" width="23" height="23" fill="#FFFFFF" /><path fill="#3B2A20" d="M0 0h7v7H0zM14 0h7v7h-7zM0 14h7v7H0z" /><path fill="#FFFFFF" d="M1 1h5v5H1zM15 1h5v5h-5zM1 15h5v5H1z" /><path fill="#3B2A20" d="M2 2h3v3H2zM16 2h3v3h-3zM2 16h3v3H2zM8 1h1v1H8zM10 0h1v2h-1zM12 2h1v1h-1zM9 3h2v1H9zM8 5h1v2H8zM11 5h2v1h-2zM10 7h1v1h-1zM12 8h1v2h-1zM0 8h2v1H0zM3 8h1v1H3zM5 9h2v1H5zM2 10h1v2H2zM0 12h1v1H0zM4 11h1v1H4zM6 12h1v1H6zM8 9h2v1H8zM9 11h1v2H9zM11 11h2v1h-2zM14 8h1v1h-1zM16 9h2v1h-2zM19 8h2v1h-2zM15 11h1v2h-1zM17 12h1v1h-1zM20 11h1v1h-1zM8 14h1v1H8zM10 15h2v1h-2zM8 17h1v2H8zM11 18h1v1h-1zM9 20h3v1H9zM14 14h2v1h-2zM17 15h1v1h-1zM19 14h2v2h-2zM15 17h3v1h-3zM14 19h1v2h-1zM16 19h1v1h-1zM18 18h1v1h-1zM20 19h1v2h-1zM13 12h1v1h-1zM13 16h1v1h-1z" /></svg>
                <p class="text-[13px] leading-snug text-muted" aria-hidden="true">{{ __('marketing.features_page.qr.card_body') }}</p>
                <p class="rounded-full bg-[#F3ECE2] px-3 py-1 text-xs font-bold text-[#3B2A20]" aria-hidden="true">{{ __('marketing.features_page.qr.card_branch') }}</p>
            </figure>
            <div class="box-border flex aspect-square w-[120px] flex-col items-center justify-center gap-2 rounded-full bg-ink p-3.5 text-paper" aria-hidden="true">
                <svg width="48" height="48" viewBox="-1 -1 23 23"><rect x="-1" y="-1" width="23" height="23" rx="2" fill="#F6F5F1" /><path fill="#15191A" d="M0 0h7v7H0zM14 0h7v7h-7zM0 14h7v7H0z" /><path fill="#F6F5F1" d="M1 1h5v5H1zM15 1h5v5h-5zM1 15h5v5H1z" /><path fill="#15191A" d="M2 2h3v3H2zM16 2h3v3h-3zM2 16h3v3H2zM9 3h2v1H9zM8 9h2v1H8zM11 11h2v1h-2zM14 14h2v1h-2zM15 17h3v1h-3zM10 15h2v1h-2z" /></svg>
                <span class="text-center text-[11px] leading-tight font-semibold">{{ __('marketing.features_page.qr.deep_link') }}</span>
            </div>
        </div>
    </x-marketing.feature-row>

    {{-- Mobile --}}
    <x-marketing.feature-row
        reverse
        :badge="__('marketing.features_page.mobile.badge')"
        :title="__('marketing.features_page.mobile.title')"
        :body="__('marketing.features_page.mobile.body')"
        :points="__('marketing.features_page.mobile.points')"
    >
        <div class="flex h-[520px] justify-center overflow-hidden rounded-[32px] border border-line bg-white px-6 pt-[clamp(32px,5vw,48px)]">
            <x-marketing.phone :width="270" class="shadow-none" :label="__('marketing.themes.minimal.venue').' — '.__('marketing.themes.minimal.kind')">
                <x-marketing.menu-preview theme="minimal" />
            </x-marketing.phone>
        </div>
    </x-marketing.feature-row>

    {{-- Languages --}}
    <x-marketing.feature-row
        :badge="__('marketing.features_page.languages.badge')"
        :title="__('marketing.features_page.languages.title')"
        :body="__('marketing.features_page.languages.body')"
        :points="__('marketing.features_page.languages.points')"
    >
        <div class="flex flex-col gap-3 rounded-[32px] bg-[#23443A] p-[clamp(24px,4vw,44px)]">
            <div class="flex gap-1 self-start rounded-full bg-[#F4EBDD]/12 p-1" aria-hidden="true">
                @foreach (array_keys(config('locales.supported')) as $locale)
                    <span lang="{{ $locale }}" @class([
                        'rounded-full px-3.5 py-1.5 text-[13px] uppercase',
                        'bg-[#F4EBDD] font-bold text-[#23443A]' => $loop->first,
                        'font-semibold text-[#F4EBDD]' => ! $loop->first,
                    ])>{{ $locale }}</span>
                @endforeach
            </div>
            @foreach (__('marketing.features_page.languages.demo') as $dish)
                <div lang="{{ $dish['locale'] }}" @class([
                    'flex items-center gap-3.5 rounded-[18px] p-3.5',
                    'bg-[#F4EBDD]' => $loop->first,
                    'bg-[#F4EBDD]/90' => ! $loop->first,
                ])>
                    <div class="w-[60px] shrink-0 overflow-hidden rounded-[30px_30px_8px_8px]">
                        <x-marketing.dish kind="turkish" bg="#E9D9C2" ratio="4 / 5" pad="4%" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ $dish['name'] }}</p>
                        <p class="text-[13px] text-[#6B5446]">{{ $dish['note'] }}</p>
                    </div>
                    <span class="font-bold text-[#9C4A2F]">{{ $dish['price'] }}</span>
                </div>
            @endforeach
        </div>
    </x-marketing.feature-row>

    {{-- Branding --}}
    <x-marketing.feature-row
        reverse
        :badge="__('marketing.features_page.branding.badge')"
        :title="__('marketing.features_page.branding.title')"
        :body="__('marketing.features_page.branding.body')"
        :points="__('marketing.features_page.branding.points')"
    >
        <div class="grid grid-cols-2 gap-3.5 rounded-[32px] border border-line bg-white p-[clamp(24px,4vw,44px)]">
            <div class="col-span-full flex items-center justify-between text-[13px] font-medium text-muted">
                <span>{{ __('marketing.features_page.branding.themes_label') }}</span>
                <span>{{ __('marketing.features_page.branding.themes_note', ['count' => count(MenuTheme::cases())]) }}</span>
            </div>
            @foreach ($swatches as $theme => $swatch)
                <div @class([
                    'flex flex-col gap-2.5 rounded-[18px] p-4',
                    'border-2 border-forest-700' => $loop->first,
                    'border border-line' => $theme === 'minimal',
                ]) style="background: {{ $swatch['bg'] }}; color: {{ $swatch['ink'] }}">
                    <div class="flex gap-1.5" aria-hidden="true">
                        @foreach ($swatch['dots'] as $dot)
                            <span class="size-[22px] rounded-full ring-1 ring-black/10" style="background: {{ $dot }}"></span>
                        @endforeach
                    </div>
                    <span class="text-2xl" style="{{ $swatch['font'] }}" aria-hidden="true">Aa</span>
                    <span class="text-[13px] font-semibold">{{ __('marketing.themes.'.$theme.'.kind') }}</span>
                </div>
            @endforeach
            <div class="col-span-full flex items-center gap-3.5 rounded-[18px] border-[1.5px] border-dashed border-[#C7C4B9] p-3.5">
                <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-[14px] bg-[#F1EFE9] text-muted">
                    <x-ui.icon name="image" :size="22" />
                </span>
                <span class="text-sm text-muted">
                    <strong class="font-semibold text-ink">{{ __('marketing.features_page.branding.logo_title') }}</strong><br>
                    {{ __('marketing.features_page.branding.logo_body') }}
                </span>
            </div>
        </div>
    </x-marketing.feature-row>

    {{-- Details --}}
    <section class="border-y border-line bg-white px-5 py-[clamp(56px,7vw,96px)] sm:px-6">
        <div class="mx-auto flex max-w-[1200px] flex-col gap-10">
            <h2 class="max-w-[640px] font-display text-[clamp(30px,3.4vw,44px)] leading-[1.06] font-bold tracking-[-0.03em] text-balance">{{ __('marketing.features_page.details.title') }}</h2>
            <ul class="grid grid-cols-[repeat(auto-fit,minmax(min(340px,100%),1fr))] gap-4">
                @foreach (__('marketing.features_page.details.items') as $detail)
                    <li class="flex flex-col gap-4 rounded-[22px] border border-[#ECEAE3] bg-paper p-7">
                        <span class="inline-flex size-12 items-center justify-center rounded-[14px] border border-line bg-white text-forest-700">
                            <x-ui.icon :name="$detail['icon']" :size="24" :stroke="1.7" />
                        </span>
                        <div class="flex flex-col gap-1.5">
                            <h3 class="font-display text-[21px] font-bold tracking-[-0.02em]">{{ $detail['title'] }}</h3>
                            <p class="text-[15px] text-pretty text-muted">{{ $detail['body'] }}</p>
                        </div>
                        <ul class="flex flex-wrap gap-1.5">
                            @foreach ($detail['tags'] as $tag)
                                <li class="rounded-full border border-line bg-white px-2.5 py-1 text-[12.5px] font-medium text-graphite">{{ $tag }}</li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- CTA --}}
    <section class="px-5 py-[clamp(56px,7vw,96px)] sm:px-6">
        <div class="mx-auto flex max-w-[1200px] flex-wrap items-center justify-between gap-7 rounded-[36px] bg-forest-700 p-[clamp(40px,6vw,72px)] text-paper">
            <div class="flex max-w-[600px] flex-col gap-2.5">
                <h2 class="font-display text-[clamp(30px,3.6vw,46px)] leading-[1.04] font-bold tracking-[-0.035em] text-balance">{{ __('marketing.features_page.cta.title') }}</h2>
                <p class="text-[#CFE0D6]">{{ __('marketing.features_page.cta.lead', ['days' => $trialDays]) }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <x-ui.button :href="route('register')" variant="accent" size="lg" class="font-bold">{{ __('marketing.features_page.cta.primary') }}</x-ui.button>
                <a href="{{ MarketingUrl::to('pricing') }}" class="inline-flex min-h-14 items-center rounded-button border border-paper/35 px-7 font-semibold text-paper no-underline hover:border-paper/70 hover:text-paper">{{ __('marketing.features_page.cta.secondary') }}</a>
            </div>
        </div>
    </section>
@endsection
