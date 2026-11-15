{{-- ?lang= links (relative to the current page) for the tenant's enabled locales. --}}
@props(['locales', 'current'])
@if (count($locales) > 1)
    <nav class="menu-lang" aria-label="{{ __('menu.language') }}">
        @foreach ($locales as $locale)
            <a href="?lang={{ $locale->code }}"
               lang="{{ $locale->code }}"
               hreflang="{{ $locale->code }}"
               title="{{ $locale->label }}"
               @if ($locale->code === $current) aria-current="true" @endif>{{ strtoupper($locale->code) }}</a>
        @endforeach
    </nav>
@endif
