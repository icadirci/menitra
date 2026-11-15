@extends('layouts.public')

@push('meta')
    <meta name="robots" content="noindex">
@endpush

@section('document_title', __('auth.register.title'))

@section('body')
    <div class="flex min-h-screen flex-wrap">
        {{-- Form side --}}
        <div class="flex min-w-0 flex-[1_1_560px] flex-col px-5 pt-7 pb-10 sm:px-10 lg:px-16">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <a href="{{ url('/') }}" class="text-ink no-underline" aria-label="{{ __('auth.home_link') }}">
                    <x-ui.logo />
                </a>
                <p class="text-[15px] text-muted">
                    {{ __('auth.register.have_account') }}
                    <a href="{{ route('login') }}" class="font-semibold text-forest-700 hover:text-forest-900">{{ __('auth.register.login') }}</a>
                </p>
            </div>

            <div class="flex flex-1 items-center justify-center pt-10">
                <form method="POST" action="{{ route('register.store') }}" class="flex w-full max-w-[440px] flex-col gap-[18px]" novalidate>
                    @csrf

                    <div class="mb-1.5 flex flex-col gap-2.5">
                        <h1 class="font-display text-[clamp(32px,4vw,42px)] leading-[1.05] font-bold tracking-[-0.04em]">{{ __('auth.register.title') }}</h1>
                        <p class="text-muted">{{ __('auth.register.subtitle') }}</p>
                    </div>

                    <div class="grid grid-cols-[repeat(auto-fit,minmax(min(200px,100%),1fr))] gap-3.5">
                        <x-ui.field name="name" :label="__('auth.fields.name')" autocomplete="name" required autofocus />
                        <x-ui.field name="business_name" :label="__('auth.fields.business_name')" autocomplete="organization" required />
                    </div>

                    <x-ui.field name="email" type="email" :label="__('auth.fields.email')" autocomplete="email" required />
                    <x-ui.field
                        name="slug"
                        :label="__('auth.fields.slug')"
                        :help="__('auth.register.slug_help', ['example' => __('auth.register.slug_example').'.'.config('tenancy.central_domain')])"
                        autocapitalize="none"
                        spellcheck="false"
                        maxlength="40"
                    />
                    <x-ui.field name="password" type="password" :label="__('auth.fields.password')" autocomplete="new-password" required />
                    <x-ui.field name="password_confirmation" type="password" :label="__('auth.fields.password_confirmation')" autocomplete="new-password" required />

                    <x-ui.button size="lg" class="mt-1 w-full">{{ __('auth.register.submit') }}</x-ui.button>
                    <p class="text-center text-[13px] text-muted">{{ __('auth.register.reassurance') }}</p>
                </form>
            </div>
        </div>

        {{-- Brand side --}}
        <aside class="relative hidden min-w-0 flex-[1_1_480px] flex-col items-center justify-center gap-9 overflow-hidden bg-forest-700 px-16 py-14 text-paper lg:flex">
            <div aria-hidden="true" class="absolute -top-[120px] -right-40 size-[520px] rounded-full border border-paper/12"></div>
            <div aria-hidden="true" class="absolute -bottom-[140px] -left-[100px] size-[360px] rounded-full border border-paper/10"></div>

            <div class="relative flex max-w-[420px] flex-col gap-8">
                <p class="font-display text-[28px] leading-[1.25] tracking-[-0.02em] text-balance">{{ __('auth.register.aside_title') }}</p>
                <ul class="flex flex-col gap-4">
                    @foreach (__('auth.register.aside_points') as $point)
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-paper/10 text-saffron-500">
                                <x-ui.icon name="check" :size="16" :stroke="2.4" />
                            </span>
                            <span class="text-[#DCE7E0]">{{ $point }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>
    </div>
@endsection
