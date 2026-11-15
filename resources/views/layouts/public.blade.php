<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#F6F5F1">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @stack('meta')
    <title>@yield('document_title') · {{ __('auth.brand') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- Shared shell for the public site and auth pages (app.css + app.js only). --}}
<body class="min-h-screen bg-paper font-sans text-base/normal text-ink">
    @yield('body')
</body>
</html>
