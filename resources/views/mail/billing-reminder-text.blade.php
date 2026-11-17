{{ __('billing.mail.reminder.greeting') }}

{{ trans_choice($key.'.body', $days, ['business' => $businessName, 'plan' => $planName, 'date' => $date, 'count' => $days]) }}

{{ __('billing.mail.reminder.cta') }}: {{ $url }}

{{ __('billing.mail.reminder.outro') }}

{{ __('auth.mail.signature') }}
