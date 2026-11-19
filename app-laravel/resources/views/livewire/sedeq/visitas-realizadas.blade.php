{{-- Registro de visitas realizadas (supervisor, solo lectura). Los datos vienen de ListarVisitasRealizadas. --}}
<div>
    <x-ui.page-header title="Visitas realizadas" eyebrow="Revisión SEDEQ">
        <x-slot:intro>Todas las visitas de inspección ya registradas, de la más reciente a la más antigua. Abre una visita para ver su detalle o descarga su acta.</x-slot:intro>
    </x-ui.page-header>

    {{-- Barra de filtros: búsqueda, resultado y rango de fechas de la visita. --}}
    <div class="mb-lg flex flex-wrap items-end gap-md rounded-lg bg-surface-card p-md">
        <x-ui.field id="buscar" label="Buscar escuela o nivel" class="min-w-56 flex-1">
            <x-ui.input type="search" wire:model.live.debounce.300ms="buscar" autocomplete="off" />
        </x-ui.field>
        <x-ui.field id="resultado" label="Resultado" class="w-full sm:w-52">
            <x-ui.select wire:model.live="resultado">
                <option value="">Todos</option>
                <option value="cumple">Cumple</option>
                <option value="con_observaciones">Con observaciones</option>
            </x-ui.select>
        </x-ui.field>
        <x-ui.field id="desde" label="Realizada desde" class="w-full sm:w-44">
            <x-ui.input type="date" wire:model.live="desde" />
        </x-ui.field>
        <x-ui.field id="hasta" label="Realizada hasta" class="w-full sm:w-44">
            <x-ui.input type="date" wire:model.live="hasta" />
        </x-ui.field>
    </div>

    @if ($filas === [])
        <p class="text-body-md text-muted">
            {{ $hayFiltros ? 'Ninguna visita coincide con los filtros.' : 'Todavía no hay visitas realizadas.' }}
        </p>
    @else
        <p class="mb-sm text-body-sm text-muted">
            {{ $total }} {{ $total === 1 ? 'visita' : 'visitas' }} · Mostrando {{ count($filas) }}.
        </p>

        <ul class="grid gap-md sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($filas as $fila)
                <li wire:key="visita-{{ $fila->visitaId }}" class="flex flex-col rounded-lg border border-hairline bg-canvas p-md">
                    <h2 class="break-words text-title-sm font-semibold text-ink">{{ $fila->escuela }}</h2>
                    <p class="text-body-sm font-semibold text-ink">{{ $fila->nivel }}</p>

                    <div class="mt-sm flex-1 space-y-xxs text-body-sm text-muted">
                        <p>
                            <span class="tabular-nums">Visita {{ $fila->numero }} · {{ $fila->realizadaEl->format('d/m/Y') }}</span>
                            · {{ $fila->inspector }}
                        </p>
                        <p>
                            @if ($fila->resultado === 'cumple')
                                <x-ui.status-tag estado="completado" texto="Cumple" />
                            @else
                                <x-ui.status-tag estado="con_observaciones" />
                            @endif
                        </p>
                        @if ($fila->resultado === 'con_observaciones' && $fila->subsanarHasta)
                            <p>Plazo aprox. de corrección: {{ $fila->subsanarHasta->format('d/m/Y') }} (aprox.)</p>
                        @endif
                        <p>{{ $fila->observacionesCount }} {{ $fila->observacionesCount === 1 ? 'observación' : 'observaciones' }} · {{ $fila->respuestasCount }} {{ $fila->respuestasCount === 1 ? 'respuesta' : 'respuestas' }}</p>
                        <p>Estado actual: <span class="font-semibold text-ink">{{ $fila->nivelEstadoEtiqueta }}</span></p>
                    </div>

                    <div class="mt-md flex flex-wrap gap-sm">
                        <x-ui.button-secondary :href="route('sedeq.visita', ['escuelaNivel' => $fila->escuelaNivelId])" class="justify-center">
                            Ver visita<span class="sr-only">: {{ $fila->escuela }}, {{ $fila->nivel }}</span>
                        </x-ui.button-secondary>
                        @if ($fila->tieneActa)
                            <x-ui.button-secondary :href="route('visita.acta', ['escuelaNivel' => $fila->escuelaNivelId, 'visita' => $fila->visitaId])" class="justify-center">
                                Descargar acta<span class="sr-only">: {{ $fila->escuela }}, {{ $fila->nivel }}</span>
                            </x-ui.button-secondary>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
