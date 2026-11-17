{{ __('billing.mail.receipt.greeting') }}

{{ __('billing.mail.receipt.intro', ['business' => $businessName]) }}

{{ __('billing.receipt.number') }}: {{ $receiptNumber }}
{{ __('billing.receipt.plan') }}: {{ $planName }} · {{ $intervalLabel }}
{{ __('billing.receipt.period') }}: {{ $period }}
{{ __('billing.receipt.paid_at') }}: {{ $paidOn }}
{{ __('billing.receipt.total') }}: {{ $amount }}

{{ __('billing.mail.receipt.cta') }}: {{ $url }}

{{ __('billing.receipt.not_invoice') }}

{{ __('auth.mail.signature') }}
