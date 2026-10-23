{{-- Solo lectura (HUD SEDEQ): sin formularios ni enlaces de edición; los únicos enlaces son descargas protegidas por `view` (o `verReporteEnviado`), con vista previa (x-ui.pdf-preview). Los datos llegan ya decididos de ConsultarTramiteSedeq. --}}
@php
    $estadosVigencia = ['vigente' => 'completado', 'vencido' => 'error', 'sin_fecha_expresa' => 'pendiente', 'no_determinada' => 'pendiente', 'no_capturado' => 'pendiente'];
    $textosVigencia = ['vigente' => 'Vigente', 'vencido' => 'Vencido', 'sin_fecha_expresa' => 'Sin fecha expresa', 'no_determinada' => 'No determinada', 'no_capturado' => 'No capturado'];
    $etiquetas = ['cumple' => 'Correcto', 'advertencia' => 'Alerta', 'no_cumple' => 'No cumple', 'no_evaluable' => 'No verificado', 'no_verificable' => 'No verificable por el sistema'];
    $tags = ['cumple' => 'completado', 'advertencia' => 'en_curso', 'no_cumple' => 'error', 'no_evaluable' => 'pendiente', 'no_verificable' => 'no_disponible'];
    $estadosPaso = ['completado' => 'completado', 'en_progreso' => 'en_curso'];
@endphp
<div>
    <x-ui.page-header :title="$tramite['nombre'] ?? 'Plantel sin nombre'" eyebrow="Trámite · solo lectura" :back-href="$volver" back-label="Volver" />

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

    @foreach ($tramite['niveles'] as $nivel)
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
    @endforeach

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
