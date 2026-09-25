{{-- Identidad del trámite + secciones. En móvil las secciones van en un <details>; en escritorio, fijas. --}}
@php($avance = $resumen->avance())

<aside aria-label="Tu trámite" {{ $attributes }}>
    <a href="{{ route('tramite.index') }}" class="inline-flex min-h-11 items-center gap-xxs text-body-sm font-semibold text-muted hover:text-ink">
        <x-ui.icon nombre="arrow-left" class="h-4 w-4" />
        Mis trámites
    </a>

    <div class="mt-xs rounded-lg bg-surface-dark p-lg text-on-dark">
        <p class="font-mono text-caption text-on-dark/80">Trámite Nº {{ $resumen->numero() }}</p>
        <p @class(['mt-xxs break-words font-semibold', 'italic' => $resumen->nombre === null])>{{ $resumen->nombre ?? 'Sin nombre propuesto' }}</p>
        <p class="mt-xxs text-caption text-on-dark/80">Iniciado el {{ $resumen->iniciadoEl->format('d/m/Y') }}</p>
        <p class="mt-md flex items-center justify-between text-caption text-on-dark/80">
            <span>Avance</span>
            <span class="tabular-nums">{{ $avance['hechas'] }} de {{ $avance['total'] }}</span>
        </p>
        <div class="mt-xxs h-1.5 rounded-pill bg-on-dark/15" aria-hidden="true">
            <div class="h-full rounded-pill bg-brand-accent" style="width: {{ $avance['porcentaje'] }}%"></div>
        </div>
    </div>

    {{-- Dos copias de la lista: una siempre tiene display:none, así que solo una llega a lectores de pantalla. --}}
    <details class="mt-md rounded-md border border-hairline lg:hidden">
        <summary class="min-h-11 cursor-pointer px-md py-sm text-body-sm font-semibold text-ink">Secciones del trámite</summary>
        <x-tramite.recorrido-pasos :resumen="$resumen" :seccion-actual="$seccionActual" :escuela-nivel-id="$escuelaNivelId" class="border-t border-hairline p-md" />
    </details>
    <x-tramite.recorrido-pasos :resumen="$resumen" :seccion-actual="$seccionActual" :escuela-nivel-id="$escuelaNivelId" class="mt-lg hidden lg:block" />
</aside>
