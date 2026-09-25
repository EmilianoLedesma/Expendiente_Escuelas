@props(['tipo' => 'info', 'titulo' => null])

@php
    [$icono, $colores] = match ($tipo) {
        'success' => ['check-circle', 'border-success-ink bg-success-soft text-success-ink'],
        'warning' => ['exclamation-triangle', 'border-warning-ink bg-warning-soft text-warning-ink'],
        'error' => ['exclamation-circle', 'border-error-ink bg-error-soft text-error-ink'],
        default => ['information-circle', 'border-info-ink bg-badge-blue text-info-ink'],
    };
@endphp

<div {{ $attributes->merge(['role' => $tipo === 'error' ? 'alert' : 'status', 'class' => "flex gap-sm rounded-sm border-l-4 p-md $colores"]) }}>
    <x-ui.icon :nombre="$icono" class="h-6 w-6" />
    <div class="min-w-0 text-body-md">
        @if ($titulo)
            <p class="font-semibold">{{ $titulo }}</p>
        @endif
        <div @class(['mt-xxs' => $titulo])>{{ $slot }}</div>
    </div>
</div>
