@extends('layouts.auth')

@section('title', __('auth.verify.title'))

@section('content')
    <p class="text-sm text-zinc-700">{{ __('auth.verify.body') }}</p>

    @if (session('status') === 'verification-link-sent')
        <p class="mt-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">{{ __('auth.verify.sent') }}</p>
    @endif

    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="w-full rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 sm:w-auto">
                {{ __('auth.verify.resend') }}
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm font-medium text-zinc-600 underline underline-offset-2 hover:text-zinc-900">
                {{ __('auth.verify.logout') }}
            </button>
        </form>
    </div>
@endsection
