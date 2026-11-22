{{-- "Made with Menitra", hidden when the plan includes remove_branding. --}}
@props(['brand'])
@if ($brand->showPlatformBranding)
    <p {{ $attributes->merge(['class' => 'menu-branding']) }}>
        <a href="{{ config('app.url') }}" rel="noopener">{{ __('menu.powered_by') }}</a>
    </p>
@endif
