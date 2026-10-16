{{-- Cola del revisor (opción A): lista priorizada + panel del trámite elegido. Solo lectura en este proyecto. --}}
@php
    $prioridades = ['alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];
    $espera = fn (int $d) => $d.' '.($d === 1 ? 'día' : 'días');
    $asignados = count($filas);
    $llena = $asignados >= $limite;
@endphp
<div>
    <x-ui.page-header title="Mis Revisiones" eyebrow="Revisión SEDEQ">
        <x-slot:intro>Tus trámites asignados, primero los de mayor prioridad y después los que llevan más tiempo esperando.</x-slot:intro>
    </x-ui.page-header>

    <div class="mb-lg">
        <p @class(['text-body-sm tabular-nums', 'font-semibold text-error-ink' => $llena, 'text-muted' => ! $llena])>{{ $asignados }} de {{ $limite }} asignados</p>
        <div class="mt-xxs h-2 rounded-pill bg-surface-card" aria-hidden="true">
            <div @class(['h-full rounded-pill', 'bg-error-ink' => $llena, 'bg-primary' => ! $llena]) style="width: {{ $limite > 0 ? min(100, intdiv($asignados * 100, $limite)) : 100 }}%"></div>
        </div>
    </div>

    @if ($filas === [])
        <x-ui.alert>No tienes trámites asignados. Cuando el supervisor te asigne uno, aparecerá aquí.</x-ui.alert>
    @else
        <div class="grid grid-cols-1 gap-lg lg:grid-cols-2">
            <nav aria-label="Trámites de mi cola">
                <ol class="space-y-xs">
                    @foreach ($filas as $i => $fila)
                        @php($elegido = $actual?->escuelaNivelId === $fila->escuelaNivelId)
                        <li wire:key="cola-{{ $fila->escuelaNivelId }}">
                            <button type="button" wire:click="seleccionar({{ $fila->escuelaNivelId }})" aria-pressed="{{ $elegido ? 'true' : 'false' }}"
                                    @class(['block w-full min-h-11 cursor-pointer rounded-lg bg-canvas p-sm text-left transition-colors duration-150 hover:bg-surface-soft motion-reduce:transition-none',
                                        'border-2 border-primary' => $elegido, 'border border-hairline' => ! $elegido])>
                                <span class="block font-semibold text-ink"><span class="tabular-nums">{{ $i + 1 }} · N.º {{ $fila->escuelaNivelId }}</span> {{ $fila->plantel }}</span>
                                <span class="block text-body-sm text-muted">{{ $fila->nivel }} · Prioridad {{ $prioridades[$fila->prioridad] ?? $fila->prioridad }} · espera {{ $espera($fila->diasEspera) }}</span>
                            </button>
                        </li>
                    @endforeach
                </ol>
            </nav>

            @if ($actual)
                <x-ui.section :title="'N.º '.$actual->escuelaNivelId.' — '.$actual->plantel" class="!mt-0">
                    <x-ui.summary-list :filas="[
                        'Nivel' => $actual->nivel,
                        'Prioridad' => $prioridades[$actual->prioridad] ?? $actual->prioridad,
                        'En revisión desde hace' => $espera($actual->diasEspera),
                        'Secciones revisadas' => $actual->revisadas.' de '.$actual->total,
                    ]" />
                    <x-ui.button-secondary :href="route('sedeq.tramite', ['escuela' => $actual->escuelaId])">Ver trámite (solo lectura)</x-ui.button-secondary>

                    <section aria-labelledby="hallazgos-titulo" class="rounded-md border border-dashed border-hairline p-md">
                        <h3 id="hallazgos-titulo" class="text-body-md font-semibold text-ink">Hallazgos del motor</h3>
                        <p class="mt-xxs text-body-sm text-muted">Aquí aparecerán los hallazgos de la validación automática y las decisiones de revisión. Disponible en la siguiente etapa.</p>
                    </section>
                </x-ui.section>
            @endif
        </div>
    @endif
</div>
