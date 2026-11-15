@extends('layouts.public')

{{--
    Marketing pages. Sections: title, description, page (marketing route page
    name for canonical/hreflang, e.g. "home"), content.
--}}

@push('meta')
    @php($page = trim($__env->yieldContent('page', 'home')))
    <meta name="description" content="@yield('description')">
    <link rel="canonical" href="{{ \App\Support\MarketingUrl::to($page) }}">
    @foreach (\App\Support\MarketingUrl::alternates($page) as $locale => $url)
        <link rel="alternate" hreflang="{{ $locale }}" href="{{ $url }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ \App\Support\MarketingUrl::to($page, config('locales.default')) }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ __('auth.brand') }}">
    <meta property="og:title" content="@yield('title')">
    <meta property="og:description" content="@yield('description')">
    <meta property="og:url" content="{{ \App\Support\MarketingUrl::to($page) }}">
    <meta property="og:locale" content="{{ app()->getLocale() === 'tr' ? 'tr_TR' : 'en_US' }}">
@endpush

@section('document_title')@yield('title')@endsection

@section('body')
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-input focus:bg-white focus:px-4 focus:py-3 focus:font-semibold focus:shadow-card">{{ __('marketing.skip_to_content') }}</a>

    <x-marketing.nav :page="trim($__env->yieldContent('page', 'home'))" />

    <main id="main">
        @yield('content')
    </main>

    <x-marketing.footer :page="trim($__env->yieldContent('page', 'home'))" />
@endsection
