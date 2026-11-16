{{--
    Terms of use checkbox + privacy notice (launch plan Faz 10). Used by the
    registration form and by the invitation form for new accounts. The privacy
    notice is information under KVKK, not consent, so it is a note, not a box.
    Links follow the current locale (MarketingUrl).
--}}
@php
    $linkClass = 'font-semibold text-forest-700 underline underline-offset-2 hover:text-forest-900';
@endphp

<div class="flex flex-col gap-2">
    <div class="flex items-start gap-3">
        <input
            id="terms"
            name="terms"
            type="checkbox"
            value="1"
            required
            @checked(old('terms'))
            @error('terms') aria-invalid="true" aria-describedby="terms-error" @enderror
            @class([
                'mt-0.5 size-5 shrink-0 cursor-pointer rounded border accent-forest-700',
                'border-danger' => $errors->has('terms'),
                'border-line-strong' => ! $errors->has('terms'),
            ])
        >
        <label for="terms" class="text-[15px] text-ink">
            {{ __('auth.terms.accept_before') }}<a href="{{ \App\Support\MarketingUrl::to('terms') }}" target="_blank" rel="noopener" class="{{ $linkClass }}">{{ __('auth.terms.accept_link') }}<span class="sr-only"> {{ __('auth.terms.new_tab') }}</span></a>{{ __('auth.terms.accept_after') }}
        </label>
    </div>
    @error('terms')
        <p id="terms-error" class="text-[13px] font-medium text-danger">{{ $message }}</p>
    @enderror
    <p class="text-[13px] text-muted">
        {{ __('auth.terms.privacy_before') }}<a href="{{ \App\Support\MarketingUrl::to('privacy') }}" target="_blank" rel="noopener" class="{{ $linkClass }}">{{ __('auth.terms.privacy_link') }}<span class="sr-only"> {{ __('auth.terms.new_tab') }}</span></a>{{ __('auth.terms.privacy_after') }}
    </p>
</div>
