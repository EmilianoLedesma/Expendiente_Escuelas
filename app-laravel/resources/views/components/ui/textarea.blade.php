@aware(['id' => null, 'hint' => null])

@php
    $campoId = $attributes->get('id') ?? $id;
    $invalido = $campoId !== null && $errors->has($campoId);
    $describe = trim(($hint ? "{$campoId}-hint " : '').($invalido ? "{$campoId}-error" : ''));
@endphp

<textarea
    id="{{ $campoId }}"
    @if ($describe !== '') aria-describedby="{{ $describe }}" @endif
    @if ($invalido) aria-invalid="true" @endif
    {{ $attributes->except('id')->merge([
        'rows' => 4,
        'class' => 'block w-full rounded-sm border bg-canvas px-sm py-xs text-body-md text-ink transition-colors duration-150 '
            .($invalido ? 'border-2 border-error-ink' : 'border-control'),
    ]) }}
></textarea>
