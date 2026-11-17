{{--
    Error page on menu hosts ({slug}.central, /m/*, /q/*): platform branding
    only, never business details. menu-base.css only: no app.js, no Livewire.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), config('locales.rtl'), true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · {{ __('auth.brand') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/menu-base.css'])
</head>
<body class="menu-unavailable" data-error-page="menu">
    <main class="menu-unavailable__card">
        <p>{{ __('errors.code', ['code' => $code]) }}</p>
        <h1>{{ $title }}</h1>
        <p>{{ $body }}</p>
        <a href="{{ config('app.url') }}">{{ __('errors.platform_home') }}</a>
    </main>
</body>
</html>
