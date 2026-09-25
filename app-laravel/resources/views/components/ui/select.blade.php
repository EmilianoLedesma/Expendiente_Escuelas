@aware(['id' => null, 'hint' => null])

@php
    $campoId = $attributes->get('id') ?? $id;
    $invalido = $campoId !== null && $errors->has($campoId);
    $describe = trim(($hint ? "{$campoId}-hint " : '').($invalido ? "{$campoId}-error" : ''));
@endphp

<select
    id="{{ $campoId }}"
    @if ($describe !== '') aria-describedby="{{ $describe }}" @endif
    @if ($invalido) aria-invalid="true" @endif
    {{ $attributes->except('id')->merge([
        'class' => 'block w-full min-h-11 rounded-md border bg-canvas px-sm text-body-md text-ink transition-colors duration-150 '
            .($invalido ? 'border-2 border-error-ink' : 'border-control'),
    ]) }}
>
    {{ $slot }}
</select>
