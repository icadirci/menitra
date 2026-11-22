{{-- Account mail with one action link (email verification, password reset). User input is escaped with {{ }} only. --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:24px;background:#F6F5F1;font-family:Inter,Arial,sans-serif;color:#15191A;font-size:15px;line-height:1.55">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#FFFFFF;border:1px solid #E3E1D9;border-radius:16px">
        <tr>
            <td style="padding:28px">
                <p style="margin:0 0 16px;font-weight:700;color:#1F4D3B;font-size:18px">Menitra</p>
                <p style="margin:0 0 12px">{{ __('auth.mail.greeting', ['name' => $name]) }}</p>
                <p style="margin:0 0 24px">{{ $intro }}</p>
                <p style="margin:0 0 24px">
                    <a href="{{ $url }}" style="display:inline-block;padding:12px 20px;border-radius:12px;background:#1F4D3B;color:#FFFFFF;text-decoration:none;font-weight:600">{{ $cta }}</a>
                </p>
                <p style="margin:0 0 16px;font-size:13px;color:#575C59">{{ $expiry }}</p>
                <p style="margin:0 0 16px;font-size:13px;color:#575C59">{{ $outro }}</p>
                <p style="margin:0 0 4px;font-size:13px;color:#575C59">{{ __('auth.mail.fallback') }}</p>
                <p style="margin:0 0 16px;font-size:13px;word-break:break-all"><a href="{{ $url }}" style="color:#1F4D3B">{{ $url }}</a></p>
                <p style="margin:0;font-size:13px;color:#575C59">{{ __('auth.mail.signature') }}</p>
            </td>
        </tr>
    </table>
</body>
</html>
