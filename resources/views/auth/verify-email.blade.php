@extends('layouts.auth')

@section('title', __('auth.verify.title'))
@section('subtitle', __('auth.verify.body'))

@section('icon')
    <span class="inline-flex size-[72px] items-center justify-center rounded-[22px] bg-forest-700 text-paper">
        <x-ui.icon name="mail" :size="32" />
    </span>
@endsection

@section('content')
    @if (session('status') === 'verification-link-sent')
        <p class="flex items-start gap-2.5 rounded-2xl bg-forest-100 px-4 py-3 text-sm text-forest-900" role="status">
            <x-ui.icon name="check" :size="18" :stroke="2.2" class="mt-0.5" />
            {{ __('auth.verify.sent') }}
        </p>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <x-ui.button size="lg" class="w-full shadow-none">{{ __('auth.verify.resend') }}</x-ui.button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <button type="submit" class="min-h-11 cursor-pointer text-sm font-semibold text-muted hover:text-ink">
            {{ __('auth.verify.logout') }}
        </button>
    </form>
@endsection
