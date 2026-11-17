{{-- Revisión del proyecto educativo: 20 secciones a la izquierda, la abierta a la derecha. El supervisor solo lee. --}}
@php
    $tag = ['aceptado' => ['completado', 'Aceptada'], 'rechazado' => ['error', 'Rechazada'], 'pendiente' => ['pendiente', 'Pendiente']];
@endphp
<div>
    <x-ui.page-header :title="$revision['escuela']" :eyebrow="'Proyecto educativo · '.$revision['nivel']" :back-href="$volver" back-label="Volver" />

    <div class="grid grid-cols-1 gap-lg lg:grid-cols-3">
        <nav aria-label="Secciones del proyecto educativo" class="lg:col-span-1">
            <p class="mb-sm text-body-sm font-semibold tabular-nums text-muted">Avance: {{ $revision['revisadas'] }} de {{ $revision['total'] }}</p>
            <ol class="space-y-xs">
                @foreach ($revision['secciones'] as $s)
                    @php($abierta = $s['slug'] === $seccionAbierta)
                    <li wire:key="seccion-{{ $s['clave'] }}">
                        <button type="button" wire:click="abrir('{{ $s['slug'] }}')" aria-current="{{ $abierta ? 'step' : 'false' }}"
                                @class(['flex w-full min-h-11 cursor-pointer items-center justify-between gap-sm rounded-lg bg-canvas p-sm text-left transition-colors duration-150 hover:bg-surface-soft motion-reduce:transition-none',
                                    'border-2 border-primary' => $abierta, 'border border-hairline' => ! $abierta])>
                            <span class="font-semibold text-ink"><span class="tabular-nums">{{ $s['numero'] }}.</span> {{ $s['titulo'] }}</span>
                            <x-ui.status-tag :estado="$tag[$s['estado']][0]" :texto="$tag[$s['estado']][1]" />
                        </button>
                    </li>
                @endforeach
            </ol>
        </nav>

        <div class="lg:col-span-2">
            @unless ($seccion['completa'])
                <x-ui.alert tipo="warning" class="mb-md">El solicitante todavía no completa esta sección.</x-ui.alert>
            @endunless

            <x-ui.section :title="$seccion['numero'].'. '.$contenido['titulo']" class="!mt-0">
                @if ($contenido['filas'] !== [])
                    <x-ui.summary-list :filas="collect($contenido['filas'])->pluck(1, 0)->all()" />
                @endif

                @foreach ($contenido['listas'] as $lista)
                    <div class="space-y-sm">
                        <h3 class="text-body-md font-semibold text-ink">{{ $lista['titulo'] }}</h3>
                        @forelse ($lista['filas'] as $fila)
                            <div class="rounded-md border border-hairline p-sm">
                                <x-ui.summary-list :filas="collect($fila)->pluck(1, 0)->all()" />
                            </div>
                        @empty
                            <p class="text-body-md text-muted">Sin captura.</p>
                        @endforelse
                    </div>
                @endforeach

                @if ($contenido['descargas'] !== [])
                    <div class="space-y-xs">
                        <h3 class="text-body-md font-semibold text-ink">Archivos PDF</h3>
                        <ul class="space-y-xs">
                            @foreach ($contenido['descargas'] as $d)
                                <li>
                                    <a href="{{ route('sedeq.proyecto-archivo', ['escuelaNivel' => $escuelaNivelId, 'clave' => $d['clave']]) }}" target="_blank" rel="noopener" class="font-semibold text-primary underline">{{ $d['etiqueta'] }} (PDF)</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-ui.section>

            @if ($seccion['estado'] !== 'pendiente')
                <p class="mt-md text-body-md text-muted">
                    Decisión vigente: {{ $seccion['estado'] === 'aceptado' ? 'aceptada' : 'rechazada' }} por {{ $seccion['por'] }} el {{ $seccion['cuando'] }}.
                    @if (filled($seccion['motivo']))
                        Motivo: {{ $seccion['motivo'] }}
                    @endif
                </p>
            @endif

            @if ($revision['editable'])
                <x-ui.section title="Decisión sobre esta sección">
                    <x-ui.field id="motivo" label="Motivo del rechazo" hint="Obligatorio solo para rechazar.">
                        <x-ui.textarea id="motivo" wire:model="motivo" />
                    </x-ui.field>
                    <div class="flex flex-wrap gap-sm">
                        <x-ui.button-primary type="button" wire:click="aceptar" wire:loading.attr="disabled">Aceptar sección</x-ui.button-primary>
                        <x-ui.button-secondary wire:click="rechazar" wire:loading.attr="disabled">Rechazar sección</x-ui.button-secondary>
                    </div>
                </x-ui.section>
            @elseif ($revision['estado'] === 'con_observaciones')
                <p class="mt-md text-body-md text-muted">Este trámite está con el solicitante para corregir; podrás decidir cuando lo reenvíe.</p>
            @endif
        </div>
    </div>
</div>
