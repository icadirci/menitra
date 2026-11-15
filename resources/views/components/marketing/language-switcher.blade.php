@props(['page' => 'home', 'dark' => false])

{{-- Plain links (works without JS); the current language is marked with aria-current. --}}
<div {{ $attributes->class('flex items-center gap-1') }} role="group" aria-label="{{ __('marketing.language.label') }}">
    <x-ui.icon name="globe" :size="16" @class(['mr-1', 'text-[#8E938F]' => $dark, 'text-muted' => ! $dark]) />
    @foreach (\App\Support\MarketingUrl::alternates($page) as $locale => $url)
        <a
            href="{{ $url }}"
            hreflang="{{ $locale }}"
            lang="{{ $locale }}"
            @if ($locale === app()->getLocale()) aria-current="true" @endif
            @class([
                'inline-flex min-h-11 min-w-11 items-center justify-center rounded-input px-2 text-sm font-semibold uppercase no-underline',
                'text-ink bg-white border border-line' => ! $dark && $locale === app()->getLocale(),
                'text-muted hover:text-ink' => ! $dark && $locale !== app()->getLocale(),
                'text-paper bg-white/10' => $dark && $locale === app()->getLocale(),
                'text-[#A9AEAA] hover:text-paper' => $dark && $locale !== app()->getLocale(),
            ])
        >
            <span class="sr-only">{{ config("locales.supported.{$locale}") }}</span>
            <span aria-hidden="true">{{ $locale }}</span>
        </a>
    @endforeach
</div>
