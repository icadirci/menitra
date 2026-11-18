{{--
    Checkout plan cards (App\Filament\App\Pages\Checkout). The whole card is a
    radio: click, Enter or Space calls selectPlan(), which re-checks the code
    on the server. Rows are aligned across cards with CSS subgrid (header,
    price, highlights, branch note, action), so cards of different content
    still line up. Colours come from Filament's CSS variables (light + dark).
    Every value is escaped ({{ }}).
--}}
<style>
    .rst-plans { display: grid; gap: 1.25rem 1rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 12rem), 1fr)); }
    .rst-plan { display: grid; grid-row: span 5; grid-template-rows: subgrid; row-gap: 0; position: relative;
        border: 1px solid var(--gray-200); border-radius: 0.75rem; cursor: pointer; text-align: start;
        transition: border-color .15s, box-shadow .15s, background-color .15s; outline: none; }
    .rst-plan:hover { border-color: var(--gray-300); }
    .rst-plan:focus-visible { box-shadow: 0 0 0 3px color-mix(in oklab, var(--primary-500) 35%, transparent); }
    .rst-plan[aria-checked="true"] { border-color: var(--primary-600); box-shadow: 0 0 0 1px var(--primary-600);
        background: color-mix(in oklab, var(--primary-50) 55%, transparent); }
    .rst-plan > :not(.rst-plan__badge) { padding-inline: 1rem; }
    .rst-plan__head { padding-top: 1.25rem !important; padding-bottom: 1rem !important; border-bottom: 1px solid var(--gray-200); display: flex; flex-direction: column; gap: .25rem; }
    .rst-plan__name { font-weight: 600; font-size: 1rem; color: var(--gray-950); }
    .rst-plan__desc { font-size: .875rem; color: var(--gray-500); }
    .rst-plan__badge { position: absolute; top: -0.7rem; inset-inline-end: 1rem; font-size: .75rem; font-weight: 600;
        padding: .125rem .5rem; border-radius: .375rem; border: 1px solid; background: white; }
    .rst-plan__badge--primary { color: var(--primary-700); border-color: var(--primary-200); background: var(--primary-50); }
    .rst-plan__badge--success { color: var(--success-700); border-color: var(--success-200); background: var(--success-50); }
    .rst-plan__badge--gray { color: var(--gray-700); border-color: var(--gray-200); background: var(--gray-50); }
    .rst-plan__price { padding-top: 1rem !important; padding-bottom: .75rem !important; display: flex; flex-direction: column; gap: .25rem; }
    .rst-plan__amount { font-size: 1.5rem; line-height: 2rem; font-weight: 700; color: var(--gray-950); }
    .rst-plan__suffix { font-size: .875rem; font-weight: 400; color: var(--gray-500); }
    .rst-plan__muted { font-size: .875rem; color: var(--gray-500); }
    .rst-plan__saving { font-size: .875rem; font-weight: 600; color: var(--success-600); }
    .rst-plan__upgrade { font-size: .875rem; font-weight: 600; color: var(--primary-600); }
    .rst-plan__list { list-style: none; margin: 0; padding-bottom: .75rem !important; display: flex; flex-direction: column; gap: .4rem; font-size: .875rem; color: var(--gray-700); }
    .rst-plan__list li { display: flex; gap: .5rem; align-items: flex-start; }
    .rst-plan__list svg { flex: none; width: 1rem; height: 1rem; margin-top: .15rem; color: var(--success-600); }
    .rst-plan__branch { font-size: .8125rem; color: var(--gray-500); padding-bottom: .75rem !important; }
    .rst-plan__foot { padding-top: 1rem !important; padding-bottom: 1.25rem !important; border-top: 1px solid var(--gray-200); }
    .rst-plan__cta { display: flex; width: 100%; justify-content: center; align-items: center; gap: .375rem; font-size: .875rem; font-weight: 600;
        padding: .5rem .75rem; border-radius: .5rem; border: 1px solid var(--gray-300); color: var(--gray-950); }
    .rst-plan[aria-checked="true"] .rst-plan__cta { background: var(--primary-600); border-color: var(--primary-600); color: white; }
    .rst-plan__cta svg { width: 1rem; height: 1rem; }

    .dark .rst-plan { border-color: rgb(255 255 255 / .1); }
    .dark .rst-plan:hover { border-color: rgb(255 255 255 / .2); }
    .dark .rst-plan[aria-checked="true"] { border-color: var(--primary-500); box-shadow: 0 0 0 1px var(--primary-500);
        background: color-mix(in oklab, var(--primary-500) 8%, transparent); }
    .dark .rst-plan__head, .dark .rst-plan__foot { border-color: rgb(255 255 255 / .1); }
    .dark .rst-plan__name, .dark .rst-plan__amount, .dark .rst-plan__cta { color: white; }
    .dark .rst-plan__desc, .dark .rst-plan__suffix, .dark .rst-plan__muted, .dark .rst-plan__branch { color: var(--gray-400); }
    .dark .rst-plan__list { color: var(--gray-300); }
    .dark .rst-plan__saving, .dark .rst-plan__list svg { color: var(--success-400); }
    .dark .rst-plan__upgrade { color: var(--primary-400); }
    .dark .rst-plan__cta { border-color: rgb(255 255 255 / .2); }
    .dark .rst-plan[aria-checked="true"] .rst-plan__cta { background: var(--primary-500); border-color: var(--primary-500); }
    .dark .rst-plan__badge { background: var(--gray-900); }
    .dark .rst-plan__badge--primary { color: var(--primary-300); border-color: color-mix(in oklab, var(--primary-400) 40%, transparent); }
    .dark .rst-plan__badge--success { color: var(--success-300); border-color: color-mix(in oklab, var(--success-400) 40%, transparent); }
    .dark .rst-plan__badge--gray { color: var(--gray-300); border-color: rgb(255 255 255 / .15); }
