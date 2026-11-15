{{--
    Shared body of a product detail sheet: variants, extra groups (display-only
    choices, Alpine), preparation / calories / allergens and dietary labels.
    Themes style it under [data-theme] (classes menu-detail*).
--}}
@props(['item'])
<div {{ $attributes->merge(['class' => 'menu-detail']) }}>
    @if ($item->variants !== [])
        <section class="menu-detail__section">
            <h3 class="menu-detail__title">{{ __('menu.variants') }}</h3>
            <dl class="menu-detail__list">
                @foreach ($item->variants as $variant)
                    <div class="menu-detail__row {{ $variant->isAvailable ? '' : 'is-muted' }}">
                        <dt>{{ $variant->name }}</dt>
                        <dd class="menu-price">{{ $variant->isAvailable ? $variant->price->formatted : __('menu.sold_out') }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif

    @foreach ($item->extraGroups as $group)
        <section class="menu-detail__section" x-data="{ picked: [] }">
            <h3 class="menu-detail__title">
                {{ $group->name }}
                <small>
                    @if ($group->isRequired) {{ __('menu.extras_required') }} · @endif
                    @if ($group->selectionType === 'single')
                        {{ __('menu.extras_choose_one') }}
                    @elseif ($group->maxSelect)
                        {{ $group->minSelect > 0 ? __('menu.extras_choose_between', ['min' => $group->minSelect, 'max' => $group->maxSelect]) : __('menu.extras_choose_up_to', ['count' => $group->maxSelect]) }}
                    @endif
                </small>
            </h3>
            <div class="menu-choices" role="{{ $group->selectionType === 'single' ? 'radiogroup' : 'group' }}" aria-label="{{ $group->name }}">
                @foreach ($group->extras as $extra)
                    <button type="button"
                            class="menu-choice"
                            role="{{ $group->selectionType === 'single' ? 'radio' : 'checkbox' }}"
                            :aria-checked="picked.includes('{{ $extra->publicId }}').toString()"
                            @click="picked = {{ $group->selectionType === 'single' ? "['{$extra->publicId}']" : "picked.includes('{$extra->publicId}') ? picked.filter(p => p !== '{$extra->publicId}') : [...picked, '{$extra->publicId}']" }}"
                            @disabled(! $extra->isAvailable)>
                        <span>{{ $extra->name }}</span>
                        @if ($extra->priceDelta)
                            <small class="menu-price">{{ $extra->priceDelta->minor > 0 ? '+' : '' }}{{ $extra->priceDelta->formatted }}</small>
                        @endif
                    </button>
                @endforeach
            </div>
            <p class="menu-detail__hint">{{ __('menu.extras_hint') }}</p>
        </section>
    @endforeach

    @if ($item->preparationMinutes || $item->calories || $item->allergens !== [])
        <dl class="menu-facts">
            @if ($item->preparationMinutes)
                <div class="menu-fact"><dt>{{ __('menu.preparation_title') }}</dt><dd>{{ __('menu.preparation', ['count' => $item->preparationMinutes]) }}</dd></div>
            @endif
            @if ($item->calories)
                <div class="menu-fact"><dt>{{ __('menu.calories_title') }}</dt><dd>{{ __('menu.calories', ['count' => $item->calories]) }}</dd></div>
            @endif
            @if ($item->allergens !== [])
                <div class="menu-fact menu-fact--wide"><dt>{{ __('menu.allergens_title') }}</dt><dd>{{ collect($item->allergens)->pluck('label')->implode(', ') }}</dd></div>
            @endif
        </dl>
    @endif

    @if ($item->dietaryLabels !== [])
        <div class="menu-detail__labels"><x-menu.badges :labels="$item->dietaryLabels" /></div>
    @endif
</div>
