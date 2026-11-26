{{ __('billing.mail.receipt.greeting') }}

{{ $intro }}

{{ __('shop.order_number') }}: {{ $orderNumber }}
@foreach ($lines as $line)
- {{ $line }}
@endforeach
{{ __('shop.checkout.total') }}: {{ $total }}
@if ($carrier)

{{ __('shop.admin.orders.carrier') }}: {{ $carrier }}
@endif
@if ($trackingNumber)
{{ __('shop.admin.orders.tracking_number') }}: {{ $trackingNumber }}
@endif
@if ($trackingUrl)
{{ $trackingUrl }}
@endif
@if ($reason)

{{ __('shop.admin.orders.cancel_reason') }}: {{ $reason }}
@endif

{{ __('shop.mail.cta') }}: {{ $url }}

{{ __('auth.mail.signature') }}
