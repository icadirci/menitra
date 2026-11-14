@extends('layouts.auth')

@section('title', __('auth.reset.title'))

@section('content')
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-auth.field name="email" type="email" :label="__('auth.fields.email')" :value="$request->query('email')" autocomplete="email" required />
        <x-auth.field name="password" type="password" :label="__('auth.fields.password')" autocomplete="new-password" required autofocus />
        <x-auth.field name="password_confirmation" type="password" :label="__('auth.fields.password_confirmation')" autocomplete="new-password" required />

        <button type="submit" class="w-full rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
            {{ __('auth.reset.submit') }}
        </button>
    </form>
@endsection
