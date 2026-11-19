{{-- Solo lectura (HUD SEDEQ): sin formularios ni enlaces de edición; los únicos enlaces son descargas protegidas por `view` (o `verReporteEnviado`), con vista previa (x-ui.pdf-preview). Los datos llegan ya decididos de ConsultarTramiteSedeq. --}}
@php
    $estadosVigencia = ['vigente' => 'completado', 'vencido' => 'error', 'sin_fecha_expresa' => 'pendiente', 'no_determinada' => 'pendiente', 'no_capturado' => 'pendiente'];
    $textosVigencia = ['vigente' => 'Vigente', 'vencido' => 'Vencido', 'sin_fecha_expresa' => 'Sin fecha expresa', 'no_determinada' => 'No determinada', 'no_capturado' => 'No capturado'];
    $etiquetas = ['cumple' => 'Correcto', 'advertencia' => 'Alerta', 'no_cumple' => 'No cumple', 'no_evaluable' => 'No verificado', 'no_verificable' => 'No verificable por el sistema'];
    $tags = ['cumple' => 'completado', 'advertencia' => 'en_curso', 'no_cumple' => 'error', 'no_evaluable' => 'pendiente', 'no_verificable' => 'no_disponible'];
    $estadosPaso = ['completado' => 'completado', 'en_progreso' => 'en_curso'];
    // Una pestaña por bloque del expediente: se ve uno a la vez en vez de una sola página larga (todos quedan en el DOM).
    $pestanas = [
        ['id' => 'plantel', 'texto' => 'Plantel y responsable'],
        ['id' => 'documentos', 'texto' => 'Documentos ('.count($tramite['documentos']).')'],
        ...array_map(fn ($n) => ['id' => 'nivel-'.$n['escuelaNivelId'], 'texto' => $n['nombre']], $tramite['niveles']),
        ['id' => 'validacion', 'texto' => 'Validación'],
        ...($historial !== null ? [['id' => 'historial', 'texto' => 'Historial']] : []),
    ];
