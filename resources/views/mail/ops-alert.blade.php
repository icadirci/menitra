{{-- Internal operational alert (ops:health-check). --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('ops.mail.heading') }}</title>
</head>
<body style="margin:0;padding:24px;background:#F6F5F1;font-family:Inter,Arial,sans-serif;color:#15191A;font-size:15px;line-height:1.55">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#FFFFFF;border:1px solid #E3E1D9;border-radius:16px">
        <tr>
            <td style="padding:28px">
                <h1 style="margin:0 0 8px;font-size:20px">{{ __('ops.mail.heading') }}</h1>
                <p style="margin:0 0 20px;color:#575C59">{{ config('app.url') }} · {{ app()->environment() }} · {{ now()->toDateTimeString() }}</p>
                <ul style="margin:0 0 20px;padding-left:20px">
                    @foreach ($problems as $problem)
                        <li style="margin-bottom:8px">{{ $problem }}</li>
                    @endforeach
                </ul>
                <p style="margin:0;font-size:13px;color:#575C59">{{ __('ops.mail.footer', ['minutes' => config('ops.alert_cooldown_minutes')]) }}</p>
            </td>
        </tr>
    </table>
</body>
</html>
