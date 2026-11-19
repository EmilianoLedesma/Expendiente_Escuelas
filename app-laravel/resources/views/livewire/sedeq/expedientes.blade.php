{{-- Expediente del supervisor (fase 0): niveles con la revisión documental aprobada. Otros estados llegan con las fases siguientes. --}}
<div>
    <x-ui.page-header title="Expedientes" eyebrow="Revisión SEDEQ">
        <x-slot:intro>Escuelas por estado del trámite (se abre en las aprobadas). Abre un expediente para ver sus datos, documentos y su historial.</x-slot:intro>
    </x-ui.page-header>

    {{-- Barra de filtros: buscar y estado en una sola franja. --}}
    <div class="mb-lg flex flex-wrap items-end gap-md rounded-lg bg-surface-card p-md">
        <x-ui.field id="buscar" label="Buscar escuela o nivel" class="min-w-56 flex-1">
            <x-ui.input type="search" wire:model.live.debounce.300ms="buscar" autocomplete="off" />
        </x-ui.field>
        <x-ui.field id="estado" label="Estado" class="w-full sm:w-80">
            <x-ui.select wire:model.live="estado">
                @foreach ($estados as $clave => $etiqueta)
                    <option value="{{ $clave }}">{{ $etiqueta }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
    </div>

    @if ($filas === [])
        <p class="text-body-md text-muted">
            @if ($buscar !== '')
                Ningún expediente coincide con la búsqueda.
            @elseif ($estado === 'aprobado')
                Todavía no hay expedientes aprobados.
            @else
                No hay expedientes en «{{ $estados[$estado] }}».
            @endif
        </p>
    @else
        @php($escuelas = collect($filas)->groupBy('escuelaId'))
        <p class="mb-sm text-body-sm text-muted">
            {{ $escuelas->count() }} {{ $escuelas->count() === 1 ? 'escuela' : 'escuelas' }} · {{ count($filas) }} {{ count($filas) === 1 ? 'nivel' : 'niveles' }}. Las más recientes primero.
        </p>

        {{-- Una tarjeta por escuela con sus niveles dentro: se ve de un vistazo, sin recorrer una tabla larga. --}}
        <ul class="grid gap-md sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($escuelas as $escuelaId => $niveles)
                <li wire:key="expediente-{{ $escuelaId }}" class="flex flex-col rounded-lg border border-hairline bg-canvas p-md">
                    <h2 class="break-words text-title-sm font-semibold text-ink">{{ $niveles->first()->escuela }}</h2>

                    <ul class="mt-sm flex-1 divide-y divide-hairline text-body-sm">
                        @foreach ($niveles as $fila)
                            <li class="flex flex-wrap items-baseline justify-between gap-x-md gap-y-xxs py-xs">
                                <span class="font-semibold text-ink">{{ $fila->nivel }}</span>
                                <span class="text-muted">
                                    <span class="tabular-nums">{{ $fila->desde?->format('d/m/Y') ?? 'Sin fecha' }}</span>
                                    · {{ $fila->revisorNombre ?? 'Sin asignar' }}
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    <x-ui.button-secondary :href="route('sedeq.tramite', ['escuela' => $escuelaId])" class="mt-md w-full justify-center">
                        Ver expediente<span class="sr-only">: {{ $niveles->first()->escuela }}</span>
                    </x-ui.button-secondary>
                </li>
            @endforeach
        </ul>
    @endif
</div>