@endphp
<div x-data="{ tab: 'plantel', ids: @js(array_column($pestanas, 'id')), mover(d) { const i = (this.ids.indexOf(this.tab) + d + this.ids.length) % this.ids.length; this.tab = this.ids[i]; this.$nextTick(() => document.getElementById('pestana-' + this.tab)?.focus()); } }">
    <x-ui.page-header :title="$tramite['nombre'] ?? 'Plantel sin nombre'" eyebrow="Trámite · solo lectura" :back-href="$volver" back-label="Volver" />

    <ul aria-label="Estado de cada nivel" class="mb-md flex flex-wrap gap-xs">
        @foreach ($tramite['niveles'] as $nivel)
            <li class="rounded-pill bg-surface-card px-md py-xxs text-body-sm font-semibold text-ink">{{ $nivel['nombre'] }}: {{ $nivel['estado'] }}</li>
        @endforeach
    </ul>

    <div role="tablist" aria-label="Secciones del expediente" class="mb-lg flex flex-wrap gap-xs border-b border-hairline pb-sm"
         x-on:keydown.right.prevent="mover(1)" x-on:keydown.left.prevent="mover(-1)">
        @foreach ($pestanas as $p)
            <button type="button" role="tab" id="pestana-{{ $p['id'] }}" aria-controls="panel-{{ $p['id'] }}"
                    x-on:click="tab = '{{ $p['id'] }}'" x-bind:aria-selected="tab === '{{ $p['id'] }}'" x-bind:tabindex="tab === '{{ $p['id'] }}' ? 0 : -1"
                    x-bind:class="tab === '{{ $p['id'] }}' ? 'border-primary bg-primary text-on-primary' : 'border-hairline bg-canvas text-primary hover:bg-surface-soft'"
                    class="inline-flex min-h-11 items-center rounded-md border px-md text-body-sm font-semibold transition-colors duration-150">
                {{ $p['texto'] }}
            </button>
        @endforeach
    </div>

    <div role="tabpanel" id="panel-plantel" aria-labelledby="pestana-plantel" x-show="tab === 'plantel'" x-cloak>
    <x-ui.section title="Datos del plantel">
        <x-ui.summary-list :filas="$tramite['plantel']" />
    </x-ui.section>

    <x-ui.section title="Responsable legal">
        @if ($tramite['responsable'] === null)
            <p class="text-body-md text-muted">Sin datos del responsable legal.</p>
        @else
            <x-ui.summary-list :filas="$tramite['responsable']" />
            @if ($tramite['terna'] !== [])
                <x-ui.summary-list class="mt-sm" :filas="['Terna de nombres' => implode(' · ', $tramite['terna'])]" />
            @endif
        @endif
    </x-ui.section>

    </div>

    <div role="tabpanel" id="panel-documentos" aria-labelledby="pestana-documentos" x-show="tab === 'documentos'" x-cloak>
    <x-ui.section title="Documentos">
        @forelse ($tramite['documentos'] as $doc)
            <div class="border-b border-hairline py-xs">
                <x-ui.pdf-preview :url="$doc['href']" :title="$doc['nombre']" class="flex min-h-11 items-center justify-between gap-md text-body-md text-primary hover:underline">
                    <span>{{ $doc['nombre'] }}</span>
                    <span class="text-caption text-muted">{{ $doc['archivo'] }}</span>
                </x-ui.pdf-preview>
                @include('livewire.sedeq.partials.metadatos', ['metadatos' => $doc['metadatos'], 'estadosVigencia' => $estadosVigencia, 'textosVigencia' => $textosVigencia])
            </div>
        @empty
            <p class="text-body-md text-muted">Sin documentos del plantel.</p>
        @endforelse
    </x-ui.section>

    </div>

    @foreach ($tramite['niveles'] as $nivel)
        <div role="tabpanel" id="panel-nivel-{{ $nivel['escuelaNivelId'] }}" aria-labelledby="pestana-nivel-{{ $nivel['escuelaNivelId'] }}" x-show="tab === 'nivel-{{ $nivel['escuelaNivelId'] }}'" x-cloak>
        <x-ui.section :title="$nivel['nombre']">
            <x-ui.summary-list :filas="['Estado' => $nivel['estado'], 'Folio de expediente' => $nivel['folio'], ...$nivel['datos']]" />

            @if (isset($cambiados[$nivel['escuelaNivelId']]))
                <div class="mt-md">
                    @include('livewire.sedeq.partials.resultado-cambiado', ['descripciones' => $cambiados[$nivel['escuelaNivelId']]])
                </div>
            @endif

            <h3 class="mt-md text-body-md font-semibold text-ink">Avance de la captura académica e infraestructura</h3>
            <ul class="mt-xs divide-y divide-hairline border-y border-hairline">
                @foreach ($nivel['pasos'] as $paso)
                    <li class="flex min-h-11 items-center justify-between gap-md py-xs text-body-md">
                        <span>{{ $paso['nombre'] }}</span>
                        <x-ui.status-tag :estado="$estadosPaso[$paso['estado']] ?? 'pendiente'" />
                    </li>
                @endforeach
            </ul>

            <h3 class="mt-md text-body-md font-semibold text-ink">Documentos del nivel</h3>
            @forelse ($nivel['documentos'] as $doc)
                <div class="border-b border-hairline py-xs">
                    <x-ui.pdf-preview :url="$doc['href']" :title="$doc['nombre']" class="flex min-h-11 items-center justify-between gap-md text-body-md text-primary hover:underline">
                        <span>{{ $doc['nombre'] }}</span>
                        <span class="text-caption text-muted">{{ $doc['archivo'] }}</span>
                    </x-ui.pdf-preview>
                    @include('livewire.sedeq.partials.metadatos', ['metadatos' => $doc['metadatos'], 'estadosVigencia' => $estadosVigencia, 'textosVigencia' => $textosVigencia])
                </div>
            @empty
                <p class="text-body-md text-muted">Sin documentos del nivel.</p>
            @endforelse
        </x-ui.section>
        </div>
    @endforeach

    <div role="tabpanel" id="panel-validacion" aria-labelledby="pestana-validacion" x-show="tab === 'validacion'" x-cloak>
    <x-ui.section title="Resultado de validación almacenado al envío">
        @if ($tramite['validacion'] === null)
            <p class="text-body-md text-muted">No hay un resultado de validación guardado con el envío.</p>
        @else
            @php($v = $tramite['validacion'])
            <p class="text-body-sm text-muted">Generado el {{ $v['generadaEn']->format('d/m/Y H:i') }}, al enviar el trámite. Es el resultado que se envió, no una evaluación nueva: los datos no se vuelven a evaluar al consultarlos.</p>
            <x-ui.summary-list class="mt-sm" :filas="['Listo para envío' => $v['listaParaEnvio'] ? 'Sí, sin incumplimientos bloqueantes' : 'No']" />
            @if ($v['reporteHref'] !== null)
                <x-ui.pdf-preview :url="$v['reporteHref']" title="Reporte de validación enviado" class="mt-sm inline-flex min-h-11 items-center font-semibold text-primary hover:underline">
                    Reporte de validación (PDF)
                </x-ui.pdf-preview>
            @endif

            @foreach ([['titulo' => 'Documentos generales', 'filas' => $v['documental']], ...array_map(fn ($s) => ['titulo' => 'Documentos · '.$s->nivel, 'filas' => $s->filas], $v['niveles']), ...array_map(fn ($s) => ['titulo' => 'Capacidad instalada · '.$s->nivel, 'filas' => $s->filas], $v['capacidad'])] as $grupo)
                <h3 class="mt-md text-body-md font-semibold text-ink">{{ $grupo['titulo'] }}</h3>
                <ul class="mt-xs divide-y divide-hairline border-y border-hairline">
                    @foreach ($grupo['filas'] as $fila)
                        <li class="py-xs" data-regla="{{ $fila->clave }}" data-estado="{{ $fila->estado }}">
                            <div class="flex flex-wrap items-center justify-between gap-sm">
                                <span class="text-body-md text-ink">{{ $fila->titulo }}</span>
                                <x-ui.status-tag :estado="$tags[$fila->estado] ?? 'pendiente'" :texto="$etiquetas[$fila->estado] ?? $fila->estado" />
                            </div>
                            @if ($fila->estado !== 'cumple')
                                <p class="mt-xxs text-body-sm text-muted">{{ $fila->mensaje }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endforeach
        @endif
    </x-ui.section>
    </div>

    {{-- Solo el supervisor (el componente pasa null a los demás): línea de tiempo de la revisión, más reciente primero. --}}
    @if ($historial !== null)
        <div role="tabpanel" id="panel-historial" aria-labelledby="pestana-historial" x-show="tab === 'historial'" x-cloak>
        <x-ui.section title="Historial">
            @forelse ($historial as $evento)
                <div class="border-b border-hairline py-xs">
                    <div class="flex flex-wrap items-baseline gap-x-sm">
                        <span class="text-body-md font-semibold text-ink">{{ $evento['que'] }}</span>
                        @if ($evento['nivel'])
                            <span class="text-caption text-muted">{{ $evento['nivel'] }}</span>
                        @endif
                    </div>
                    <p class="text-caption tabular-nums text-muted">{{ $evento['cuando']->format('d/m/Y H:i') }}@if ($evento['quien']) · {{ $evento['quien'] }}@endif</p>
                    @if ($evento['detalle'])
                        <p class="mt-xxs text-body-sm text-body">{{ $evento['detalle'] }}</p>
                    @endif
                </div>
            @empty
                <p class="text-body-md text-muted">Este trámite aún no tiene eventos registrados.</p>
            @endforelse
        </x-ui.section>
        </div>
    @endif
</div>
