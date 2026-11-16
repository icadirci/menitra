{{ __('ops.mail.heading') }}

{{ config('app.url') }} · {{ app()->environment() }} · {{ now()->toDateTimeString() }}

@foreach ($problems as $problem)
- {{ $problem }}
@endforeach

{{ __('ops.mail.footer', ['minutes' => config('ops.alert_cooldown_minutes')]) }}
