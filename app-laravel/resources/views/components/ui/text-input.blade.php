@props([
    'name' => null,
    'label' => null,
    'type' => 'text',
    'required' => false,
    'disabled' => false,
    'hint' => null,
])

@php
    $hasError = $name && $errors->has($name);
    $borderClass = match (true) {
        $disabled => 'border-hairline bg-surface-soft text-muted cursor-not-allowed',
        $hasError => 'border-error focus:border-error focus:ring-error/10',
        default => 'border-hairline focus:border-primary focus:ring-primary/10',
    };
@endphp

<div>
    @if ($label)
        <label @if($name) for="{{ $name }}" @endif class="block font-sans text-body-sm font-medium text-ink mb-xxs">
            {{ $label }}
            @if ($required)
                <span class="text-error">*</span>
            @endif
        </label>
    @endif

    <input
        @if($name) id="{{ $name }}" name="{{ $name }}" @endif
        type="{{ $type }}"
        @disabled($disabled)
        {{ $attributes->merge([
            'class' => "w-full h-[38px] px-[13px] py-[9px] rounded-md border-[0.5px] font-sans text-body-sm text-ink bg-canvas focus:outline-none focus:ring-[3px] $borderClass",
        ]) }}
    >

    @if ($hasError)
        <p class="mt-xxs font-sans text-[11px] text-error">{{ $errors->first($name) }}</p>
    @elseif ($hint)
        <p class="mt-xxs font-sans text-[11px] text-muted">{{ $hint }}</p>
    @endif
</div>
