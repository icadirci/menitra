{{--
    Shared <head> of public menu pages: meta, CSS variables and the menu bundles
    only (menu-base.css + one theme stylesheet + menu.js) — never app.css/app.js.
    Expects $theme (ThemeSettings). Sections: title, description, noindex (optional), content.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title')</title>
    @hasSection('description')
        <meta name="description" content="@yield('description')">
    @endif
    @hasSection('noindex')
        <meta name="robots" content="noindex">
    @endif
    <meta name="theme-color" content="{{ $theme->colors['bg'] ?? '#ffffff' }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/menu-base.css', $theme->theme->stylesheet(), 'resources/js/menu.js'])
</head>
<body class="menu-body">
    <div class="menu-root" data-theme="{{ $theme->key() }}" style="{{ $theme->cssVariables() }}">
        @yield('content')
    </div>
</body>
</html>
