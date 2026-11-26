{{--
    Swipeable product images (scroll snap; works without JS). Alpine only adds
    the previous/next buttons and the counter.
--}}
@props(['images', 'alt'])

@if (count($images) === 1)
    <img src="{{ $images[0] }}" alt="{{ $alt }}" width="600" height="450" loading="lazy" class="aspect-[4/3] w-full object-cover">
@elseif (count($images) > 1)
    <div class="relative"
        role="group"
        aria-roledescription="carousel"
        aria-label="{{ __('shop.gallery') }}"
        x-data="{ active: 0, count: {{ count($images) }}, go(step) { this.active = (this.active + step + this.count) % this.count; this.$refs.track.scrollTo({ left: this.$refs.track.clientWidth * this.active, behavior: 'smooth' }); } }">
        <div x-ref="track"
            class="flex snap-x snap-mandatory overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            x-on:scroll.debounce.100ms="active = Math.round($el.scrollLeft / $el.clientWidth)">
            @foreach ($images as $index => $url)
                <img src="{{ $url }}" alt="{{ $alt }} ({{ $index + 1 }}/{{ count($images) }})" width="600" height="450" loading="lazy" class="aspect-[4/3] w-full flex-none snap-start object-cover">
            @endforeach
        </div>
        <button type="button" x-cloak x-on:click="go(-1)" aria-label="{{ __('shop.gallery_prev') }}"
            class="absolute top-1/2 left-3 grid size-9 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-ink shadow-sm hover:bg-white focus-visible:outline-2 focus-visible:outline-forest-700">
            <x-ui.icon name="arrow-left" :size="18" />
        </button>
        <button type="button" x-cloak x-on:click="go(1)" aria-label="{{ __('shop.gallery_next') }}"
            class="absolute top-1/2 right-3 grid size-9 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-ink shadow-sm hover:bg-white focus-visible:outline-2 focus-visible:outline-forest-700">
            <x-ui.icon name="arrow-right" :size="18" />
        </button>
        <span x-cloak aria-live="polite" class="absolute right-3 bottom-3 rounded-full bg-black/65 px-2.5 py-0.5 text-xs font-semibold text-white"
            x-text="(active + 1) + ' / ' + count"></span>
    </div>
@endif
