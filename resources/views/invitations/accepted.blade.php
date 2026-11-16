@extends('layouts.auth')

@section('title', __('team.accept.accepted.title'))
@section('subtitle', __('team.accept.accepted.body', ['business' => $business]))

@section('icon')
    <span class="inline-flex size-[60px] items-center justify-center rounded-[18px] bg-forest-100 text-forest-700">
        <x-ui.icon name="check" :size="28" />
    </span>
@endsection

@section('content')
    <x-ui.button :href="route('login')" size="lg" class="w-full shadow-none">{{ __('team.accept.accepted.login') }}</x-ui.button>
@endsection
