{{-- WhatsApp or call, and directions, for a location. Renders nothing without contact data. --}}
@props(['location'])
@if ($location->mapUrl || $location->phone || $location->whatsapp)
    <div {{ $attributes->merge(['class' => 'menu-actions']) }}>
        @if ($location->whatsapp)
            <a class="menu-action menu-action--primary" href="https://wa.me/{{ preg_replace('/\D+/', '', $location->whatsapp) }}" rel="noopener">{{ __('menu.whatsapp') }}</a>
        @elseif ($location->phone)
            <a class="menu-action menu-action--primary" href="tel:{{ preg_replace('/[^\d+]+/', '', $location->phone) }}">{{ __('menu.call') }}</a>
        @endif
        @if ($location->mapUrl)
            <a class="menu-action" href="{{ $location->mapUrl }}" rel="noopener" target="_blank">{{ __('menu.directions') }}</a>
        @endif
    </div>
@endif
