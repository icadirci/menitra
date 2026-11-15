@props(['icon', 'label'])

{{-- One row of the dark "other ways to reach us" card (contact page). --}}
<div class="flex items-start gap-3.5">
    <span class="inline-flex size-10 flex-none items-center justify-center rounded-input bg-[#262C28] text-saffron-500">
        <x-ui.icon :name="$icon" />
    </span>
    <div class="min-w-0 leading-[1.45]">
        <span class="block text-[13px] text-[#A9AEAA]">{{ $label }}</span>
        {{ $slot }}
    </div>
</div>
