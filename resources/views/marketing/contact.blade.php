@extends('layouts.marketing')

@php
    use App\Http\Requests\Marketing\ContactRequest;
    use App\Support\MarketingUrl;

    $quickLinks = array_values(array_filter([
        $hasHomeFaq ? ['label' => __('marketing.contact_page.quick.faq'), 'href' => MarketingUrl::to('home', fragment: 'faq')] : null,
        ['label' => __('marketing.contact_page.quick.plans'), 'href' => MarketingUrl::to('pricing')],
        ['label' => __('marketing.contact_page.quick.languages'), 'href' => MarketingUrl::to('features')],
        ['label' => __('marketing.contact_page.quick.password'), 'href' => route('password.request')],
    ]));

    $inputClasses = fn (string $field) => [
        'block w-full rounded-input border bg-white px-3.5 text-base text-ink placeholder:text-[#8A8E8B]',
        'border-danger' => $errors->has($field),
        'border-line-strong' => ! $errors->has($field),
    ];
@endphp

@section('title', __('marketing.meta.contact_title'))
@section('description', __('marketing.meta.contact_description'))
@section('page', 'contact')

@section('content')
    <section class="px-5 pt-[clamp(48px,7vw,88px)] pb-[clamp(56px,7vw,96px)] sm:px-6">
        <div class="mx-auto flex max-w-[1200px] flex-col gap-12">
            <div class="flex max-w-[680px] flex-col gap-4">
                <p class="text-[13px] font-semibold tracking-[0.12em] text-forest-700 uppercase">{{ __('marketing.contact_page.eyebrow') }}</p>
                <h1 class="font-display text-[clamp(40px,5.4vw,64px)] leading-none font-bold tracking-[-0.045em] text-balance">{{ __('marketing.contact_page.title') }}</h1>
                <p class="text-lg text-pretty text-[#4C514E]">{{ __('marketing.contact_page.lead') }}</p>
            </div>

            <div class="flex flex-wrap items-start gap-6">
                {{-- Form --}}
                <div id="contact-form" class="min-w-0 flex-[3_1_520px] scroll-mt-24 rounded-panel border border-line bg-white p-[clamp(24px,4vw,40px)]">
                    @if ($sent)
                        <div role="status" class="flex flex-col items-start gap-4 py-6">
                            <span class="inline-flex size-14 items-center justify-center rounded-full bg-forest-100 text-forest-700">
                                <x-ui.icon name="check" :size="28" :stroke="2.2" />
                            </span>
                            <h2 class="font-display text-[30px] leading-tight font-bold tracking-[-0.03em]">{{ __('marketing.contact_page.sent_title') }}</h2>
                            <p class="max-w-[460px] text-muted">{{ __('marketing.contact_page.sent_lead') }}</p>
                            <x-ui.button :href="MarketingUrl::to('contact', fragment: 'contact-form')" variant="secondary">{{ __('marketing.contact_page.send_another') }}</x-ui.button>
                        </div>
                    @else
                        <form method="POST" action="{{ route('marketing.'.app()->getLocale().'.contact.store') }}" class="flex flex-col gap-5" aria-label="{{ __('marketing.contact_page.form_label') }}">
                            @csrf

                            <div class="grid grid-cols-[repeat(auto-fit,minmax(min(220px,100%),1fr))] gap-4">
                                <x-ui.field
                                    name="name"
                                    :label="__('marketing.contact_page.fields.name')"
                                    autocomplete="name"
                                    maxlength="100"
                                    required
                                    :placeholder="__('marketing.contact_page.placeholders.name')"
                                />
                                <x-ui.field
                                    name="email"
                                    type="email"
                                    :label="__('marketing.contact_page.fields.email')"
                                    autocomplete="email"
                                    maxlength="255"
                                    required
                                    :placeholder="__('marketing.contact_page.placeholders.email')"
                                />
                            </div>

                            <div class="flex flex-col gap-[7px]">
                                <label for="subject" class="text-sm font-semibold text-ink">{{ __('marketing.contact_page.fields.subject') }}</label>
                                <select
                                    id="subject"
                                    name="subject"
                                    required
                                    @error('subject') aria-invalid="true" aria-describedby="subject-error" @enderror
                                    @class([...$inputClasses('subject'), 'h-[50px] cursor-pointer'])
                                >
                                    @foreach ($subjects as $subject)
                                        <option value="{{ $subject->value }}" @selected(old('subject') === $subject->value)>{{ $subject->label() }}</option>
                                    @endforeach
                                </select>
                                @error('subject')
                                    <p id="subject-error" class="text-[13px] font-medium text-danger">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex flex-col gap-[7px]">
                                <label for="message" class="text-sm font-semibold text-ink">{{ __('marketing.contact_page.fields.message') }}</label>
                                <textarea
                                    id="message"
                                    name="message"
                                    rows="6"
                                    required
                                    minlength="10"
                                    maxlength="5000"
                                    placeholder="{{ __('marketing.contact_page.placeholders.message') }}"
                                    @error('message') aria-invalid="true" aria-describedby="message-error" @enderror
                                    @class([...$inputClasses('message'), 'resize-y py-3.5'])
                                >{{ old('message') }}</textarea>
                                @error('message')
                                    <p id="message-error" class="text-[13px] font-medium text-danger">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Honeypot: invisible to people and assistive technology; bots that fill it get a fake success. --}}
                            <div class="absolute -left-[9999px] size-px overflow-hidden" aria-hidden="true">
                                <label for="{{ ContactRequest::HONEYPOT }}">{{ __('marketing.contact_page.honeypot') }}</label>
                                <input id="{{ ContactRequest::HONEYPOT }}" name="{{ ContactRequest::HONEYPOT }}" type="text" value="" tabindex="-1" autocomplete="off">
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-4">
                                <p class="max-w-[360px] text-[13px] text-[#6B6F6C]">{{ __('marketing.contact_page.privacy') }}</p>
                                <x-ui.button size="lg">
                                    {{ __('marketing.contact_page.submit') }}
                                    <x-ui.icon name="arrow-right" :size="18" :stroke="2" />
                                </x-ui.button>
                            </div>
                        </form>
                    @endif
                </div>

                {{-- Contact channels (site settings in /admin) and quick answers --}}
                <aside class="flex min-w-0 flex-[2_1_340px] flex-col gap-4">
                    @if ($contact->hasChannels())
                        <div class="flex flex-col gap-5 rounded-panel bg-ink p-7 text-paper">
                            <h2 class="font-display text-[22px] font-bold tracking-[-0.02em]">{{ __('marketing.contact_page.channels_title') }}</h2>

                            @if ($contact->email)
                                <x-marketing.contact-channel icon="mail" :label="__('marketing.contact_page.channels.email')">
                                    <a href="mailto:{{ $contact->email }}" class="font-semibold break-all text-paper no-underline hover:text-saffron-500">{{ $contact->email }}</a>
                                </x-marketing.contact-channel>
                            @endif

                            @if ($contact->phone)
                                <x-marketing.contact-channel icon="chat" :label="$contact->whatsapp ? __('marketing.contact_page.channels.phone_whatsapp') : __('marketing.contact_page.channels.phone')">
                                    <a href="{{ $contact->telUrl() }}" class="font-semibold text-paper no-underline hover:text-saffron-500">{{ $contact->phone }}</a>
                                    @if ($contact->whatsappUrl())
                                        <a href="{{ $contact->whatsappUrl() }}" class="mt-1 block text-sm text-saffron-500 underline-offset-4 hover:underline" rel="noopener" target="_blank">{{ __('marketing.contact_page.channels.whatsapp') }}</a>
                                    @endif
                                </x-marketing.contact-channel>
                            @endif

                            @if ($contact->supportHours)
                                <x-marketing.contact-channel icon="clock" :label="__('marketing.contact_page.channels.hours')">
                                    <span class="font-semibold">{{ $contact->supportHours }}</span>
                                </x-marketing.contact-channel>
                            @endif

                            @if ($contact->address)
                                <x-marketing.contact-channel icon="pin" :label="__('marketing.contact_page.channels.address')">
                                    <address class="font-semibold whitespace-pre-line not-italic">{{ $contact->address }}</address>
                                </x-marketing.contact-channel>
                            @endif
                        </div>
                    @endif

                    <div class="flex flex-col gap-1.5 rounded-panel border border-line bg-white p-6">
                        <h2 class="mb-1.5 font-display text-xl font-bold tracking-[-0.02em]">{{ __('marketing.contact_page.quick_title') }}</h2>
                        @foreach ($quickLinks as $link)
                            <a href="{{ $link['href'] }}" class="flex min-h-12 items-center justify-between gap-3 border-t border-[#F0EEE8] px-1 font-medium text-ink no-underline hover:text-forest-700">
                                {{ $link['label'] }}
                                <x-ui.icon name="arrow-right" :size="18" :stroke="2" class="text-forest-700" />
                            </a>
                        @endforeach
                    </div>
                </aside>
            </div>
        </div>
    </section>
@endsection
