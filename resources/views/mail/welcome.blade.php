{{-- Welcome mail (registration). Tenant/user input is escaped with {{ }} only. --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('registration.welcome_mail.heading', ['name' => $welcome->name]) }}</title>
</head>
<body style="margin:0;padding:24px;background:#F6F5F1;font-family:Inter,Arial,sans-serif;color:#15191A;font-size:15px;line-height:1.55">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#FFFFFF;border:1px solid #E3E1D9;border-radius:16px">
        <tr>
            <td style="padding:28px">
                <p style="margin:0 0 16px;font-weight:700;color:#1F4D3B;font-size:18px">Menitra</p>
                <h1 style="margin:0 0 12px;font-size:22px">{{ __('registration.welcome_mail.heading', ['name' => $welcome->name]) }}</h1>
                <p style="margin:0 0 16px">{{ __('registration.welcome_mail.intro', ['business' => $welcome->businessName]) }}</p>
                @if ($welcome->trialEndsOn)
                    <p style="margin:0 0 16px;padding:12px 16px;border-radius:12px;background:#F6F5F1">{{ __('registration.welcome_mail.trial', ['date' => $welcome->trialEndsOn]) }}</p>
                @endif
                <p style="margin:0 0 8px;font-weight:600">{{ __('registration.welcome_mail.steps_title') }}</p>
                <ol style="margin:0 0 24px;padding-left:20px">
                    @foreach (__('registration.welcome_mail.steps') as $step)
                        <li style="margin-bottom:4px">{{ $step }}</li>
                    @endforeach
                </ol>
                <p style="margin:0 0 24px">
                    <a href="{{ $welcome->panelUrl }}" style="display:inline-block;padding:12px 20px;border-radius:12px;background:#1F4D3B;color:#FFFFFF;text-decoration:none;font-weight:600">{{ __('registration.welcome_mail.cta') }}</a>
                </p>
                <p style="margin:0;font-size:13px;color:#575C59">{{ __('registration.welcome_mail.verify_hint') }}</p>
                <p style="margin:12px 0 0;font-size:13px;color:#575C59">{{ __('registration.welcome_mail.signature') }}</p>
            </td>
        </tr>
    </table>
</body>
</html>
