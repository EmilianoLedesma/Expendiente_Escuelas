@props(['estado', 'texto' => null])

@php
    [$icono, $etiqueta, $color] = match ($estado) {
        'completado' => ['check-circle', 'Completado', 'text-success-ink'],
        'en_curso' => ['arrow-path', 'En curso', 'text-warning-ink'],
        'error' => ['exclamation-circle', 'Requiere corrección', 'text-error-ink'],
        'no_disponible' => ['clock', 'No disponible aún', 'text-muted'],
        'no_aplica' => ['no-symbol', 'No aplica para este nivel', 'text-muted'],
        default => ['minus-circle', 'Pendiente', 'text-info-ink'],
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex shrink-0 items-center gap-xxs whitespace-nowrap text-body-sm font-semibold $color"]) }}>
    <x-ui.icon :nombre="$icono" class="h-4 w-4" />
    {{ $texto ?? $etiqueta }}
</span>
