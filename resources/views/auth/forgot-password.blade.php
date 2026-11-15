@extends('layouts.auth')

{{-- Fortify redirects back here with a `status` message once the link is sent. --}}
@if (session('status'))
    @section('title', __('auth.forgot.sent_title'))
    @section('subtitle', __('auth.forgot.sent_subtitle'))

    @section('icon')
        <span class="relative inline-flex">
            <span class="inline-flex size-[72px] items-center justify-center rounded-[22px] bg-forest-700 text-paper">
                <x-ui.icon name="mail" :size="32" />
            </span>
            <span class="absolute -top-1.5 -right-1.5 inline-flex size-[26px] items-center justify-center rounded-full border-[3px] border-white bg-saffron-500 text-ink">
                <x-ui.icon name="check" :size="12" :stroke="3.2" />
            </span>
        </span>
    @endsection

    @section('content')
        <p class="sr-only" role="status">{{ session('status') }}</p>

        <ol class="flex flex-col gap-3">
            @foreach (__('auth.forgot.sent_steps') as $step)
                <li class="flex items-center gap-3.5">
                    <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-forest-100 text-sm font-bold text-forest-700">{{ $loop->iteration }}</span>
                    <span>{{ $step }}</span>
                </li>
            @endforeach
        </ol>

        <x-ui.button :href="route('login')" size="lg" class="w-full shadow-none">{{ __('auth.forgot.back') }}</x-ui.button>

        <p class="text-center text-sm text-muted">
            {{ __('auth.forgot.wrong_address') }}
            <a href="{{ route('password.request') }}" class="font-semibold text-forest-700 hover:text-forest-900">{{ __('auth.forgot.use_another') }}</a>
        </p>
    @endsection
@else
    @section('title', __('auth.forgot.title'))
    @section('subtitle', __('auth.forgot.subtitle'))

    @section('icon')
        <span class="inline-flex size-[60px] items-center justify-center rounded-[18px] bg-forest-100 text-forest-700">
            <x-ui.icon name="lock" :size="28" />
        </span>
    @endsection

    @section('content')
        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-[22px]" novalidate>
            @csrf

            <x-ui.field name="email" type="email" :label="__('auth.fields.email')" autocomplete="email" required autofocus />

            <x-ui.button size="lg" class="w-full shadow-none">{{ __('auth.forgot.submit') }}</x-ui.button>
        </form>

        <ul class="flex flex-col gap-2.5 rounded-2xl bg-paper px-[18px] py-4 text-sm text-graphite">
            <li class="flex gap-2.5">
                <x-ui.icon name="clock" :size="18" class="mt-0.5 text-forest-700" />
                <span>{{ __('auth.forgot.tip_expiry', ['minutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]) }}</span>
            </li>
            <li class="flex gap-2.5">
                <x-ui.icon name="mail" :size="18" class="mt-0.5 text-forest-700" />
                <span>{{ __('auth.forgot.tip_spam') }}</span>
            </li>
        </ul>
    @endsection
@endif

@section('after_card')
    <a href="{{ route('login') }}" class="mt-6 inline-flex min-h-11 items-center gap-2 font-semibold text-forest-700 no-underline hover:text-forest-900">
        <x-ui.icon name="arrow-left" :size="18" :stroke="2" />
        {{ __('auth.forgot.back') }}
    </a>
@endsection
