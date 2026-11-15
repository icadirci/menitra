@extends('layouts.marketing')

@php
    use App\Support\MarketingUrl;

    $trialDays = (int) config('subscriptions.trial_days');
    $stepIcons = ['layers', 'sliders', 'qr'];
    $benefitIcons = ['bolt', 'image', 'globe', 'sparkle'];
    // Gallery order follows the design: cafe, bistro, minimal, grid, classic (App\Enums\MenuTheme values).
    $galleryThemes = ['cafe', 'bistro', 'minimal', 'grid', 'classic'];
    $avatarTints = ['#E9DCCB', '#E4EEE8', '#F4E4C4'];
@endphp

@section('title', __('marketing.meta.home_title'))
@section('description', __('marketing.meta.home_description'))
@section('page', 'home')

@section('content')
    {{-- Hero --}}
    <section class="px-5 pt-[clamp(48px,7vw,96px)] pb-[clamp(64px,8vw,112px)] sm:px-6">
        <div class="mx-auto flex max-w-[1200px] flex-wrap items-center gap-[clamp(40px,6vw,80px)]">
            <div class="flex min-w-0 flex-[1_1_480px] flex-col gap-7">
                <span class="inline-flex items-center gap-2 self-start rounded-full bg-forest-100 py-1.5 pr-3.5 pl-2 text-sm font-semibold text-forest-700">
                    <span class="inline-flex size-[22px] items-center justify-center rounded-full bg-forest-700 text-paper">
                        <x-ui.icon name="qr" :size="12" :stroke="2.4" />
                    </span>
                    {{ __('marketing.hero.badge') }}
                </span>
                <h1 class="font-display text-[clamp(44px,6.2vw,80px)] leading-[0.98] font-bold tracking-[-0.045em] text-balance">{{ __('marketing.hero.title') }}</h1>
                <p class="max-w-[520px] text-[clamp(17px,1.5vw,19px)] text-pretty text-[#4C514E]">{{ __('marketing.hero.lead') }}</p>
                <div class="flex flex-wrap gap-3">
                    <x-ui.button :href="route('register')" size="lg">
                        {{ __('marketing.hero.cta_primary') }}
                        <x-ui.icon name="arrow-right" :size="18" :stroke="2" />
                    </x-ui.button>
                    <x-ui.button :href="MarketingUrl::to('pricing')" variant="secondary" size="lg">{{ __('marketing.hero.cta_secondary') }}</x-ui.button>
                </div>
                <ul class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-muted">
                    @foreach (__('marketing.hero.trust') as $point)
                        <li class="inline-flex items-center gap-1.5">
                            <x-ui.icon name="check" :size="16" :stroke="2.2" class="text-forest-700" />
                            {{ __('marketing.hero.trust.'.$loop->index, ['days' => $trialDays]) }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="relative flex min-w-0 flex-[1_1_400px] justify-center py-6">
                <div aria-hidden="true" class="absolute inset-[40px_6%_0_6%] rounded-[40px] bg-[#E9E1D2]"></div>
                <x-marketing.phone :width="300" :label="__('marketing.themes.cafe.venue').' — '.__('marketing.themes.cafe.kind')">
                    <x-marketing.menu-preview theme="cafe" />
                </x-marketing.phone>

                <div class="absolute bottom-16 left-[max(0px,calc(50%-270px))] hidden w-[168px] flex-col items-center gap-2.5 rounded-card bg-white p-4 shadow-[0_24px_48px_-20px_rgb(21_25_26/0.35)] sm:flex">
                    <svg width="104" height="104" viewBox="-1 -1 23 23" role="img" aria-label="{{ __('marketing.hero.qr_label') }}"><rect x="-1" y="-1" width="23" height="23" fill="#FFFFFF" /><path fill="#15191A" d="M0 0h7v7H0zM14 0h7v7h-7zM0 14h7v7H0z" /><path fill="#FFFFFF" d="M1 1h5v5H1zM15 1h5v5h-5zM1 15h5v5H1z" /><path fill="#15191A" d="M2 2h3v3H2zM16 2h3v3h-3zM2 16h3v3H2zM8 1h1v1H8zM10 0h1v2h-1zM12 2h1v1h-1zM9 3h2v1H9zM8 5h1v2H8zM11 5h2v1h-2zM10 7h1v1h-1zM12 8h1v2h-1zM0 8h2v1H0zM3 8h1v1H3zM5 9h2v1H5zM2 10h1v2H2zM0 12h1v1H0zM4 11h1v1H4zM6 12h1v1H6zM8 9h2v1H8zM9 11h1v2H9zM11 11h2v1h-2zM14 8h1v1h-1zM16 9h2v1h-2zM19 8h2v1h-2zM15 11h1v2h-1zM17 12h1v1h-1zM20 11h1v1h-1zM8 14h1v1H8zM10 15h2v1h-2zM8 17h1v2H8zM11 18h1v1h-1zM9 20h3v1H9zM14 14h2v1h-2zM17 15h1v1h-1zM19 14h2v2h-2zM15 17h3v1h-3zM14 19h1v2h-1zM16 19h1v1h-1zM18 18h1v1h-1zM20 19h1v2h-1zM13 12h1v1h-1zM13 16h1v1h-1z" /></svg>
                    <div class="text-center leading-tight">
                        <p class="font-display text-[15px] font-bold">{{ __('marketing.hero.qr_title') }}</p>
                        <p class="text-xs text-muted">{{ __('marketing.hero.qr_body') }}</p>
                    </div>
                </div>

                <div class="absolute top-[120px] right-[max(0px,calc(50%-280px))] hidden items-center gap-2.5 rounded-2xl bg-ink px-3.5 py-3 text-paper shadow-[0_20px_40px_-18px_rgb(21_25_26/0.5)] sm:flex">
                    <span class="inline-flex size-7 items-center justify-center rounded-full bg-saffron-500 text-ink">
                        <x-ui.icon name="bolt" :size="14" :stroke="2.6" />
                    </span>
                    <span class="text-[13px] leading-snug">
                        <strong class="font-semibold">{{ __('marketing.hero.toast_title') }}</strong><br>
                        <span class="text-[#B9BDB9]">{{ __('marketing.hero.toast_body') }}</span>
                    </span>
                </div>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section id="how" class="border-y border-line bg-white px-5 py-[clamp(64px,8vw,112px)] sm:px-6">
        <div class="mx-auto flex max-w-[1200px] flex-col gap-14">
            <div class="flex flex-wrap items-end justify-between gap-6">
                <div class="flex max-w-[620px] flex-col gap-3.5">
                    <p class="text-[13px] font-semibold tracking-[0.12em] text-forest-700 uppercase">{{ __('marketing.how.eyebrow') }}</p>
                    <h2 class="font-display text-[clamp(34px,4vw,52px)] leading-[1.04] font-bold tracking-[-0.035em] text-balance">{{ __('marketing.how.title') }}</h2>
                </div>
                <p class="max-w-[380px] text-muted">{{ __('marketing.how.lead') }}</p>
            </div>
            <ol class="grid grid-cols-[repeat(auto-fit,minmax(min(300px,100%),1fr))] gap-6">
                @foreach (__('marketing.how.steps') as $step)
                    <li class="flex flex-col gap-5 rounded-3xl border border-[#ECEAE3] bg-paper p-8">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex size-[52px] items-center justify-center rounded-2xl border border-line bg-white text-forest-700">
                                <x-ui.icon :name="$stepIcons[$loop->index] ?? 'check'" :size="24" :stroke="1.7" />
                            </span>
                            <span class="font-display text-[44px] font-semibold tracking-[-0.04em] text-[#CFCCC1]" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        </div>
                        <div class="flex flex-col gap-2">
                            <h3 class="font-display text-2xl font-bold tracking-[-0.02em]">{{ $step['title'] }}</h3>
                            <p class="text-pretty text-muted">{{ $step['body'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Benefits --}}
    <section class="px-5 py-[clamp(64px,8vw,120px)] sm:px-6">
        <div class="mx-auto flex max-w-[1200px] flex-wrap items-center gap-[clamp(40px,6vw,88px)]">
            <div class="flex min-w-0 flex-[1_1_460px] flex-col gap-10">
                <div class="flex flex-col gap-3.5">
                    <p class="text-[13px] font-semibold tracking-[0.12em] text-forest-700 uppercase">{{ __('marketing.benefits.eyebrow') }}</p>
                    <h2 class="font-display text-[clamp(34px,4vw,52px)] leading-[1.04] font-bold tracking-[-0.035em] text-balance">{{ __('marketing.benefits.title') }}</h2>
                </div>
                <div class="grid grid-cols-[repeat(auto-fit,minmax(min(240px,100%),1fr))] gap-x-7 gap-y-8">
                    @foreach (__('marketing.benefits.items') as $benefit)
                        <div class="flex flex-col gap-2.5">
                            <x-ui.icon :name="$benefitIcons[$loop->index] ?? 'check'" :size="26" :stroke="1.7" class="text-forest-700" />
                            <h3 class="text-lg font-semibold tracking-[-0.01em]">{{ $benefit['title'] }}</h3>
                            <p class="text-[15px] text-pretty text-muted">{{ $benefit['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex min-w-0 flex-[1_1_420px] flex-col gap-3.5 rounded-[32px] bg-forest-700 p-[clamp(24px,4vw,48px)]" role="img" aria-label="{{ __('marketing.benefits.live.label') }}">
                <div class="flex items-center justify-between text-[13px] font-medium text-[#CFE0D6]" aria-hidden="true">
                    <span>{{ __('marketing.benefits.live.label') }}</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-[#7FD3A0]"></span>{{ __('marketing.benefits.live.synced') }}</span>
                </div>
                @foreach (__('marketing.benefits.live.items') as $item)
                    <div class="flex items-center gap-3.5 rounded-[18px] bg-white p-3.5" aria-hidden="true">
                        <div @class(['w-16 shrink-0 overflow-hidden rounded-input', 'opacity-55' => $item['price'] === null])>
                            <x-marketing.dish :kind="$item['dish']" :bg="$item['bg']" pad="8%" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p @class(['font-semibold', 'text-[#6B6F6C]' => $item['price'] === null])>{{ $item['name'] }}</p>
                            <p class="text-[13px] text-muted">{{ $item['note'] }}</p>
                        </div>
                        @if ($item['price'] === null)
                            <span class="rounded-full bg-[#F3E7E4] px-2.5 py-1 text-xs font-semibold text-[#8A2E1B]">{{ __('marketing.benefits.live.sold_out') }}</span>
                        @else
                            <div class="text-right">
                                @if ($item['old_price'])
                                    <p class="text-[13px] text-[#6B6F6C] line-through">{{ $item['old_price'] }}</p>
                                @endif
                                <p class="font-bold text-forest-700">{{ $item['price'] }}</p>
                            </div>
                        @endif
                    </div>
                @endforeach
                <div class="mt-1.5 flex items-center gap-3 rounded-2xl bg-forest-900 px-4 py-3.5 text-sm text-paper" aria-hidden="true">
                    <x-ui.icon name="bolt" :size="18" :stroke="2" class="text-saffron-500" />
                    <span>{{ __('marketing.benefits.live.published') }}</span>
                </div>
            </div>
        </div>
    </section>

    {{-- Features overview --}}
    <section id="features" class="scroll-mt-24 px-5 pb-[clamp(64px,8vw,120px)] sm:px-6">
        <div class="mx-auto flex max-w-[1200px] flex-col gap-10">
            <h2 class="max-w-[640px] font-display text-[clamp(30px,3.4vw,44px)] leading-[1.06] font-bold tracking-[-0.03em] text-balance">{{ __('marketing.features.title') }}</h2>
            <ul class="grid grid-cols-[repeat(auto-fit,minmax(min(260px,100%),1fr))] gap-4">
                @foreach (__('marketing.features.items') as $feature)
                    <li class="flex flex-col gap-3.5 rounded-card border border-line bg-white p-6">
                        <span class="inline-flex size-11 items-center justify-center rounded-input bg-forest-100 text-forest-700">
                            <x-ui.icon :name="$feature['icon']" :size="22" />
                        </span>
                        <div class="flex flex-col gap-1">
                            <h3 class="font-semibold">{{ $feature['title'] }}</h3>
                            <p class="text-sm text-muted">{{ $feature['body'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Menu gallery --}}
    <section id="examples" class="scroll-mt-20 bg-[#121614] py-[clamp(72px,9vw,128px)] text-paper">
        <div class="mx-auto flex max-w-[1200px] flex-col items-center gap-3.5 px-5 text-center sm:px-6">
            <p class="text-[13px] font-semibold tracking-[0.12em] text-saffron-500 uppercase">{{ __('marketing.gallery.eyebrow') }}</p>
            <h2 class="max-w-[760px] font-display text-[clamp(34px,4.4vw,56px)] leading-[1.02] font-bold tracking-[-0.035em] text-balance">{{ __('marketing.gallery.title') }}</h2>
            <p class="max-w-[560px] text-[#B4B9B5]">{{ __('marketing.gallery.lead') }}</p>
        </div>
        <ul class="mx-auto mt-14 flex max-w-[1248px] justify-[safe_center] gap-5 overflow-x-auto px-5 pb-2 sm:px-6" aria-label="{{ __('marketing.gallery.note') }}">
            @foreach ($galleryThemes as $theme)
                <li class="flex shrink-0 flex-col items-center gap-4">
                    <x-marketing.phone :width="212" class="bg-[#2A302C] shadow-none" :label="__('marketing.themes.'.$theme.'.venue').' — '.__('marketing.themes.'.$theme.'.kind')">
                        <x-marketing.menu-preview :theme="$theme" />
                    </x-marketing.phone>
                    <div class="text-center leading-snug" aria-hidden="true">
                        <p class="text-[15px] font-semibold">{{ __('marketing.themes.'.$theme.'.venue') }}</p>
                        <p class="text-[13px] text-[#9CA19D]">{{ __('marketing.themes.'.$theme.'.kind') }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="mt-10 flex justify-center px-5">
            <a href="{{ \App\Support\MarketingUrl::to('themes') }}" class="inline-flex min-h-11 items-center gap-2 rounded-input px-4 font-semibold text-saffron-500 underline-offset-4 hover:underline">{{ __('marketing.gallery.cta') }} →</a>
        </div>
    </section>

    {{-- Testimonials (managed in /admin; hidden when none are published) --}}
    @if ($testimonials->isNotEmpty())
        <section class="px-5 py-[clamp(64px,8vw,120px)] sm:px-6">
            <div class="mx-auto flex max-w-[1200px] flex-col gap-10">
                <h2 class="max-w-[620px] font-display text-[clamp(30px,3.4vw,44px)] leading-[1.06] font-bold tracking-[-0.03em] text-balance">{{ __('marketing.testimonials.title') }}</h2>
                <div class="grid grid-cols-[repeat(auto-fit,minmax(min(320px,100%),1fr))] gap-5">
                    @foreach ($testimonials as $testimonial)
                        <figure class="flex flex-col justify-between gap-8 rounded-3xl border border-line bg-white p-8">
                            <blockquote class="font-display text-[21px] leading-[1.35] tracking-[-0.015em] text-pretty">“{{ $testimonial['quote'] }}”</blockquote>
                            <figcaption class="flex items-center gap-3.5">
                                <span class="inline-flex size-11 items-center justify-center rounded-full text-[15px] font-bold text-ink" style="background: {{ $avatarTints[$loop->index % count($avatarTints)] }}" aria-hidden="true">{{ $testimonial['initials'] }}</span>
                                <span class="leading-snug">
                                    <span class="block font-semibold">{{ $testimonial['name'] }}</span>
                                    @if ($testimonial['role'])
                                        <span class="block text-sm text-muted">{{ $testimonial['role'] }}</span>
                                    @endif
                                </span>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Pricing preview (plans and prices managed in /admin) --}}
    @if ($plans->isNotEmpty())
        <section id="pricing" class="scroll-mt-20 border-y border-line bg-white px-5 py-[clamp(64px,8vw,112px)] sm:px-6">
            <div class="mx-auto flex max-w-[1200px] flex-col gap-12">
                <div class="flex flex-col items-center gap-3.5 text-center">
                    <p class="text-[13px] font-semibold tracking-[0.12em] text-forest-700 uppercase">{{ __('marketing.pricing.eyebrow') }}</p>
                    <h2 class="font-display text-[clamp(34px,4vw,52px)] leading-[1.04] font-bold tracking-[-0.035em]">{{ __('marketing.pricing.title') }}</h2>
                    <p class="text-muted">{{ __('marketing.pricing.lead') }}</p>
                </div>
                <div class="grid grid-cols-[repeat(auto-fit,minmax(min(300px,100%),1fr))] items-stretch gap-5">
                    @foreach ($plans as $plan)
                        <div @class([
                            'flex flex-col gap-[18px] rounded-3xl p-8',
                            'bg-ink text-paper shadow-[0_30px_60px_-30px_rgb(21_25_26/0.6)]' => $plan->featured,
                            'border border-line bg-paper text-ink' => ! $plan->featured,
                        ])>
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="font-display text-2xl font-bold tracking-[-0.02em]">{{ $plan->name }}</h3>
                                @if ($plan->featured)
                                    <span class="rounded-full bg-saffron-500 px-3 py-1 text-xs font-bold text-ink">{{ __('marketing.pricing.most_popular') }}</span>
                                @endif
                            </div>
                            @if ($plan->description)
                                <p class="text-[15px] opacity-80">{{ $plan->description }}</p>
                            @endif
                            <div class="flex items-baseline gap-1.5">
                                <span class="font-display text-[52px] leading-none font-bold tracking-[-0.04em]">{{ $plan->monthly->format(omitZeroFraction: true) }}</span>
                                <span class="opacity-75">{{ __('marketing.pricing.per_month') }}</span>
                            </div>
                            @if ($plan->yearlyPerMonth)
                                <p class="-mt-2 text-sm opacity-80">
                                    {{ __('marketing.pricing.yearly_note', ['price' => $plan->yearlyPerMonth->format(omitZeroFraction: true)]) }}
                                    @if ($plan->yearlySavingPercent)
                                        <span @class(['ml-1 rounded-full px-2 py-0.5 text-xs font-semibold', 'bg-white/10 text-saffron-500' => $plan->featured, 'bg-forest-100 text-forest-700' => ! $plan->featured])>{{ __('marketing.pricing.yearly_saving', ['percent' => $plan->yearlySavingPercent]) }}</span>
                                    @endif
                                </p>
                            @endif
                            <a
                                href="{{ route('register') }}"
                                @class([
                                    'mt-auto inline-flex h-[50px] items-center justify-center rounded-input font-semibold no-underline',
                                    'bg-saffron-500 text-ink hover:bg-[#D9932C]' => $plan->featured,
                                    'border border-[#DAD8CF] bg-white text-ink hover:border-ink/40' => ! $plan->featured,
                                ])
                            >{{ __('marketing.pricing.start_trial') }}</a>
                        </div>
                    @endforeach
                </div>
                <x-ui.button :href="MarketingUrl::to('pricing')" variant="ghost" class="self-center">
                    {{ __('marketing.pricing.compare_all') }}
                    <x-ui.icon name="arrow-right" :size="18" :stroke="2" />
                </x-ui.button>
            </div>
        </section>
    @endif

    {{-- FAQ (managed in /admin) --}}
    @if ($faqs->isNotEmpty())
        <section id="faq" class="scroll-mt-20 px-5 py-[clamp(64px,8vw,120px)] sm:px-6">
            <div class="mx-auto flex max-w-[1200px] flex-wrap gap-12">
                <div class="flex flex-[1_1_320px] flex-col gap-4">
                    <h2 class="font-display text-[clamp(34px,4vw,52px)] leading-[1.04] font-bold tracking-[-0.035em]">{{ __('marketing.faq.title') }}</h2>
                    <p class="max-w-[340px] text-muted">
                        {{ __('marketing.faq.lead') }}
                        <a href="{{ MarketingUrl::to('contact') }}" class="font-semibold">{{ __('marketing.faq.contact_link') }}</a>
                        {{ __('marketing.faq.lead_after') }}
                    </p>
                </div>
                <div class="flex min-w-0 flex-[2_1_560px] flex-col gap-3">
                    @foreach ($faqs as $faq)
                        <details class="group rounded-[18px] border border-line bg-white">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-[22px] text-[17px] font-semibold [&::-webkit-details-marker]:hidden">
                                {{ $faq['question'] }}
                                <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-[#F1EFE9] transition-transform group-open:rotate-45" aria-hidden="true">
                                    <x-ui.icon name="plus" :size="16" :stroke="2" />
                                </span>
                            </summary>
                            <p class="max-w-[640px] px-6 pb-6 whitespace-pre-line text-muted">{{ $faq['answer'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- CTA --}}
    <section class="px-5 pb-[clamp(64px,8vw,112px)] sm:px-6 {{ $faqs->isEmpty() ? 'pt-[clamp(64px,8vw,112px)]' : '' }}">
        <div class="mx-auto flex max-w-[1200px] flex-wrap items-center justify-between gap-8 rounded-[36px] bg-forest-700 p-[clamp(32px,6vw,80px)] text-paper">
            <div class="flex max-w-[620px] flex-col gap-3">
                <h2 class="font-display text-[clamp(32px,4vw,52px)] leading-[1.02] font-bold tracking-[-0.035em] text-balance">{{ __('marketing.cta.title') }}</h2>
                <p class="text-lg text-[#CFE0D6]">{{ __('marketing.cta.lead', ['days' => $trialDays]) }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <x-ui.button :href="route('register')" variant="accent" size="lg" class="font-bold">{{ __('marketing.cta.primary') }}</x-ui.button>
                <x-ui.button :href="MarketingUrl::to('contact')" variant="inverse" size="lg">{{ __('marketing.cta.secondary') }}</x-ui.button>
            </div>
        </div>
    </section>
@endsection
