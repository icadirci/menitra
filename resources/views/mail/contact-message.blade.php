{{-- Internal notification; visitor input is escaped with {{ }} only. --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('marketing.contact_mail.heading') }}</title>
</head>
<body style="margin:0;padding:24px;background:#F6F5F1;font-family:Inter,Arial,sans-serif;color:#15191A;font-size:15px;line-height:1.55">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#FFFFFF;border:1px solid #E3E1D9;border-radius:16px">
        <tr>
            <td style="padding:28px">
                <h1 style="margin:0 0 20px;font-size:20px">{{ __('marketing.contact_mail.heading') }}</h1>
                <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:20px">
                    <tr><td style="padding:4px 12px 4px 0;color:#575C59;white-space:nowrap">{{ __('marketing.contact_page.fields.name') }}</td><td style="padding:4px 0;font-weight:600">{{ $contact->name }}</td></tr>
                    <tr><td style="padding:4px 12px 4px 0;color:#575C59;white-space:nowrap">{{ __('marketing.contact_page.fields.email') }}</td><td style="padding:4px 0;font-weight:600">{{ $contact->email }}</td></tr>
                    <tr><td style="padding:4px 12px 4px 0;color:#575C59;white-space:nowrap">{{ __('marketing.contact_page.fields.subject') }}</td><td style="padding:4px 0;font-weight:600">{{ $contact->subject->label() }}</td></tr>
                    <tr><td style="padding:4px 12px 4px 0;color:#575C59;white-space:nowrap">{{ __('marketing.contact_mail.language') }}</td><td style="padding:4px 0;font-weight:600">{{ config('locales.supported.'.$contact->locale, $contact->locale) }}</td></tr>
                </table>
                <div style="padding:16px;border-radius:12px;background:#F6F5F1;white-space:pre-wrap">{{ $contact->body }}</div>
                <p style="margin:20px 0 0;font-size:13px;color:#575C59">{{ __('marketing.contact_mail.reply_hint') }}</p>
            </td>
        </tr>
    </table>
</body>
</html>
