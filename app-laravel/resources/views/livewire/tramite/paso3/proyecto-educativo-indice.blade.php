<div class="max-w-3xl">
    <x-ui.page-header :eyebrow="$encabezado" title="Presentación del Proyecto Educativo" :back-href="route('tramite.resumen', ['escuela' => $escuelaNivel->escuela_id])">
        <x-slot:intro>
            Estas son las secciones que integran el proyecto educativo del plantel. El contenido de estas secciones aún no está disponible. Tu trámite podrá enviarse cuando puedas completarlas.
        </x-slot:intro>
    </x-ui.page-header>

    <ol class="divide-y divide-hairline overflow-hidden rounded-lg border border-hairline">
        @foreach ($secciones as $slug => $nombre)
            <li wire:key="seccion-{{ $slug }}">
                <a href="{{ route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $escuelaNivel->id, 'seccion' => $slug]) }}" class="flex min-h-11 items-center gap-md px-lg py-xs text-body-md text-body hover:bg-surface-soft">
                    <span class="w-6 text-right tabular-nums text-muted">{{ $loop->iteration }}.</span>
                    <span class="min-w-0 flex-1 font-semibold text-primary">{{ $nombre }}</span>
                    @if ($completas[$slug] ?? false)
                        <x-ui.status-tag estado="completado" texto="Completa" />
                    @else
                        <x-ui.status-tag estado="pendiente" />
                    @endif
                </a>
            </li>
        @endforeach
    </ol>
</div>
