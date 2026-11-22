{{-- Payment receipt. The business name is tenant input: escaped with {{ }} only. --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('billing.mail.receipt.subject', ['number' => $receiptNumber]) }}</title>
</head>
<body style="margin:0;padding:24px;background:#F6F5F1;font-family:Inter,Arial,sans-serif;color:#15191A;font-size:15px;line-height:1.55">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#FFFFFF;border:1px solid #E3E1D9;border-radius:16px">
        <tr>
            <td style="padding:28px">
                <p style="margin:0 0 16px;font-weight:700;color:#1F4D3B;font-size:18px">Menitra</p>
                <p style="margin:0 0 12px">{{ __('billing.mail.receipt.greeting') }}</p>
                <p style="margin:0 0 20px">{{ __('billing.mail.receipt.intro', ['business' => $businessName]) }}</p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;font-size:14px">
                    <tr><td style="padding:6px 0;color:#575C59">{{ __('billing.receipt.number') }}</td><td style="padding:6px 0;text-align:right">{{ $receiptNumber }}</td></tr>
                    <tr><td style="padding:6px 0;color:#575C59">{{ __('billing.receipt.plan') }}</td><td style="padding:6px 0;text-align:right">{{ $planName }} · {{ $intervalLabel }}</td></tr>
                    <tr><td style="padding:6px 0;color:#575C59">{{ __('billing.receipt.period') }}</td><td style="padding:6px 0;text-align:right">{{ $period }}</td></tr>
                    <tr><td style="padding:6px 0;color:#575C59">{{ __('billing.receipt.paid_at') }}</td><td style="padding:6px 0;text-align:right">{{ $paidOn }}</td></tr>
                    <tr><td style="padding:10px 0 0;font-weight:700;border-top:1px solid #E3E1D9">{{ __('billing.receipt.total') }}</td><td style="padding:10px 0 0;text-align:right;font-weight:700;border-top:1px solid #E3E1D9">{{ $amount }}</td></tr>
                </table>
                <p style="margin:0 0 24px">
                    <a href="{{ $url }}" style="display:inline-block;padding:12px 20px;border-radius:12px;background:#1F4D3B;color:#FFFFFF;text-decoration:none;font-weight:600">{{ __('billing.mail.receipt.cta') }}</a>
                </p>
                <p style="margin:0 0 16px;font-size:13px;color:#575C59">{{ __('billing.receipt.not_invoice') }}</p>
                <p style="margin:0;font-size:13px;color:#575C59">{{ __('auth.mail.signature') }}</p>
            </td>
        </tr>
    </table>
</body>
</html>
