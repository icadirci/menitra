{{-- Product details modal of App\Filament\App\Pages\Shop. The description is Markdown rendered by ShopProduct::descriptionHtml() (raw HTML stripped). --}}
<div class="mn-shop-detail">
    <style>
        .mn-shop-detail { display: grid; gap: 1rem; }
        .mn-shop-detail__img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: .75rem; background: var(--gray-100); }
        .mn-shop-detail__gallery { display: grid; gap: .5rem; }
        .mn-shop-detail__thumbs { display: flex; gap: .5rem; overflow-x: auto; padding-bottom: .25rem; }
        .mn-shop-detail__thumb { flex: none; width: 4.5rem; padding: 0; border: 2px solid transparent; border-radius: .5rem; overflow: hidden; background: none; cursor: pointer; opacity: .7; }
        .mn-shop-detail__thumb[aria-pressed='true'] { border-color: var(--primary-500); opacity: 1; }
        .mn-shop-detail__thumb:focus-visible { outline: 2px solid var(--primary-500); outline-offset: 2px; }
        .mn-shop-detail__thumb img { display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: cover; }
        .mn-shop-detail__text { font-size: .9375rem; line-height: 1.6; color: var(--gray-700); }
        .mn-shop-detail__text > * + * { margin-top: .75rem; }
        .mn-shop-detail__text strong { font-weight: 600; color: var(--gray-950); }
        .mn-shop-detail__text ul { list-style: disc; padding-left: 1.25rem; }
        .mn-shop-detail__text ol { list-style: decimal; padding-left: 1.25rem; }
        .mn-shop-detail__text a { color: var(--primary-600); text-decoration: underline; }
        .mn-shop-detail__meta { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: .5rem; }
        .mn-shop-detail__price { font-size: 1.5rem; font-weight: 700; color: var(--gray-950); }
        .mn-shop-detail__muted { font-size: .875rem; color: var(--gray-500); }
        .dark .mn-shop-detail__img { background: var(--gray-800); }
        .dark .mn-shop-detail__text { color: var(--gray-300); }
        .dark .mn-shop-detail__text strong, .dark .mn-shop-detail__price { color: #fff; }
        .dark .mn-shop-detail__text a { color: var(--primary-400); }
        .dark .mn-shop-detail__muted { color: var(--gray-400); }
    </style>

    @php($images = $product->imageUrls())
    @if (count($images) === 1)
        <img class="mn-shop-detail__img" src="{{ $images[0] }}" alt="{{ $product->translate()?->name }}" width="800" height="600">
    @elseif (count($images) > 1)
        <div x-data="{ active: 0 }" class="mn-shop-detail__gallery">
            @foreach ($images as $index => $url)
                <img class="mn-shop-detail__img" src="{{ $url }}" alt="{{ $product->translate()?->name }} ({{ $index + 1 }}/{{ count($images) }})" width="800" height="600" @if ($index > 0) loading="lazy" @endif x-show="active === {{ $index }}" @if ($index > 0) x-cloak @endif>
            @endforeach
            <div class="mn-shop-detail__thumbs" role="group" aria-label="{{ __('shop.gallery') }}">
                @foreach ($images as $index => $url)
                    <button type="button" class="mn-shop-detail__thumb" x-on:click="active = {{ $index }}" x-bind:aria-pressed="active === {{ $index }} ? 'true' : 'false'" aria-label="{{ __('shop.gallery_show', ['number' => $index + 1]) }}">
                        <img src="{{ $url }}" alt="" width="80" height="60" loading="lazy">
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mn-shop-detail__meta">
        <p class="mn-shop-detail__price">{{ $product->price()->format(omitZeroFraction: true) }} <span class="mn-shop-detail__muted">{{ __('shop.per_unit') }}</span></p>
        <p class="mn-shop-detail__muted">
            {{ $product->availableQuantity() > 0 ? ($product->is_personalized ? __('shop.personalized') : '') : __('shop.sold_out') }}
        </p>
    </div>

    @if ($description = $product->descriptionHtml())
        <div class="mn-shop-detail__text">{{ $description }}</div>
    @endif
</div>
