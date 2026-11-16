@extends('layouts.auth')

{{-- Generic page for every unusable invitation link; reveals no business, inviter or email. --}}

@section('title', __('team.accept.invalid.title'))
@section('subtitle', __('team.accept.invalid.body'))

@section('icon')
    <span class="inline-flex size-[60px] items-center justify-center rounded-[18px] bg-forest-100 text-forest-700">
        <x-ui.icon name="mail" :size="28" />
    </span>
@endsection

@section('content')
    <x-ui.button :href="url('/')" variant="secondary" size="lg" class="w-full">{{ __('team.accept.home') }}</x-ui.button>
@endsection
