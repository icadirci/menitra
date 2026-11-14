@extends('layouts.auth')

@section('title', __('auth.register.title'))
@section('subtitle', __('auth.register.subtitle'))

@section('content')
    <form method="POST" action="{{ route('register.store') }}" class="space-y-4" novalidate>
        @csrf

        <x-auth.field name="name" :label="__('auth.fields.name')" autocomplete="name" required autofocus />
        <x-auth.field name="email" type="email" :label="__('auth.fields.email')" autocomplete="email" required />
        <x-auth.field name="business_name" :label="__('auth.fields.business_name')" autocomplete="organization" required />
        <x-auth.field
            name="slug"
            :label="__('auth.fields.slug')"
            :help="__('auth.register.slug_help', ['example' => __('auth.register.slug_example').'.'.config('tenancy.central_domain')])"
            autocapitalize="none"
            spellcheck="false"
            maxlength="40"
        />
        <x-auth.field name="password" type="password" :label="__('auth.fields.password')" autocomplete="new-password" required />
        <x-auth.field name="password_confirmation" type="password" :label="__('auth.fields.password_confirmation')" autocomplete="new-password" required />

        <button type="submit" class="w-full rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
            {{ __('auth.register.submit') }}
        </button>
    </form>
@endsection

@section('footer')
    {{ __('auth.register.have_account') }}
    <a href="{{ route('login') }}" class="font-medium text-zinc-900 underline underline-offset-2">{{ __('auth.register.login') }}</a>
@endsection
