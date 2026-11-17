@extends('layouts.account')

{{--
    /account: minimal and read-only (not a dashboard). Receives only the
    AccountSummary DTO, already authorized and allow-listed for the role.
--}}

@section('title', __('account.title'))

@php
    /** @var \App\Services\Account\AccountSummary $account */
    $subscription = $account->subscription;
    $card = 'flex flex-col gap-4 rounded-card border border-line bg-white p-5 sm:p-7';
@endphp

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex flex-col gap-1.5">
            <h1 class="font-display text-[32px] leading-[1.05] font-bold tracking-[-0.04em]">{{ __('account.title') }}</h1>
            <p class="text-muted">{{ __('account.subtitle', ['business' => $account->businessName]) }}</p>
        </div>
        <x-ui.button :href="$account->panelUrl" size="sm" class="shadow-none" data-test="panel-link">
            {{ __('account.open_panel') }}
            <x-ui.icon name="arrow-right" :size="18" />
        </x-ui.button>
    </div>

    {{-- Grace: prominent warning while the menu is still online. --}}
    @if ($account->isOwner && $account->menuOnline && $subscription?->isGrace())
        <section class="flex flex-col gap-2 rounded-card border-2 border-saffron-500 bg-saffron-100 p-5 sm:p-6" role="alert" data-test="grace-warning">
            <h2 class="text-lg font-bold">{{ __('account.grace.title') }}</h2>
            <p>{{ __('account.grace.body', ['date' => $subscription->keyDate]) }}</p>
            <p class="font-semibold">
                <a href="{{ $account->paymentUrl }}" class="text-forest-700 underline underline-offset-4 hover:text-forest-900" data-test="grace-pay">{{ __('account.grace.action') }}</a>
            </p>
        </section>
    @endif

    {{-- Offline: data kept; reactivation by paying (or through support). --}}
    @if ($account->needsReactivation())
        <section class="flex flex-col gap-3 rounded-card border-2 border-danger/40 bg-white p-5 sm:p-6" role="alert" data-test="expired-message">
            <h2 class="text-lg font-bold text-danger">{{ __('account.expired.title') }}</h2>
            <p>{{ __('account.expired.body') }}</p>
            <p class="font-semibold">{{ __('account.expired.reactivate') }}</p>

            @if ($account->support?->hasChannels() || $account->support?->supportHours)
                <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[auto_1fr]">
                    @if ($account->support->email)
                        <dt class="text-muted">{{ __('account.expired.email') }}</dt>
                        <dd><a href="mailto:{{ $account->support->email }}" class="text-forest-700 underline underline-offset-4">{{ $account->support->email }}</a></dd>
                    @endif
                    @if ($account->support->phone)
                        <dt class="text-muted">{{ __('account.expired.phone') }}</dt>
                        <dd><a href="{{ $account->support->telUrl() }}" class="text-forest-700 underline underline-offset-4">{{ $account->support->phone }}</a></dd>
                    @endif
                    @if ($account->support->whatsappUrl())
                        <dt class="text-muted">{{ __('account.expired.whatsapp') }}</dt>
                        <dd><a href="{{ $account->support->whatsappUrl() }}" class="text-forest-700 underline underline-offset-4" rel="noopener" target="_blank">{{ $account->support->phone }}</a></dd>
                    @endif
                    @if ($account->support->supportHours)
                        <dt class="text-muted">{{ __('account.expired.support_hours') }}</dt>
                        <dd>{{ $account->support->supportHours }}</dd>
                    @endif
                </dl>
            @endif

            <div class="flex flex-wrap gap-3">
                @if ($account->paymentUrl)
                    <x-ui.button :href="$account->paymentUrl" size="sm" class="shadow-none" data-test="reactivate-pay">{{ __('account.expired.pay') }}</x-ui.button>
                @endif
                <x-ui.button :href="$account->contactUrl" variant="secondary" size="sm">{{ __('account.expired.contact') }}</x-ui.button>
            </div>
        </section>
    @endif

    {{-- Menu status: every role; subscription details for owners only. --}}
    <section class="{{ $card }}" aria-labelledby="menu-status">
        <h2 id="menu-status" class="text-sm font-semibold tracking-wide text-muted uppercase">{{ __('account.menu.heading') }}</h2>

        <p class="flex items-center gap-2.5 text-lg font-bold" data-test="menu-status" data-online="{{ $account->menuOnline ? 'true' : 'false' }}">
            <span @class(['size-3 rounded-full', 'bg-success' => $account->menuOnline, 'bg-danger' => ! $account->menuOnline]) aria-hidden="true"></span>
            {{ $account->menuOnline ? __('account.menu.online') : __('account.menu.offline') }}
        </p>

        @if (! $account->isOwner && ! $account->menuOnline)
            <p class="text-muted">{{ __('account.staff_offline') }}</p>
        @endif

        @if ($subscription !== null)
            @if ($account->menuOnline && $subscription->accessUntil)
                <p class="text-muted">{{ __('account.menu.online_until', ['date' => $subscription->accessUntil]) }}</p>
            @endif

            <dl class="grid gap-x-6 gap-y-3 border-t border-line pt-4 sm:grid-cols-[auto_1fr]" data-test="subscription">
                <dt class="text-muted">{{ __('account.subscription.plan') }}</dt>
                <dd class="font-semibold">{{ $subscription->planName ?? __('account.subscription.none') }}</dd>

                <dt class="text-muted">{{ __('account.subscription.status') }}</dt>
                <dd class="font-semibold" data-test="subscription-status">{{ $subscription->status?->label() ?? __('account.subscription.none') }}</dd>

                @if ($subscription->keyDateField)
                    <dt class="text-muted">{{ __("account.subscription.key_dates.{$subscription->keyDateField}") }}</dt>
                    <dd data-test="key-date">
                        <span class="font-semibold">{{ $subscription->keyDate }}</span>
                        <span class="text-muted">· {{ trans_choice('account.subscription.days_left', $subscription->keyDateDaysLeft, ['days' => $subscription->keyDateDaysLeft]) }}</span>
                    </dd>
                @endif

                @if ($subscription->accessUntil)
                    <dt class="text-muted">{{ __('account.subscription.access_until') }}</dt>
                    <dd class="font-semibold" data-test="access-until">{{ $subscription->accessUntil }}</dd>
                @endif

                @if ($subscription->endedAt)
                    <dt class="text-muted">{{ __('account.subscription.ended_at') }}</dt>
                    <dd class="font-semibold" data-test="ended-at">{{ $subscription->endedAt }}</dd>
                @endif
            </dl>

            @if ($subscription->isPastDue())
                <p class="rounded-2xl bg-saffron-100 px-4 py-3 text-sm">{{ __('account.subscription.past_due') }}</p>
            @endif
            @if ($subscription->cancelAtPeriodEnd)
                <p class="rounded-2xl bg-saffron-100 px-4 py-3 text-sm">{{ __('account.subscription.cancel_at_period_end') }}</p>
            @endif
        @endif
    </section>

    {{-- Links: public menu (MenuUrlGenerator) and the stable /q/{code} QR link. --}}
    <section class="{{ $card }}" aria-labelledby="menu-links">
        <h2 id="menu-links" class="text-sm font-semibold tracking-wide text-muted uppercase">{{ __('account.links.heading') }}</h2>

        @if ($account->publicMenuUrl)
            <div class="flex flex-col gap-1">
                <span class="text-sm text-muted">{{ __('account.links.public_menu') }}</span>
                <a href="{{ $account->publicMenuUrl }}" target="_blank" rel="noopener" class="font-semibold break-all text-forest-700 underline underline-offset-4 hover:text-forest-900" data-test="public-menu-url">{{ $account->publicMenuUrl }}</a>
            </div>
        @endif

        @forelse ($account->branches as $branch)
            <article class="flex flex-col gap-4 border-t border-line pt-4 sm:flex-row" data-test="branch">
                @if ($branch->qrCode)
                    <img
                        src="{{ $branch->qrCode->previewDataUri }}"
                        alt="{{ __('account.links.qr_alt', ['branch' => $branch->name]) }}"
                        width="128"
                        height="128"
                        class="size-32 shrink-0 rounded-input border border-line bg-white p-1"
                    >
                @endif

                <div class="flex min-w-0 flex-col gap-2">
                    <h3 class="flex flex-wrap items-center gap-2 font-bold">
                        {{ $branch->name }}
                        @if ($branch->isDefault)
                            <span class="rounded-label bg-forest-100 px-2 py-0.5 text-xs font-semibold text-forest-900">{{ __('account.links.default') }}</span>
                        @endif
                    </h3>
                    @unless ($branch->isActive)
                        <p class="text-sm text-danger">{{ __('account.links.inactive') }}</p>
                    @endunless

                    <p class="text-sm">
                        <span class="text-muted">{{ __('account.links.branch_menu') }}:</span>
                        <a href="{{ $branch->publicUrl }}" target="_blank" rel="noopener" class="break-all text-forest-700 underline underline-offset-4 hover:text-forest-900" data-test="branch-url">{{ $branch->publicUrl }}</a>
                    </p>

                    @if ($branch->qrCode)
                        <p class="text-sm">
                            <span class="text-muted">{{ __('account.links.qr_link') }}:</span>
                            <a href="{{ $branch->qrCode->scanUrl }}" target="_blank" rel="noopener" class="break-all text-forest-700 underline underline-offset-4 hover:text-forest-900" data-test="qr-url">{{ $branch->qrCode->scanUrl }}</a>
                        </p>
                        <p class="text-sm text-muted">{{ __('account.links.qr_help') }}</p>
                        <div class="flex flex-wrap gap-2">
                            <x-ui.button :href="$branch->qrCode->svgDownloadUrl" variant="secondary" size="sm" download>
                                <x-ui.icon name="qr" :size="18" /> {{ __('account.links.download_svg') }}
                            </x-ui.button>
                            <x-ui.button :href="$branch->qrCode->pngDownloadUrl" variant="secondary" size="sm" download>
                                <x-ui.icon name="qr" :size="18" /> {{ __('account.links.download_png') }}
                            </x-ui.button>
                        </div>
                    @else
                        <p class="text-sm text-muted">{{ __('account.links.no_qr') }}</p>
                    @endif
                </div>
            </article>
        @empty
            <p class="text-muted">{{ __('account.links.none') }}</p>
        @endforelse
    </section>
@endsection