</style>

<div class="rst-plans" role="radiogroup" aria-label="{{ $label }}" style="padding-top: .75rem;">
    @foreach ($cards as $card)
        <div
            wire:key="plan-card-{{ $card['code'] }}"
            class="rst-plan"
            role="radio"
            tabindex="{{ $card['selected'] ? 0 : -1 }}"
            aria-checked="{{ $card['selected'] ? 'true' : 'false' }}"
            data-plan="{{ $card['code'] }}"
            wire:click="selectPlan(@js($card['code']))"
            x-on:keydown.enter.prevent="$wire.selectPlan(@js($card['code']))"
            x-on:keydown.space.prevent="$wire.selectPlan(@js($card['code']))"
            x-on:keydown.right.prevent="$el.nextElementSibling?.focus()"
            x-on:keydown.left.prevent="$el.previousElementSibling?.focus()"
        >
            @if ($card['badge'])
                <span class="rst-plan__badge rst-plan__badge--{{ $card['badge']['tone'] }}">{{ $card['badge']['label'] }}</span>
            @endif

            <div class="rst-plan__head">
                <span class="rst-plan__name">{{ $card['name'] }}</span>
                @if ($card['description'])
                    <span class="rst-plan__desc">{{ $card['description'] }}</span>
                @endif
            </div>

            <div class="rst-plan__price">
                <span class="rst-plan__amount">{{ $card['price'] }} <span class="rst-plan__suffix">{{ $card['suffix'] }}</span></span>
                @if ($card['note'])
                    <span class="rst-plan__muted">{{ $card['note'] }}</span>
                @endif
                @if ($card['saving'])
                    <span class="rst-plan__saving">{{ $card['saving'] }}</span>
                @endif
                @if ($card['upgrade'])
                    <span class="rst-plan__upgrade">{{ $card['upgrade'] }}</span>
                @endif
            </div>

            <ul class="rst-plan__list">
                @foreach ($card['highlights'] as $point)
                    <li>
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                        <span>{{ $point }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="rst-plan__branch">{{ $card['branch'] }}</div>

            <div class="rst-plan__foot">
                <span class="rst-plan__cta">
                    @if ($card['selected'])
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/></svg>
                    @endif
                    {{ $card['action'] }}
                </span>
            </div>
        </div>
    @endforeach
</div>
