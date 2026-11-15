@extends('layouts.public')

{{--
    Shell for /account (app.css + app.js only, no Livewire or Filament).
    Sections: title, content.
--}}

@push('meta')
    <meta name="robots" content="noindex">
@endpush

@section('document_title')@yield('title')@endsection

@section('body')
    <header class="border-b border-line">
        <div class="mx-auto flex h-[72px] max-w-[760px] items-center justify-between gap-4 px-4 sm:px-6">
            <a href="{{ url('/') }}" class="text-ink no-underline" aria-label="{{ __('account.home_link') }}">
                <x-ui.logo :size="32" />
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="min-h-11 cursor-pointer rounded-input px-3 text-sm font-semibold text-muted hover:bg-white hover:text-ink">
                    {{ __('account.logout') }}
                </button>
            </form>
        </div>
    </header>

    <main id="main" class="mx-auto flex max-w-[760px] flex-col gap-6 px-4 pt-8 pb-16 sm:px-6 sm:pt-12">
        @yield('content')
    </main>
@endsection
