@extends('layouts.marketing')

@section('title', __('shop.marketing.meta_title'))
@section('description', __('shop.marketing.meta_description'))
@section('page', 'stands')

@if ($products->isEmpty())
    @push('meta')
        <meta name="robots" content="noindex">
    @endpush
@endif

@section('content')
    <section class="px-5 pt-[clamp(56px,8vw,104px)] pb-[clamp(32px,4vw,56px)] text-center sm:px-6">
        <div class="mx-auto flex max-w-[760px] flex-col items-center gap-[22px]">
            <h1 class="font-display text-[clamp(40px,5.6vw,68px)] leading-none font-bold tracking-[-0.045em] text-balance">{{ __('shop.marketing.title') }}</h1>
            <p class="max-w-[600px] text-lg text-pretty text-[#4C514E]">{{ $products->isEmpty() ? __('shop.marketing.empty') : __('shop.marketing.lead') }}</p>
            @if ($products->isEmpty())
                <x-ui.button :href="route('register')" size="lg">{{ __('marketing.footer.register') }}</x-ui.button>
            @endif
        </div>
    </section>

    @if ($products->isNotEmpty())
        <section class="px-5 pb-[clamp(64px,8vw,112px)] sm:px-6">
            <ul class="mx-auto grid max-w-[1200px] grid-cols-[repeat(auto-fill,minmax(min(280px,100%),1fr))] gap-6">
                @foreach ($products as $product)
                    <li class="flex flex-col overflow-hidden rounded-[24px] border border-line bg-white">
                        @if ($product->imageUrl())
                            <img src="{{ $product->imageUrl() }}" alt="{{ $product->translate()?->name }}" width="600" height="450" loading="lazy" class="aspect-[4/3] w-full object-cover">
                        @endif
                        <div class="flex flex-1 flex-col gap-3 p-6">
                            <h2 class="font-display text-xl font-bold tracking-[-0.02em]">{{ $product->translate()?->name }}</h2>
                            @if ($product->translate()?->description)
                                <p class="text-[15px] whitespace-pre-line text-muted">{{ $product->translate()->description }}</p>
                            @endif
                            @if ($product->is_personalized)
                                <p class="text-sm font-semibold text-forest-700">{{ __('shop.personalized') }}</p>
                            @endif
                            <p class="mt-auto pt-2 font-display text-2xl font-bold">{{ $product->price()->format(omitZeroFraction: true) }} <span class="font-sans text-sm font-normal text-muted">{{ __('shop.per_unit') }}</span></p>
                            @if ($product->availableQuantity() > 0)
                                <x-ui.button :href="route('register', ['stand' => $product->public_id])">{{ __('shop.marketing.cta') }}</x-ui.button>
                            @else
                                <p class="text-sm text-muted">{{ __('shop.sold_out') }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <p class="mx-auto mt-8 max-w-[760px] text-center text-sm text-muted">
                {{ __('marketing.pricing.vat_note') }}
                @if ($settings->freeShippingFrom())
                    {{ __('shop.free_shipping_from', ['amount' => $settings->freeShippingFrom()->format(omitZeroFraction: true)]) }}
                @endif
                {{ $settings->deliveryText() }}
            </p>
            <p class="mt-3 text-center text-sm text-muted">{{ __('shop.marketing.existing') }} <a href="{{ route('login') }}" class="font-semibold text-forest-700">{{ __('marketing.footer.login') }}</a></p>
        </section>
    @endif
@endsection
