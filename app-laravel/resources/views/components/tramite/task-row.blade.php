{{-- Una fila del hub a partir de App\Application\Tramite\DTO\SeccionTramite. --}}
@props(['seccion', 'numerado' => false])

<li class="grid gap-xs py-md sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:gap-md" data-clave="{{ $seccion->clave }}" data-estado="{{ $seccion->estado }}">
    <div class="min-w-0">
        <h3 class="text-body-md font-semibold text-ink">@if ($numerado && $seccion->paso){{ $seccion->paso }}. @endif{{ $seccion->nombre }}</h3>
        <p class="text-body-sm text-muted">{{ $seccion->descripcion }}</p>
        @if ($seccion->motivoBloqueo)
            <p class="mt-xxs flex items-center gap-xxs text-body-sm text-body">
                <x-ui.icon nombre="lock-closed" class="h-4 w-4" />
                {{ $seccion->motivoBloqueo }}
            </p>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-md sm:justify-end">
        <x-ui.status-tag :estado="$seccion->estado" />
        @if ($seccion->accion !== null && $seccion->href !== null)
            <a href="{{ $seccion->href }}" class="inline-flex min-h-11 items-center font-semibold text-primary underline underline-offset-4 hover:no-underline">
                {{ ['comenzar' => 'Comenzar', 'continuar' => 'Continuar', 'revisar' => 'Revisar'][$seccion->accion] }}<span class="sr-only">: {{ $seccion->nombre }}</span>
            </a>
        @endif
    </div>
</li>
