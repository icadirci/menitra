{{-- Cart lines of App\Filament\App\Pages\ShopCheckout. Every value is escaped. --}}
<style>
    .mn-cart { list-style: none; margin: 0; padding: 0; font-size: .875rem; }
    .mn-cart li { display: flex; gap: 1rem; align-items: center; justify-content: space-between; padding: .625rem 0; border-bottom: 1px solid var(--gray-200); }
    .mn-cart li:last-child { border-bottom: 0; }
    .mn-cart__main { min-width: 0; flex: 1; }
    .mn-cart__muted { color: var(--gray-500); }
    .mn-cart__amount { font-weight: 600; white-space: nowrap; }
    .mn-cart button { color: var(--danger-600); text-decoration: underline; cursor: pointer; }
    .dark .mn-cart li { border-color: rgb(255 255 255 / .1); }
</style>
<ul class="mn-cart">
    @foreach ($items as $item)
        <li wire:key="cart-{{ $item->getKey() }}">
            <div class="mn-cart__main">
                <strong>{{ $item->quantity }} × {{ $item->product->translate()?->name }}</strong>
                @if ($item->qrCode)
                    <div class="mn-cart__muted">{{ __('shop.printed_code') }}: {{ $item->qrCode->label }} ({{ $item->qrCode->code }})</div>
                @endif
            </div>
            <span class="mn-cart__amount">{{ $item->total()->format() }}</span>
            <button type="button" wire:click="removeItem({{ $item->getKey() }})">{{ __('shop.actions.remove') }}</button>
        </li>
    @endforeach
</ul>
