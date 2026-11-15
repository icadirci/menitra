{{ __('registration.welcome_mail.heading', ['name' => $welcome->name]) }}

{{ __('registration.welcome_mail.intro', ['business' => $welcome->businessName]) }}

@if ($welcome->trialEndsOn)
{{ __('registration.welcome_mail.trial', ['date' => $welcome->trialEndsOn]) }}

@endif
{{ __('registration.welcome_mail.steps_title') }}
@foreach (__('registration.welcome_mail.steps') as $step)
{{ $loop->iteration }}. {{ $step }}
@endforeach

{{ __('registration.welcome_mail.cta') }}: {{ $welcome->panelUrl }}

{{ __('registration.welcome_mail.verify_hint') }}

{{ __('registration.welcome_mail.signature') }}
