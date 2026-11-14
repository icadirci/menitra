@extends('layouts.auth')

@section('title', __('auth.forgot.title'))
@section('subtitle', __('auth.forgot.subtitle'))

@section('content')
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4" novalidate>
        @csrf

        <x-auth.field name="email" type="email" :label="__('auth.fields.email')" autocomplete="email" required autofocus />

        <button type="submit" class="w-full rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
            {{ __('auth.forgot.submit') }}
        </button>
    </form>
@endsection

@section('footer')
    <a href="{{ route('login') }}" class="font-medium text-zinc-900 underline underline-offset-2">{{ __('auth.forgot.back') }}</a>
@endsection
