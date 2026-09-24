{{-- Dentro de <x-ui.field> hereda id y hint (@aware); suelto (tablas) recibe su propio id=, que gana. --}}
@aware(['id' => null, 'hint' => null])
@props(['unit' => null])

@php
    $campoId = $attributes->get('id') ?? $id;
    $invalido = $campoId !== null && $errors->has($campoId);
    $describe = trim(($hint ? "{$campoId}-hint " : '').($invalido ? "{$campoId}-error" : ''));
@endphp

<div class="flex items-center gap-xs">
    <input
        id="{{ $campoId }}"
        @if ($describe !== '') aria-describedby="{{ $describe }}" @endif
        @if ($invalido) aria-invalid="true" @endif
        {{ $attributes->except('id')->merge([
            'type' => 'text',
            'class' => 'block w-full min-h-11 rounded-sm border bg-canvas px-sm text-body-md text-ink transition-colors duration-150 '
                .($invalido ? 'border-2 border-error-ink' : 'border-control'),
        ]) }}
    >
    @if ($unit)
        <span class="shrink-0 text-body-md text-muted" aria-hidden="true">{{ $unit }}</span>
    @endif
</div>
