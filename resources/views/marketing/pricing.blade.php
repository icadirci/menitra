@extends('layouts.marketing')

@php
    $trialDays = (int) config('subscriptions.trial_days');
    $hasYearly = $plans->contains(fn ($plan) => $plan->yearly !== null);
    $comparisonGroups = array_filter($comparison);
@endphp

@section('title', __('marketing.meta.pricing_title'))
@section('description', __('marketing.meta.pricing_description'))
@section('page', 'pricing')

@section('content')
    {{-- Plans and prices are managed in /admin (MarketingContent); without JS the monthly prices are shown. --}}
    <div x-data="{ yearly: false }">
        {{-- Hero + billing toggle --}}
        <section class="px-5 pt-[clamp(56px,8vw,96px)] pb-10 text-center sm:px-6">
            <div class="mx-auto flex max-w-[820px] flex-col items-center gap-5">
                <p class="text-[13px] font-semibold tracking-[0.12em] text-forest-700 uppercase">{{ __('marketing.pricing_page.eyebrow') }}</p>
                <h1 class="font-display text-[clamp(40px,5.6vw,68px)] leading-none font-bold tracking-[-0.045em] text-balance">{{ __('marketing.pricing_page.title') }}</h1>
                <p class="max-w-[560px] text-lg text-pretty text-[#4C514E]">{{ __('marketing.pricing_page.lead', ['days' => $trialDays]) }}</p>

                @if ($hasYearly)
                    <div
                        role="radiogroup"
                        aria-label="{{ __('marketing.pricing_page.billing_label') }}"
                        class="mt-2 inline-flex items-center gap-1 rounded-full border border-line bg-white p-[5px]"
                        x-cloak
                        x-on:keydown.arrow-left.prevent="yearly = false; $refs.monthly.focus()"
                        x-on:keydown.arrow-right.prevent="yearly = true; $refs.yearly.focus()"
                    >
                        <button
                            type="button"
                            role="radio"
                            x-ref="monthly"
                            class="inline-flex h-11 cursor-pointer items-center gap-2 rounded-full px-5 text-[15px] font-semibold transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-forest-700/20"
                            x-bind:class="yearly ? 'text-graphite hover:text-ink' : 'bg-ink text-white'"
                            x-bind:aria-checked="(! yearly).toString()"
                            x-bind:tabindex="yearly ? -1 : 0"
                            x-on:click="yearly = false"
                        >{{ __('marketing.pricing_page.monthly') }}</button>
                        <button
                            type="button"
                            role="radio"
                            x-ref="yearly"
                            class="inline-flex h-11 cursor-pointer items-center gap-2 rounded-full px-5 text-[15px] font-semibold transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-forest-700/20"
                            x-bind:class="yearly ? 'bg-ink text-white' : 'text-graphite hover:text-ink'"
                            x-bind:aria-checked="yearly.toString()"
                            x-bind:tabindex="yearly ? 0 : -1"
                            x-on:click="yearly = true"
                        >
                            {{ __('marketing.pricing_page.yearly') }}
                            @if ($yearlySaving)
                                <span class="rounded-full bg-saffron-500 px-2 py-[3px] text-xs font-bold text-ink">{{ __($yearlySaving['uniform'] ? 'marketing.pricing_page.saving_badge' : 'marketing.pricing_page.saving_badge_up_to', ['percent' => $yearlySaving['percent']]) }}</span>
                            @endif
                        </button>
                    </div>
                @endif
            </div>
        </section>

        {{-- Plan cards --}}
        <section class="px-5 pt-4 pb-[clamp(56px,7vw,96px)] sm:px-6">
            @if ($plans->isEmpty())
                <div class="mx-auto flex max-w-[640px] flex-col items-center gap-6 rounded-[28px] border border-line bg-white p-10 text-center">
                    <p class="text-muted">{{ __('marketing.pricing_page.empty') }}</p>
                    <x-ui.button :href="route('register')">{{ __('marketing.pricing_page.start_trial', ['days' => $trialDays]) }}</x-ui.button>
                </div>
            @else
                <div class="mx-auto grid max-w-[1200px] grid-cols-[repeat(auto-fit,minmax(min(320px,100%),1fr))] items-stretch gap-5">
                    @foreach ($plans as $plan)
                        @php
                            // Displayed price per billing period; a plan without a yearly price keeps its monthly price.
                            $variants = [
                                'monthly' => [
                                    'price' => $plan->monthly,
                                    'per' => __('marketing.pricing_page.per_month'),
                                    'note' => $plan->yearly
                                        ? __('marketing.pricing_page.billed_monthly_or_yearly', ['price' => $plan->yearly->format(omitZeroFraction: true)])
                                        : __('marketing.pricing_page.billed_monthly'),
                                    'saving' => null,
                                ],
                                'yearly' => $plan->yearly ? [
                                    'price' => $plan->yearly,
                                    'per' => __('marketing.pricing_page.per_year'),
                                    'note' => __('marketing.pricing_page.billed_yearly', ['price' => $plan->yearlyPerMonth->format(omitZeroFraction: true)]),
                                    'saving' => $plan->yearlySavingPercent,
                                ] : [
                                    'price' => $plan->monthly,
                                    'per' => __('marketing.pricing_page.per_month'),
                                    'note' => __('marketing.pricing_page.monthly_only'),
                                    'saving' => null,
                                ],
                            ];
                            if (! $hasYearly) {
                                unset($variants['yearly']); // no toggle: monthly only, always visible
                            }
                            $highlights = $plan->highlights();
                        @endphp
                        <article @class([
                            'flex flex-col gap-5 rounded-[28px] px-8 py-9',
                            'bg-ink text-paper shadow-[0_40px_70px_-36px_rgb(21_25_26/0.7)] outline-[3px] -outline-offset-[3px] outline-saffron-500' => $plan->featured,
                            'border border-line bg-white text-ink' => ! $plan->featured,
                        ])>
                            <div class="flex items-center justify-between gap-3">
                                <h2 class="font-display text-[26px] font-bold tracking-[-0.02em]">{{ $plan->name }}</h2>
                                @if ($plan->featured)
                                    <span class="rounded-full bg-saffron-500 px-3 py-[5px] text-xs font-bold text-ink">{{ __('marketing.pricing_page.recommended') }}</span>
                                @endif
                            </div>
                            @if ($plan->description)
                                <p class="text-[15px] opacity-80 sm:min-h-12">{{ $plan->description }}</p>
                            @endif

                            @foreach ($variants as $period => $variant)
                                <div
                                    class="flex flex-col gap-1"
                                    @if ($hasYearly) x-show="{{ $period === 'yearly' ? 'yearly' : '! yearly' }}" @endif
                                    @if ($period === 'yearly') x-cloak @endif
                                >
                                    <p class="flex flex-wrap items-baseline gap-x-1.5">
                                        <span class="font-display text-[clamp(44px,5vw,60px)] leading-none font-bold tracking-[-0.045em]">{{ $variant['price']->format(omitZeroFraction: true) }}</span>
                                        <span class="opacity-70">{{ $variant['per'] }}</span>
                                    </p>
                                    <p class="text-sm opacity-70">{{ $variant['note'] }}</p>
                                    @if ($variant['saving'])
                                        <span @class([
                                            'mt-1 self-start rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                            'bg-white/10 text-saffron-500' => $plan->featured,
                                            'bg-forest-100 text-forest-700' => ! $plan->featured,
                                        ])>{{ __('marketing.pricing.yearly_saving', ['percent' => $variant['saving']]) }}</span>
                                    @endif
                                </div>
                            @endforeach

                            <a
                                href="{{ route('register', ['plan' => $plan->code]) }}"
                                @class([
                                    'inline-flex min-h-[54px] items-center justify-center rounded-input px-4 text-center font-semibold no-underline transition-colors',
                                    'bg-saffron-500 text-ink hover:bg-[#D9932C] hover:text-ink' => $plan->featured,
                                    'bg-forest-700 text-white hover:bg-forest-900 hover:text-white' => ! $plan->featured,
                                ])
                            >{{ __('marketing.pricing_page.start_trial', ['days' => $plan->trialDays ?? $trialDays]) }}</a>

                            @if ($highlights !== [])
                                <div @class(['h-px', 'bg-paper/15' => $plan->featured, 'bg-line' => ! $plan->featured])></div>
                                <h3 class="text-[13px] font-semibold tracking-[0.06em] uppercase opacity-70">{{ __('marketing.pricing_page.includes') }}</h3>
                                <ul class="flex flex-col gap-3 text-[15px]">
                                    @foreach ($highlights as $point)
                                        <li class="flex items-start gap-2.5">
                                            <x-ui.icon name="check" :stroke="2.2" @class(['mt-0.5', 'text-saffron-500' => $plan->featured, 'text-forest-700' => ! $plan->featured]) />
                                            <span>{{ $point }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </article>
                    @endforeach
                </div>
                <p class="mx-auto mt-6 max-w-[1180px] text-center text-sm text-muted">{{ __('marketing.pricing.vat_note') }}</p>
            @endif
        </section>
    </div>

    {{-- Comparison (rows from PlanFeature limits) --}}
    @if ($plans->isNotEmpty() && $comparisonGroups !== [])
        <section id="compare" class="scroll-mt-20 border-y border-line bg-white px-5 py-[clamp(56px,7vw,96px)] sm:px-6">
            <div class="mx-auto flex max-w-[1200px] flex-col gap-8">
                <h2 class="font-display text-[clamp(30px,3.4vw,44px)] leading-[1.06] font-bold tracking-[-0.03em]">{{ __('marketing.pricing_page.compare.title') }}</h2>
                <div class="overflow-x-auto rounded-[20px] border border-line" role="region" aria-label="{{ __('marketing.pricing_page.compare.label') }}" tabindex="0">
                    <table class="w-full border-collapse text-[15px]" style="min-width: {{ 240 + $plans->count() * 150 }}px">
                        <caption class="sr-only">{{ __('marketing.pricing_page.compare.label') }}</caption>
                        <thead class="bg-paper">
                            <tr>
                                <th scope="col" class="px-5 py-[18px] text-left font-semibold text-graphite">{{ __('marketing.pricing_page.compare.feature') }}</th>
                                @foreach ($plans as $plan)
                                    <th scope="col" @class([
                                        'px-3 py-[18px] text-center font-display text-lg font-bold',
                                        'bg-forest-100 text-forest-700' => $plan->featured,
                                    ])>{{ $plan->name }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        @foreach ($comparisonGroups as $group => $features)
                            <tbody>
                                <tr class="border-t border-line">
                                    <th scope="colgroup" colspan="{{ $plans->count() + 1 }}" class="px-5 pt-[22px] pb-2.5 text-left text-[13px] font-semibold tracking-[0.08em] text-forest-700 uppercase">{{ __('marketing.pricing_page.compare.'.$group) }}</th>
                                </tr>
                                @foreach ($features as $feature)
                                    <tr class="border-t border-[#F0EEE8]">
                                        <th scope="row" class="px-5 py-3.5 text-left font-medium">{{ $feature->label() }}</th>
                                        @foreach ($plans as $plan)
                                            <td @class(['px-3 py-3.5 text-center text-[#3A3F3C]', 'bg-[#F4F8F5]' => $plan->featured])>
                                                @if (! $plan->includes($feature))
                                                    <x-ui.icon name="minus" :size="16" :stroke="2" class="mx-auto text-[#A9ADAA]" />
                                                    <span class="sr-only">{{ __('marketing.pricing_page.compare.not_included') }}</span>
                                                @elseif ($feature->isBoolean())
                                                    <x-ui.icon name="check" :stroke="2.4" class="mx-auto text-forest-700" />
                                                    <span class="sr-only">{{ __('marketing.pricing_page.compare.included') }}</span>
                                                @else
                                                    {{ $plan->limitText($feature) }}
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        @endforeach
                    </table>
                </div>
            </div>
        </section>
    @endif

    {{-- Billing FAQ (placement "pricing", managed in /admin) --}}
    @if ($faqs->isNotEmpty())
        <section id="faq" class="scroll-mt-20 px-5 py-[clamp(56px,7vw,96px)] sm:px-6">
            <div class="mx-auto flex max-w-[1200px] flex-wrap gap-12">
                <div class="flex flex-[1_1_300px] flex-col gap-3.5">
                    <h2 class="font-display text-[clamp(30px,3.4vw,44px)] leading-[1.06] font-bold tracking-[-0.03em]">{{ __('marketing.pricing_page.faq.title') }}</h2>
                    <p class="text-graphite">{{ __('marketing.pricing_page.faq.lead') }}</p>
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
@endsection
