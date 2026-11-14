@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'help' => null,
])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-zinc-800">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $type === 'password' ? '' : old($name, $value) }}"
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
        {{ $attributes->class([
            'mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm outline-none focus:ring-2 focus:ring-amber-500',
            'border-red-400' => $errors->has($name),
            'border-zinc-300' => ! $errors->has($name),
        ]) }}
    >
    @if ($help)
        <p class="mt-1 text-xs text-zinc-500">{{ $help }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
