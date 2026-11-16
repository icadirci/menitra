{{ __('team.mail.greeting') }}

{{ __('team.mail.intro', ['inviter' => $inviterName, 'business' => $businessName, 'role' => $roleLabel]) }}

{{ __('team.mail.cta') }}: {{ $url }}

{{ trans_choice('team.mail.expiry', $days, ['count' => $days]) }}

{{ __('team.mail.outro') }}

{{ __('auth.mail.signature') }}
