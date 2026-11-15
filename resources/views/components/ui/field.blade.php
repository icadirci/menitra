@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'help' => null,
])

@php
    $isPassword = $type === 'password';
    $describedBy = collect([
        $help ? $name.'-help' : null,
        $errors->has($name) ? $name.'-error' : null,
    ])->filter()->implode(' ');
@endphp

<div class="flex flex-col gap-[7px]">
    <label for="{{ $name }}" class="text-sm font-semibold text-ink">{{ $label }}</label>

    <div @class(['relative' => $isPassword]) @if ($isPassword) x-data="{ shown: false }" @endif>
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ $isPassword ? '' : old($name, $value) }}"
            @if ($isPassword) x-bind:type="shown ? 'text' : 'password'" @endif
            @error($name) aria-invalid="true" @enderror
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->class([
                'block h-[50px] w-full rounded-input border bg-white px-3.5 text-base text-ink placeholder:text-[#8A8E8B]',
                'pr-14' => $isPassword,
                'border-danger' => $errors->has($name),
                'border-line-strong' => ! $errors->has($name),
            ]) }}
        >
        @if ($isPassword)
            <button
                type="button"
                class="absolute top-[3px] right-1 inline-flex size-11 cursor-pointer items-center justify-center rounded-input text-muted hover:text-ink"
                x-on:click="shown = ! shown"
                x-bind:aria-pressed="shown.toString()"
                aria-label="{{ __('auth.password_toggle') }}"
            >
                <x-ui.icon name="eye" x-show="! shown" />
                <x-ui.icon name="eye-off" x-show="shown" x-cloak />
            </button>
        @endif
    </div>

    @if ($help)
        <p id="{{ $name }}-help" class="text-[13px] text-muted">{{ $help }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="text-[13px] font-medium text-danger">{{ $message }}</p>
    @enderror
</div>
