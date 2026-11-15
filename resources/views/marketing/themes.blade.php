@extends('layouts.marketing')

{{--
    Theme showcase. $themes: list<ShowcaseTheme> (ThemeShowcase). Venue names and
    links come from the demo businesses (DemoSeeder) through the public resolver;
    no menu content is written here.
--}}

@section('title', __('marketing.meta.themes_title'))
@section('description', __('marketing.meta.themes_description'))
@section('page', 'themes')

@section('content')
    <section class="px-5 pt-[clamp(56px,8vw,104px)] pb-[clamp(40px,5vw,64px)] text-center sm:px-6">
        <div class="mx-auto flex max-w-[860px] flex-col items-center gap-[22px]">
            <p class="text-[13px] font-semibold tracking-[0.12em] text-forest-700 uppercase">{{ __('marketing.showcase.eyebrow') }}</p>
            <h1 class="font-display text-[clamp(40px,5.6vw,72px)] leading-none font-bold tracking-[-0.045em] text-balance">{{ __('marketing.showcase.title') }}</h1>
            <p class="max-w-[620px] text-[clamp(17px,1.5vw,19px)] text-pretty text-[#4C514E]">{{ __('marketing.showcase.lead') }}</p>
        </div>
    </section>

    <section class="px-5 pb-[clamp(64px,8vw,112px)] sm:px-6" aria-label="{{ __('marketing.showcase.list_label') }}">
        <ul class="mx-auto grid max-w-[1200px] grid-cols-[repeat(auto-fit,minmax(min(300px,100%),1fr))] gap-5">
            @foreach ($themes as $entry)
                <li class="flex flex-col overflow-hidden rounded-3xl border border-line bg-white">
                    <div class="flex h-36 items-end gap-2 p-5" style="background: {{ $entry->colors['bg'] }}" aria-hidden="true">
                        @foreach (['primary', 'accent', 'surface', 'text', 'ornament'] as $swatch)
                            <span class="size-9 rounded-full border border-black/10" style="background: {{ $entry->colors[$swatch] }}"></span>
                        @endforeach
                    </div>
                    <div class="flex flex-1 flex-col gap-3 p-6">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h2 class="font-display text-2xl font-bold tracking-[-0.02em]">{{ __('marketing.showcase.themes.'.$entry->theme->value.'.name') }}</h2>
                            @if ($entry->theme->requiredFeature() !== null)
                                <span class="rounded-full bg-saffron-100 px-2.5 py-0.5 text-xs font-semibold text-ink">{{ __('marketing.showcase.premium') }}</span>
                            @endif
                        </div>
                        <p class="text-[15px] text-pretty text-[#4C514E]">{{ __('marketing.showcase.themes.'.$entry->theme->value.'.description') }}</p>
                        <div class="mt-auto pt-2">
                            @if ($entry->url)
                                <p class="mb-3 text-sm text-[#575C59]">{{ __('marketing.showcase.demo_venue', ['venue' => $entry->venue]) }}</p>
                                <x-ui.button :href="$entry->url" variant="secondary" target="_blank" rel="noopener">
                                    {{ __('marketing.showcase.open_demo') }}
                                    <span class="sr-only">({{ __('marketing.showcase.new_tab') }})</span>
                                </x-ui.button>
                            @else
                                <p class="text-sm text-[#575C59]">{{ __('marketing.showcase.no_demo') }}</p>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
        <p class="mx-auto mt-8 max-w-[720px] text-center text-sm text-[#575C59]">{{ __('marketing.showcase.note') }}</p>
    </section>

    <section class="px-5 pb-[clamp(72px,9vw,128px)] sm:px-6">
        <div class="mx-auto flex max-w-[860px] flex-col items-center gap-5 rounded-3xl bg-forest-700 px-6 py-12 text-center text-paper">
            <h2 class="font-display text-[clamp(28px,3.4vw,40px)] leading-tight font-bold tracking-[-0.03em] text-balance">{{ __('marketing.showcase.cta_title') }}</h2>
            <x-ui.button :href="route('register')" size="lg">{{ __('marketing.showcase.cta') }}</x-ui.button>
        </div>
    </section>
@endsection
