@props([
    // App\Enums\MenuTheme value; the sample content comes from lang marketing.themes.{theme}.
    'theme' => 'cafe',
])

@php
    // Marketing illustration of each theme's identity (docs/design/source/Menu*.dc.html).
    // Not the real theme: real menus are rendered by resources/views/themes/{key}.
    $palettes = [
        'cafe' => ['bg' => '#F3ECE2', 'surface' => '#FBF7F1', 'ink' => '#3B2A20', 'muted' => '#6E5C50', 'accent' => '#A0714F', 'chip' => '#3B2A20', 'chipInk' => '#FBF7F1', 'dish' => '#E9DCCB', 'radius' => '20px', 'display' => "'Inter', sans-serif", 'weight' => 800, 'serif' => false],
        'bistro' => ['bg' => '#0F0E0C', 'surface' => '#1A1815', 'ink' => '#EDE6D8', 'muted' => '#A79F90', 'accent' => '#C9A96E', 'chip' => '#C9A96E', 'chipInk' => '#0F0E0C', 'dish' => '#1C1A16', 'radius' => '2px', 'display' => "Georgia, 'Times New Roman', serif", 'weight' => 500, 'serif' => true],
        'minimal' => ['bg' => '#FFFFFF', 'surface' => '#F2F1EE', 'ink' => '#111111', 'muted' => '#6B6B68', 'accent' => '#B5401C', 'chip' => '#111111', 'chipInk' => '#FFFFFF', 'dish' => '#F2F1EE', 'radius' => '12px', 'display' => "'Inter', sans-serif", 'weight' => 700, 'serif' => false],
        'grid' => ['bg' => '#FFF6F3', 'surface' => '#FFFFFF', 'ink' => '#3D2A35', 'muted' => '#7A5F6C', 'accent' => '#C2456A', 'chip' => '#F08FA7', 'chipInk' => '#3D2A35', 'dish' => '#FBE3E8', 'radius' => '28px', 'display' => "Georgia, 'Times New Roman', serif", 'weight' => 700, 'serif' => true],
        'classic' => ['bg' => '#F4EBDD', 'surface' => '#FBF5EA', 'ink' => '#23443A', 'muted' => '#6B5446', 'accent' => '#9C4A2F', 'chip' => '#23443A', 'chipInk' => '#F4EBDD', 'dish' => '#EADFC9', 'radius' => '12px', 'display' => "Georgia, 'Times New Roman', serif", 'weight' => 400, 'serif' => true],
    ];

    $theme = array_key_exists($theme, $palettes) ? $theme : 'cafe';
    $palette = $palettes[$theme];

    $sample = __("marketing.themes.{$theme}");
    $items = $sample['items'];
@endphp

<div
    class="min-h-[900px] w-[390px] bg-(--p-bg) font-sans text-[15px] leading-normal text-(--p-ink)"
    style="--p-bg: {{ $palette['bg'] }}; --p-surface: {{ $palette['surface'] }}; --p-ink: {{ $palette['ink'] }}; --p-muted: {{ $palette['muted'] }}; --p-accent: {{ $palette['accent'] }}; --p-chip: {{ $palette['chip'] }}; --p-chip-ink: {{ $palette['chipInk'] }}; --p-radius: {{ $palette['radius'] }}; --p-display: {{ $palette['display'] }}"
>
    {{-- Cover --}}
    <div class="relative overflow-hidden" style="height: 250px">
        <x-marketing.dish :kind="$items[0]['dish']" :bg="$palette['dish']" ratio="390 / 250" pad="6%" class="size-full" />
    </div>

    {{-- Venue header --}}
    <div @class(['-mt-8 relative mx-4 flex flex-col gap-1.5 rounded-(--p-radius) bg-(--p-surface) p-5', 'items-center text-center' => $theme === 'bistro'])>
        <p class="text-[30px] leading-[1.05] tracking-[-0.02em]" style="font-family: var(--p-display); font-weight: {{ $palette['weight'] }}">{{ $sample['venue'] }}</p>
        <p class="text-[13px] text-(--p-muted)">{{ $sample['kind'] }}</p>
    </div>

    {{-- Category chips --}}
    <div class="flex gap-2 overflow-hidden px-4 pt-5">
        <span class="rounded-full bg-(--p-chip) px-4 py-2 text-[13px] font-bold text-(--p-chip-ink)">{{ $sample['section'] }}</span>
        @foreach (array_slice($items, 1) as $item)
            <span class="rounded-full border border-(--p-muted)/40 px-4 py-2 text-[13px] font-semibold whitespace-nowrap text-(--p-ink)">{{ $item['name'] }}</span>
        @endforeach
    </div>

    <p class="px-5 pt-6 pb-3 text-[22px] tracking-[-0.02em]" style="font-family: var(--p-display); font-weight: {{ $palette['weight'] }}">{{ $sample['section'] }}</p>

    @if ($theme === 'grid')
        <div class="grid grid-cols-2 gap-3 px-4">
            @foreach ($items as $item)
                <div class="overflow-hidden rounded-(--p-radius) bg-(--p-surface)">
                    <x-marketing.dish :kind="$item['dish']" :bg="$palette['dish']" ratio="1 / 1" pad="10%" />
                    <div class="flex flex-col gap-0.5 p-3">
                        <span class="font-bold">{{ $item['name'] }}</span>
                        <span class="text-[14px] font-bold text-(--p-accent)">{{ $item['price'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div @class(['flex flex-col px-4', 'gap-3' => $theme !== 'minimal'])>
            @foreach ($items as $item)
                <div @class([
                    'flex items-center gap-3.5 p-3',
                    'rounded-(--p-radius) bg-(--p-surface)' => $theme !== 'minimal',
                    'border-b border-[#EDEDEA] px-1 py-3.5' => $theme === 'minimal',
                    'border border-[#2A2621]' => $theme === 'bistro',
                ])>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold" @if ($palette['serif']) style="font-family: var(--p-display); font-size: 18px; font-weight: 500" @endif>{{ $item['name'] }}</p>
                        <p class="truncate text-[13px] text-(--p-muted)">{{ $item['note'] }}</p>
                        <p @class(['mt-1 text-[15px] font-bold text-(--p-accent)', 'font-mono font-medium text-(--p-ink)' => $theme === 'minimal'])>{{ $item['price'] }}</p>
                    </div>
                    <div
                        class="w-[84px] shrink-0 overflow-hidden"
                        style="border-radius: {{ $theme === 'classic' ? '42px 42px 10px 10px' : 'var(--p-radius)' }}"
                    >
                        <x-marketing.dish :kind="$item['dish']" :bg="$palette['dish']" :ratio="$theme === 'classic' ? '4 / 5' : '1 / 1'" pad="6%" />
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
