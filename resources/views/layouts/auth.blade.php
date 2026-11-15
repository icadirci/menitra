@extends('layouts.public')

{{--
    Centred card layout for single-purpose auth pages (forgot / reset password,
    email verification). Sections: title, subtitle, icon (optional), content,
    after_card (optional, below the card).
--}}

@push('meta')
    <meta name="robots" content="noindex">
@endpush

@section('document_title')@yield('title')@endsection

@section('body')
    <main class="flex min-h-screen flex-col items-center px-5 pt-9 pb-12">
        <a href="{{ url('/') }}" class="text-ink no-underline" aria-label="{{ __('auth.home_link') }}">
            <x-ui.logo :size="38" />
        </a>

        <div class="mt-12 flex w-full max-w-[460px] flex-col gap-[22px] rounded-panel border border-line bg-white p-6 shadow-card sm:p-10">
            @hasSection('icon')
                <div class="self-center">@yield('icon')</div>
            @endif

            <div class="flex flex-col gap-2 text-center">
                <h1 class="font-display text-[32px] leading-[1.05] font-bold tracking-[-0.04em]">@yield('title')</h1>
                @hasSection('subtitle')
                    <p class="text-muted">@yield('subtitle')</p>
                @endif
            </div>

            @yield('content')
        </div>

        @yield('after_card')
    </main>
@endsection
