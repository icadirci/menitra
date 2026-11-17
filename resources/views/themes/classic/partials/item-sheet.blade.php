{{-- Product detail sheet (DetailKonak), client-side over rendered data; choices in menuItem. --}}
<div class="k-sheet" role="dialog" x-data="menuItem({{ $theme->cartEnabled() ? \Illuminate\Support\Js::from($item->cartPayload()) : 'null' }})" aria-modal="true" aria-labelledby="sheet-title-{{ $item->publicId }}"
     x-show="open === '{{ $item->publicId }}'" x-cloak x-transition.opacity tabindex="-1" x-ref="dlg-{{ $item->publicId }}">
    <div class="k-sheet__bar">
        <button type="button" class="k-back" @click="close()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="menu-flip-rtl" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"></path></svg>
            {{ __('menu.back_to_menu') }}
        </button>
        <span class="k-sheet__brand">{{ $menu->brand->name }}</span>
        <span class="k-sheet__spacer"></span>
    </div>
    <div class="k-arch k-arch--detail">
        <div class="k-arch__inner">
            @include('themes.classic.partials.picture', ['url' => $item->imageUrl, 'label' => $item->name, 'alt' => $item->name, 'width' => 284, 'height' => 284, 'class' => 'k-pic--detail'])
        </div>
    </div>
    <div class="k-sheet__body">
        <div class="k-sheet__head">
            <span class="k-tags k-tags--center">
                @if ($item->isPopular)
                    <span class="menu-badge menu-badge--accent">{{ __('menu.popular') }}</span>
                @endif
                @if ($live->isSoldOut($item))
                    <span class="menu-badge menu-badge--outline">{{ $live->soldOutUntil($item) ? __('menu.sold_out_until', ['time' => $live->soldOutUntil($item)]) : __('menu.sold_out') }}</span>
                @elseif (! $item->isAvailable)
                    <span class="menu-badge menu-badge--outline">{{ __('menu.unavailable_item') }}</span>
                @endif
            </span>
            <h2 id="sheet-title-{{ $item->publicId }}">{{ $item->name }}</h2>
            <x-menu.price :item="$item" class="k-price k-price--lg" />
            @if ($item->description)
                <p>{{ $item->description }}</p>
            @endif
        </div>

        @if ($item->variants !== [] && $theme->cartEnabled())
            <section class="k-sheet__section">
                <h3>{{ __('menu.variants') }}</h3>
                <div class="k-choices" role="radiogroup" aria-label="{{ __('menu.variants') }}">
                    @foreach ($item->variants as $variant)
                        <button type="button" class="k-choice" role="radio"
                                :aria-checked="(variant === '{{ $variant->publicId }}').toString()"
                                @click="variant = '{{ $variant->publicId }}'"
                                @disabled(! $variant->isAvailable)>
                            <span>{{ $variant->name }}</span>
                            <small>{{ $variant->isAvailable ? $variant->price->formatted : __('menu.sold_out') }}</small>
                        </button>
                    @endforeach
                </div>
            </section>
        @elseif ($item->variants !== [])
            <section class="k-sheet__section">
                <h3>{{ __('menu.variants') }}</h3>
                <ul class="k-variants">
                    @foreach ($item->variants as $variant)
                        <li class="{{ $variant->isAvailable ? '' : 'is-muted' }}">
                            <span>{{ $variant->name }}</span>
                            <span class="k-price">{{ $variant->isAvailable ? $variant->price->formatted : __('menu.sold_out') }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @foreach ($item->extraGroups as $group)
            <section class="k-sheet__section" data-group="{{ $loop->index }}" :class="{ 'is-missing': missing === {{ $loop->index }} }">
                <h3>
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
                <div class="k-choices" role="{{ $group->selectionType === 'single' ? 'radiogroup' : 'group' }}" aria-label="{{ $group->name }}">
                    @foreach ($group->extras as $extra)
                        <button type="button"
                                class="k-choice"
                                role="{{ $group->selectionType === 'single' ? 'radio' : 'checkbox' }}"
                                :aria-checked="isPicked({{ $loop->parent->index }}, '{{ $extra->publicId }}').toString()"
                                @click="pick({{ $loop->parent->index }}, '{{ $extra->publicId }}', {{ $group->selectionType === 'single' ? 'true' : 'false' }}, {{ $group->selectionType === 'single' ? 'null' : ($group->maxSelect ?? 'null') }})"
                                @disabled(! $extra->isAvailable)>
                            <span>{{ $extra->name }}</span>
                            @if ($extra->priceDelta)
                                <small>{{ $extra->priceDelta->minor > 0 ? '+' : '' }}{{ $extra->priceDelta->formatted }}</small>
                            @endif
                        </button>
                    @endforeach
                </div>
                <p class="k-hint">{{ __('menu.extras_hint') }}</p>
            </section>
        @endforeach

        @if ($item->preparationMinutes || $item->calories || $item->allergens !== [])
            <div class="k-facts">
                @if ($item->preparationMinutes)
                    <div class="k-fact"><span>{{ __('menu.preparation_title') }}</span><strong>{{ __('menu.preparation', ['count' => $item->preparationMinutes]) }}</strong></div>
                @endif
                @if ($item->calories)
                    <div class="k-fact"><span>{{ __('menu.calories_title') }}</span><strong>{{ __('menu.calories', ['count' => $item->calories]) }}</strong></div>
                @endif
                @if ($item->allergens !== [])
                    <div class="k-fact k-fact--wide"><span>{{ __('menu.allergens_title') }}</span><strong>{{ collect($item->allergens)->pluck('label')->implode(', ') }}</strong></div>
                @endif
            </div>
        @endif

        @if ($item->dietaryLabels !== [])
            <div class="k-tags"><x-menu.badges :labels="$item->dietaryLabels" /></div>
        @endif
    </div>
    @if ($theme->cartEnabled())
        <x-menu.cart-add :item="$item" :live="$live" />
    @endif
</div>
