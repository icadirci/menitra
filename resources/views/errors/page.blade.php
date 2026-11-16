{{--
    Shared HTTP error page. $menuHost comes from the `errors::*` view composer
    (App\Support\ErrorPage): menu hosts get a plain platform page, the central
    domain the marketing design. No database access in either variant.
--}}
@php
    $messageKey ??= (string) $code;
    $title = __("errors.{$messageKey}.title");
    $body = __("errors.{$messageKey}.body");
    // Explicit 403 reasons (e.g. suspended business) on the central domain only.
    if (! ($menuHost ?? false)) {
        $body = \App\Support\ErrorPage::forbiddenReason($exception ?? null) ?? $body;
    }
@endphp
@if ($menuHost ?? false)
    @include('errors.partials.menu')
@else
    @include('errors.partials.central')
@endif
