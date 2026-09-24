@props(['estado', 'texto' => null])

@php
    [$icono, $etiqueta, $colores] = match ($estado) {
        'completado' => ['check-circle', 'Completado', 'bg-success-soft text-success-ink'],
        'en_curso' => ['arrow-path', 'En curso', 'bg-warning-soft text-warning-ink'],
        'error' => ['exclamation-circle', 'Requiere corrección', 'bg-error-soft text-error-ink'],
        'no_disponible' => ['clock', 'No disponible aún', 'bg-hairline-soft text-muted'],
        'no_aplica' => ['no-symbol', 'No aplica para este nivel', 'bg-hairline-soft text-muted'],
        default => ['minus-circle', 'Pendiente', 'bg-badge-blue text-info-ink'],
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex shrink-0 items-center gap-xxs rounded-pill px-sm py-xxs text-body-sm font-semibold $colores"]) }}>
    <x-ui.icon :nombre="$icono" class="h-4 w-4" />
    {{ $texto ?? $etiqueta }}
</span>
