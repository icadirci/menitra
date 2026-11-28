{{-- Read by resources/js/lib/google-tag.js; the banner stays hidden until the script shows it. --}}
<div data-google-tag="{{ $tagId }}" @if ($signupConversion) data-signup-conversion="{{ $signupConversion }}" @endif hidden></div>

<section data-consent-banner hidden role="dialog" aria-labelledby="consent-title" aria-describedby="consent-body"
    class="fixed inset-x-4 bottom-4 z-50 mx-auto flex max-w-[560px] flex-col gap-4 rounded-panel border border-line bg-white p-5 text-ink shadow-card sm:p-6">
    <div class="flex flex-col gap-1.5">
        <h2 id="consent-title" class="font-semibold">{{ __('marketing.consent.title') }}</h2>
        <p id="consent-body" class="text-sm text-muted">
            {{ __('marketing.consent.body') }}
            <a href="{{ \App\Support\MarketingUrl::to('privacy') }}" class="font-semibold text-forest-700 hover:text-forest-900">{{ __('marketing.consent.privacy') }}</a>
        </p>
    </div>
    <div class="flex flex-wrap justify-end gap-2.5">
        <button type="button" data-consent="denied" class="rounded-input border border-line px-4 py-2.5 text-sm font-semibold text-ink hover:bg-paper">{{ __('marketing.consent.reject') }}</button>
        <button type="button" data-consent="granted" class="rounded-input bg-forest-700 px-4 py-2.5 text-sm font-semibold text-paper hover:bg-forest-900">{{ __('marketing.consent.accept') }}</button>
    </div>
</section>
