<div class="max-w-3xl">
    <x-ui.page-header :eyebrow="$encabezado" :title="$numero . '. ' . $nombre" :back-href="route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $escuelaNivel->id])" back-label="Presentación del Proyecto Educativo" />

    <p class="rounded-lg border border-dashed border-hairline p-lg text-body-md text-muted">Contenido por definir</p>

    <nav aria-label="Secciones del proyecto educativo" class="mt-lg flex flex-wrap items-center justify-between gap-md">
        @if ($anterior)
            <a href="{{ route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $escuelaNivel->id, 'seccion' => $anterior['slug']]) }}" class="inline-flex min-h-11 items-center font-semibold text-primary">
                Anterior<span class="sr-only">: {{ $anterior['nombre'] }}</span>
            </a>
        @endif
        <a href="{{ route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $escuelaNivel->id]) }}" class="inline-flex min-h-11 items-center font-semibold text-primary">Índice de secciones</a>
        @if ($siguiente)
            <a href="{{ route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $escuelaNivel->id, 'seccion' => $siguiente['slug']]) }}" class="inline-flex min-h-11 items-center font-semibold text-primary">
                Siguiente<span class="sr-only">: {{ $siguiente['nombre'] }}</span>
            </a>
        @endif
    </nav>
</div>
