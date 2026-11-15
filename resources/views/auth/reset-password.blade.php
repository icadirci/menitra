@extends('layouts.auth')

@section('title', __('auth.reset.title'))
@section('subtitle', __('auth.reset.subtitle'))

@section('icon')
    <span class="inline-flex size-[60px] items-center justify-center rounded-[18px] bg-forest-100 text-forest-700">
        <x-ui.icon name="lock" :size="28" />
    </span>
@endsection

@section('content')
    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-[18px]" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-ui.field name="email" type="email" :label="__('auth.fields.email')" :value="$request->query('email')" autocomplete="email" required />
        <x-ui.field name="password" type="password" :label="__('auth.fields.password')" autocomplete="new-password" required autofocus />
        <x-ui.field name="password_confirmation" type="password" :label="__('auth.fields.password_confirmation')" autocomplete="new-password" required />

        <x-ui.button size="lg" class="mt-1 w-full shadow-none">{{ __('auth.reset.submit') }}</x-ui.button>
    </form>
@endsection
