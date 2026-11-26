{{-- QR stand shop (App\Filament\App\Pages\Shop). Every value is escaped. --}}
<x-filament-panels::page>
    <style>
        .mn-shop { display: grid; gap: 1.25rem; grid-template-columns: repeat(auto-fill, minmax(min(100%, 16rem), 1fr)); }
        .mn-shop__card { display: flex; flex-direction: column; overflow: hidden; background: #fff; border: 1px solid var(--gray-200); border-radius: .75rem; }
        .mn-shop__img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; background: var(--gray-100); }
        .mn-shop__body { display: flex; flex: 1; flex-direction: column; gap: .5rem; padding: 1rem; }
        .mn-shop__name { font-weight: 600; color: var(--gray-950); }
        .mn-shop__muted { font-size: .875rem; color: var(--gray-500); white-space: pre-line; }
        .mn-shop__price { margin-top: auto; padding-top: .5rem; font-size: 1.25rem; font-weight: 700; color: var(--gray-950); }
        .dark .mn-shop__card { background: var(--gray-900); border-color: rgb(255 255 255 / .1); }
        .dark .mn-shop__img { background: var(--gray-800); }
        .dark .mn-shop__name, .dark .mn-shop__price { color: #fff; }
        .dark .mn-shop__muted { color: var(--gray-400); }
    </style>

    @if ($products->isEmpty())
        <p class="mn-shop__muted">{{ __('shop.empty') }}</p>
    @else
        <div class="mn-shop">
            @foreach ($products as $product)
                <article class="mn-shop__card" wire:key="product-{{ $product->public_id }}">
                    @if ($product->imageUrl())
                        <img class="mn-shop__img" src="{{ $product->imageUrl() }}" alt="" width="400" height="300" loading="lazy">
                    @endif
                    <div class="mn-shop__body">
                        <h2 class="mn-shop__name">{{ $product->translate()?->name }}</h2>
                        @if ($product->translate()?->description)
                            <p class="mn-shop__muted">{{ $product->translate()->description }}</p>
                        @endif
                        @if ($product->is_personalized)
                            <p class="mn-shop__muted">{{ __('shop.personalized') }}</p>
                        @endif
                        <p class="mn-shop__price">{{ $product->price()->format(omitZeroFraction: true) }} <span class="mn-shop__muted">{{ __('shop.per_unit') }}</span></p>
                        @if ($product->availableQuantity() > 0)
                            {{ ($this->addToCartAction)(['product' => $product->public_id]) }}
                        @else
                            <p class="mn-shop__muted">{{ __('shop.sold_out') }}</p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
        <p class="mn-shop__muted">{{ implode(' · ', $notes) }}</p>
    @endif
</x-filament-panels::page>
