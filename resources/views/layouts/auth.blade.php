<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ __('auth.brand') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-12">
        <p class="mb-8 text-center text-lg font-semibold tracking-tight">{{ __('auth.brand') }}</p>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">
            <h1 class="text-xl font-semibold">@yield('title')</h1>

            @hasSection('subtitle')
                <p class="mt-2 text-sm text-zinc-600">@yield('subtitle')</p>
            @endif

            @if (session('status'))
                <p class="mt-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">
                    {{ session('status') }}
                </p>
            @endif

            <div class="mt-6">
                @yield('content')
            </div>
        </div>

        @hasSection('footer')
            <p class="mt-6 text-center text-sm text-zinc-600">@yield('footer')</p>
        @endif
    </main>
</body>
</html>
