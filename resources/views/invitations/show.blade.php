@extends('layouts.auth')

{{-- Team invitation (launch plan Faz 7). Business and branch names are tenant content: {{ }} only. --}}

@section('title', __('team.accept.title', ['business' => $business]))
@section('subtitle', __('team.accept.subtitle', ['role' => $role]))

@section('icon')
    <span class="inline-flex size-[72px] items-center justify-center rounded-[22px] bg-forest-700 text-paper">
        <x-ui.icon name="user" :size="32" />
    </span>
@endsection

@section('content')
    @if ($branches !== [])
        <div class="rounded-2xl bg-paper px-4 py-3 text-sm">
            <p class="font-semibold text-ink">{{ __('team.accept.branches') }}</p>
            <p class="text-muted">{{ implode(', ', $branches) }}</p>
        </div>
    @endif

    @error('invitation')
        <p class="rounded-2xl bg-danger/10 px-4 py-3 text-sm font-medium text-danger" role="alert">{{ $message }}</p>
    @enderror

    @switch($mode)
        @case(\App\Http\Requests\Team\AcceptInvitationRequest::MODE_CONFIRM)
            <form method="POST" action="{{ route('invitations.accept', ['token' => $token]) }}">
                @csrf
                <x-ui.button size="lg" class="w-full shadow-none">{{ __('team.accept.submit') }}</x-ui.button>
            </form>
            @break

        @case(\App\Http\Requests\Team\AcceptInvitationRequest::MODE_MISMATCH)
            <p class="rounded-2xl bg-danger/10 px-4 py-3 text-sm font-medium text-danger" role="alert">{{ __('team.accept.errors.mismatch') }}</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-ui.button variant="secondary" size="lg" class="w-full">{{ __('team.accept.logout') }}</x-ui.button>
            </form>
            @break

        @case(\App\Http\Requests\Team\AcceptInvitationRequest::MODE_PASSWORD)
            <form method="POST" action="{{ route('invitations.accept', ['token' => $token]) }}" class="flex flex-col gap-[18px]" novalidate>
                @csrf
                <p class="text-sm text-muted">{{ __('team.accept.existing_account', ['email' => $invitation->email]) }}</p>
                <x-ui.field name="password" type="password" :label="__('auth.fields.password')" autocomplete="current-password" required autofocus />
                <x-ui.button size="lg" class="w-full shadow-none">{{ __('team.accept.submit') }}</x-ui.button>
                <a href="{{ route('password.request') }}" class="text-center text-sm font-semibold text-forest-700 hover:text-forest-900">{{ __('team.accept.forgot_password') }}</a>
            </form>
            @break

        @default
            <form method="POST" action="{{ route('invitations.accept', ['token' => $token]) }}" class="flex flex-col gap-[18px]" novalidate>
                @csrf
                <p class="text-sm text-muted">{{ __('team.accept.new_account', ['email' => $invitation->email]) }}</p>
                <x-ui.field name="name" :label="__('auth.fields.name')" autocomplete="name" required autofocus />
                <x-ui.field name="password" type="password" :label="__('auth.fields.password')" autocomplete="new-password" required />
                <x-ui.field name="password_confirmation" type="password" :label="__('auth.fields.password_confirmation')" autocomplete="new-password" required />
                <x-ui.button size="lg" class="w-full shadow-none">{{ __('team.accept.submit_new') }}</x-ui.button>
            </form>
    @endswitch
@endsection
