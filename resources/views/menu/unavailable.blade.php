{{--
    Platform page for suspended / expired tenants and inactive QR codes.
    Not themed, never cached (no-store), not indexed. Never reveals more than
    the business name already known from the URL.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('menu.unavailable.title') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/menu-base.css'])
</head>
<body class="menu-unavailable">
    <main class="menu-unavailable__card">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7.5V12l3 2"></path></svg>
        <h1>{{ __('menu.unavailable.title') }}</h1>
        @if ($businessName)
            <p><strong>{{ __('menu.unavailable.business', ['name' => $businessName]) }}</strong></p>
        @endif
        <p>{{ __('menu.unavailable.body') }}</p>
        <a href="{{ config('app.url') }}">{{ __('menu.unavailable.home') }}</a>
    </main>
</body>
</html>
