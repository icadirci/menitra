{{-- Error page on the central domain: marketing design (app.css + app.js). --}}
@extends('layouts.public')

@push('meta')
    <meta name="robots" content="noindex">
@endpush

@section('document_title'){{ $title }}@endsection

@section('body')
    <main class="flex min-h-screen flex-col items-center px-5 pt-9 pb-12" data-error-page="central">
        <a href="{{ url('/'.\App\Support\MarketingUrl::prefix(app()->getLocale())) }}" class="text-ink no-underline" aria-label="{{ __('auth.home_link') }}">
            <x-ui.logo :size="38" />
        </a>

        <div class="mt-12 flex w-full max-w-[460px] flex-col items-center gap-[22px] rounded-panel border border-line bg-white p-6 text-center shadow-card sm:p-10">
            <p class="font-display text-[56px] leading-none font-bold tracking-[-0.04em] text-forest-700">{{ $code }}</p>

            <div class="flex flex-col gap-2">
                <h1 class="font-display text-[32px] leading-[1.05] font-bold tracking-[-0.04em]">{{ $title }}</h1>
                <p class="text-muted">{{ $body }}</p>
            </div>

            <x-ui.button :href="url('/'.\App\Support\MarketingUrl::prefix(app()->getLocale()))">{{ __('errors.home') }}</x-ui.button>
        </div>
    </main>
@endsection
