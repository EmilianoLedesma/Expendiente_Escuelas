{{-- Revisión de un nivel (opción A): pasos a la izquierda, paso abierto a la derecha. Sin acciones de edición del trámite. --}}
@php
    $tag = ['aceptado' => ['completado', 'Aceptado'], 'rechazado' => ['error', 'Rechazado'], 'pendiente' => ['pendiente', 'Pendiente']];
    // Estado del motor => [tag, etiqueta]. Mismos tags que la validación final; etiquetas sin «bloquea el envío» (aquí son solo pistas).
    $etiquetasPista = ['cumple' => ['completado', 'Cumple'], 'advertencia' => ['en_curso', 'Advertencia'], 'no_cumple' => ['error', 'No cumple'], 'no_evaluable' => ['pendiente', 'No evaluable'], 'no_verificable' => ['no_disponible', 'No verificable por el sistema']];
@endphp
<div>
    <x-ui.page-header :title="$revision['escuela'] ?? 'Plantel sin nombre'" :eyebrow="'Revisión · '.$revision['nivel']" :back-href="$volver" back-label="Volver">
        <x-slot:intro>
            <a href="{{ route('sedeq.tramite', ['escuela' => $revision['escuelaId']]) }}" class="font-semibold text-primary hover:underline">Ver trámite completo (solo lectura)</a>
        </x-slot:intro>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-lg lg:grid-cols-3">
        <nav aria-label="Pasos de la revisión" class="lg:col-span-1">
            <p class="mb-sm text-body-sm font-semibold tabular-nums text-muted">Avance: {{ $revision['revisadas'] }} de {{ $revision['total'] }}</p>
            <ol class="space-y-xs">
                @foreach ($revision['pasos'] as $p)
                    @php($abierto = $p['clave'] === $pasoAbierto)
                    <li wire:key="paso-{{ $p['clave'] }}">
                        <button type="button" wire:click="abrir('{{ $p['clave'] }}')" aria-current="{{ $abierto ? 'step' : 'false' }}"
                                @class(['flex w-full min-h-11 cursor-pointer items-center justify-between gap-sm rounded-lg bg-canvas p-sm text-left transition-colors duration-150 hover:bg-surface-soft motion-reduce:transition-none',
                                    'border-2 border-primary' => $abierto, 'border border-hairline' => ! $abierto])>
                            <span class="font-semibold text-ink">{{ $p['titulo'] }}</span>
                            <x-ui.status-tag :estado="$tag[$p['estado']][0]" :texto="$tag[$p['estado']][1]" />
                        </button>
                    </li>
                @endforeach
            </ol>
        </nav>

        <div class="lg:col-span-2">
            <x-ui.section :title="$contenido['titulo']" class="!mt-0">
                @forelse ($contenido['bloques'] as $bloque)
                    <div class="space-y-sm">
                        @if ($bloque['titulo'] !== $contenido['titulo'])
                            <h3 class="text-body-md font-semibold text-ink">{{ $bloque['titulo'] }}</h3>
                        @endif
                        @if ($bloque['filas'] !== [])
                            <x-ui.summary-list :filas="collect($bloque['filas'])->pluck(1, 0)->all()" />
                        @endif
                        @foreach ($bloque['documentos'] as $doc)
                            <x-ui.pdf-preview :url="$doc['href']" :title="$doc['nombre']" class="flex min-h-11 items-center justify-between gap-md border-b border-hairline py-xs text-body-md text-primary hover:underline">
                                <span>{{ $doc['nombre'] }}</span>
                                <span class="text-caption text-muted">{{ $doc['archivo'] }}</span>
                            </x-ui.pdf-preview>
                        @endforeach
                        @if ($bloque['filas'] === [] && $bloque['documentos'] === [])
                            <p class="text-body-md text-muted">Sin documentos en este paso.</p>
                        @endif
                    </div>
                @empty
                    <p class="text-body-md text-muted">Sin datos en este paso.</p>
                @endforelse
            </x-ui.section>

            @if ($pistas !== [])
                <x-ui.section title="Pistas del sistema">
                    <p class="text-body-sm text-muted">Informativas: no sustituyen tu revisión ni bloquean la decisión.</p>
                    <ul class="divide-y divide-hairline">
                        @foreach ($pistas as $pista)
                            <li class="space-y-xxs py-sm" data-pista="{{ $pista->clave }}" data-estado="{{ $pista->estado }}">
                                <div class="flex flex-wrap items-center justify-between gap-sm">
                                    <span class="text-body-md font-semibold text-ink">{{ $pista->titulo }}</span>
                                    <x-ui.status-tag :estado="$etiquetasPista[$pista->estado][0] ?? 'pendiente'" :texto="$etiquetasPista[$pista->estado][1] ?? $pista->estado" />
                                </div>
                                <p class="text-body-sm text-muted">{{ $pista->mensaje }}</p>
                                @if ($pista->lineas !== [])
                                    <ul class="list-disc space-y-xxs pl-lg text-body-sm text-muted">
                                        @foreach ($pista->lineas as $linea)
                                            <li>{{ $linea }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-ui.section>
            @endif

            @if ($paso['otrosNiveles'] !== [])
                <x-ui.section title="Mismo paso en otros niveles">
                    <ul class="space-y-xxs text-body-sm text-muted">
                        @foreach ($paso['otrosNiveles'] as $o)
                            <li>{{ $o['nivel'] }}: {{ $o['estado'] === 'aceptado' ? 'aceptado' : 'rechazado' }} por {{ $o['por'] }} el {{ $o['cuando'] }}</li>
                        @endforeach
                    </ul>
                </x-ui.section>
            @endif

            @if ($paso['historial'] !== [])
                <x-ui.section title="Historial del paso">
                    <ul class="space-y-xs text-body-sm">
                        @foreach ($paso['historial'] as $h)
                            <li><span class="font-semibold text-ink">{{ $h['estado'] === 'aceptado' ? 'Aceptado' : 'Rechazado' }}</span> por {{ $h['por'] }} el {{ $h['cuando'] }}@if (filled($h['motivo'])): <span class="text-muted">{{ $h['motivo'] }}</span>@endif</li>
                        @endforeach
                    </ul>
                </x-ui.section>
            @endif

            @if ($revision['editable'])
                <x-ui.section title="Decisión sobre este paso">
                    @if ($paso['estado'] !== 'pendiente')
                        <p class="text-body-md text-muted">Decisión vigente: {{ $paso['estado'] === 'aceptado' ? 'aceptado' : 'rechazado' }} por {{ $paso['por'] }} el {{ $paso['cuando'] }}.</p>
                    @endif
                    <x-ui.field id="motivo" label="Motivo del rechazo" hint="Obligatorio solo para rechazar.">
                        <x-ui.textarea id="motivo" wire:model="motivo" />
                    </x-ui.field>
                    <div class="flex flex-wrap gap-sm">
                        <x-ui.button-primary type="button" wire:click="aceptar" wire:loading.attr="disabled">Aceptar paso</x-ui.button-primary>
                        <x-ui.button-secondary wire:click="rechazar" wire:loading.attr="disabled">Rechazar paso</x-ui.button-secondary>
                    </div>
                </x-ui.section>
            @endif
        </div>
    </div>
</div>
