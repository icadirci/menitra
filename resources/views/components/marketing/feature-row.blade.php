@props([
    'badge',
    'title',
    'body',
    'points' => [],
    // Visual first on wide screens; on narrow screens the text always comes first.
    'reverse' => false,
])

{{-- Text + illustration row of the features page (Features.dc.html). The illustration goes in the default slot. --}}
<section {{ $attributes->class('px-5 py-[clamp(40px,6vw,72px)] sm:px-6') }}>
    <div class="mx-auto flex max-w-[1200px] flex-wrap items-center gap-[clamp(40px,6vw,88px)]">
        <div @class(['flex min-w-0 flex-[1_1_440px] flex-col gap-5', 'lg:order-2' => $reverse]) data-animate="fadeInUp">
            <p class="self-start rounded-full bg-forest-100 px-3 py-1.5 text-[13px] font-semibold text-forest-700">{{ $badge }}</p>
            <h2 class="font-display text-[clamp(30px,3.4vw,44px)] leading-[1.06] font-bold tracking-[-0.03em] text-balance">{{ $title }}</h2>
            <p class="max-w-[480px] text-pretty text-muted">{{ $body }}</p>
            @if ($points !== [])
                <ul class="flex flex-col gap-3">
                    @foreach ($points as $point)
                        <li class="flex items-start gap-3">
                            <x-ui.icon name="check" :size="22" :stroke="2" class="text-forest-700" />
                            <span>{{ $point }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div @class(['min-w-0 flex-[1_1_440px]', 'lg:order-1' => $reverse]) data-animate="fadeInUp" data-animate-delay="150">
            {{ $slot }}
        </div>
    </div>
</section>
