@extends('layouts.marketing')

{{--
    Privacy notice / terms of use. $document: privacy | terms. Text comes from
    lang/{locale}/legal.php (generic drafts; config marketing.legal_review_pending
    shows a visible notice until a lawyer has reviewed them).
--}}

@section('title', __("legal.{$document}.title"))
@section('description', __("legal.{$document}.description"))
@section('page', $document)

@section('content')
    <article class="px-5 pt-[clamp(48px,7vw,96px)] pb-[clamp(64px,8vw,112px)] sm:px-6">
        <div class="mx-auto flex max-w-[760px] flex-col gap-8">
            <header class="flex flex-col gap-3">
                <p class="text-[13px] font-semibold tracking-[0.12em] text-forest-700 uppercase">{{ __('legal.eyebrow') }}</p>
                <h1 class="font-display text-[clamp(36px,5vw,56px)] leading-none font-bold tracking-[-0.04em] text-balance">{{ __("legal.{$document}.title") }}</h1>
                <p class="text-sm text-[#575C59]">{{ __('legal.updated', ['date' => __("legal.{$document}.updated")]) }}</p>
            </header>

            @if (config('marketing.legal_review_pending'))
                <div class="rounded-2xl border border-saffron-500 bg-saffron-100 px-5 py-4 text-[15px] text-ink" role="note">
                    <strong>{{ __('legal.draft_title') }}</strong> {{ __('legal.draft_body') }}
                </div>
            @endif

            <p class="text-[17px] text-pretty text-[#4C514E]">{{ __("legal.{$document}.intro") }}</p>

            @foreach (__("legal.{$document}.sections") as $section)
                <section class="flex flex-col gap-3">
                    <h2 class="font-display text-[22px] font-bold tracking-[-0.02em]">{{ $section['title'] }}</h2>
                    @foreach ($section['paragraphs'] as $paragraph)
                        <p class="text-[16px] leading-relaxed text-pretty text-[#2E3330]">{{ $paragraph }}</p>
                    @endforeach
                    @if (! empty($section['items']))
                        <ul class="ml-5 flex list-disc flex-col gap-1.5 text-[16px] text-[#2E3330]">
                            @foreach ($section['items'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach

            <p class="border-t border-line pt-6 text-[15px] text-[#4C514E]">
                {{ __('legal.contact_lead') }}
                <a href="{{ \App\Support\MarketingUrl::to('contact') }}" class="font-semibold text-forest-700 underline-offset-4 hover:underline">{{ __('legal.contact_link') }}</a>
            </p>
        </div>
    </article>
@endsection
