@extends('layouts.auth')

{{-- /account without an accessible business: suspended tenant or no membership. --}}

@section('title', __("account.unavailable.{$reason}.title"))
@section('subtitle', $reason === 'suspended' ? __('tenancy.suspended') : __('account.unavailable.no_business.body'))

@section('icon')
    <span class="inline-flex size-[72px] items-center justify-center rounded-[22px] bg-saffron-100 text-ink">
        <x-ui.icon name="lock" :size="32" />
    </span>
@endsection

@section('content')
    <x-ui.button :href="\App\Support\MarketingUrl::to('contact')" size="lg" class="w-full shadow-none">
        {{ __('account.unavailable.contact') }}
    </x-ui.button>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <button type="submit" class="min-h-11 cursor-pointer text-sm font-semibold text-muted hover:text-ink">
            {{ __('account.logout') }}
        </button>
    </form>
@endsection
