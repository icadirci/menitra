{{ __('marketing.contact_mail.heading') }}

{{ __('marketing.contact_page.fields.name') }}: {{ $contact->name }}
{{ __('marketing.contact_page.fields.email') }}: {{ $contact->email }}
{{ __('marketing.contact_page.fields.subject') }}: {{ $contact->subject->label() }}
{{ __('marketing.contact_mail.language') }}: {{ config('locales.supported.'.$contact->locale, $contact->locale) }}

{{ $contact->body }}

{{ __('marketing.contact_mail.reply_hint') }}
