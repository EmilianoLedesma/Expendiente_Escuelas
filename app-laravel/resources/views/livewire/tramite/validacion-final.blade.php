<div class="max-w-3xl">
    <x-ui.page-header eyebrow="Último paso" title="Validación final">
        <x-slot:intro>Revisamos que los datos que capturaste coincidan con los documentos que subiste. Corrige lo que se indique y vuelve a validar.</x-slot:intro>
    </x-ui.page-header>

    @if ($validacion)
        @php
            $etiquetas = ['cumple' => 'Correcto', 'advertencia' => 'Alerta', 'no_cumple' => 'Debe corregirse', 'no_evaluable' => 'No verificado'];
            $tags = ['cumple' => 'completado', 'advertencia' => 'en_curso', 'no_cumple' => 'error', 'no_evaluable' => 'pendiente'];
            $errores = $validacion->conEstado('no_cumple');
            $alertas = $validacion->conEstado('advertencia');
        @endphp

        @if ($validacion->listaParaEnvio)
            <x-ui.alert tipo="success" titulo="Tu expediente no tiene errores que impidan enviarlo">
                @if ($alertas !== [])
                    Hay {{ count($alertas) }} {{ count($alertas) === 1 ? 'alerta' : 'alertas' }} que SEDEQ revisará; puedes corregirlas si lo necesitas.
                @else
                    Todos los datos coinciden con tus documentos.
                @endif
                El envío a SEDEQ se habilitará en una próxima versión.
            </x-ui.alert>
        @else
            <x-ui.alert tipo="error" titulo="Hay errores que debes corregir antes de enviar">
                {{ count($errores) }} {{ count($errores) === 1 ? 'revisión no se cumple' : 'revisiones no se cumplen' }}. Usa «Corregir» en cada documento señalado.
            </x-ui.alert>
        @endif

        <ol class="mt-lg divide-y divide-hairline rounded-lg border border-hairline px-md sm:px-lg">
            @foreach (['no_cumple', 'advertencia', 'no_evaluable', 'cumple'] as $estado)
                @foreach ($validacion->conEstado($estado) as $fila)
                    <li class="py-md" data-regla="{{ $fila->clave }}" data-estado="{{ $fila->estado }}">
                        <div class="flex flex-wrap items-center justify-between gap-sm">
                            <h2 class="text-body-md font-semibold text-ink">{{ $fila->titulo }}</h2>
                            <x-ui.status-tag :estado="$tags[$fila->estado]" :texto="$etiquetas[$fila->estado]" />
                        </div>
                        @if ($fila->estado !== 'cumple')
                            <p class="mt-xxs text-body-sm text-ink">{{ $fila->mensaje }}</p>
                            @if ($fila->lineas !== [])
                                <ul class="mt-xs list-disc space-y-xxs pl-lg text-body-sm text-muted">
                                    @foreach ($fila->lineas as $linea)
                                        <li>{{ $linea }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if ($fila->documentos !== [])
                                <ul class="mt-sm flex flex-wrap gap-sm">
                                    @foreach ($fila->documentos as $clave => $nombre)
                                        <li>
                                            <a href="{{ route('tramite.paso2-documentos', ['escuela' => $escuela->id, 'corregir' => $clave]) }}" class="inline-flex min-h-11 items-center gap-xxs font-semibold text-primary underline underline-offset-4 hover:no-underline">
                                                Corregir: {{ $nombre }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        @endif
                    </li>
                @endforeach
            @endforeach
        </ol>

        <div class="mt-lg flex flex-wrap items-center gap-md">
            <x-ui.button-primary type="button" wire:click="validarDeNuevo">Validar de nuevo</x-ui.button-primary>
            <a href="{{ route('tramite.validacion.reporte', ['escuela' => $escuela->id, 'evaluacion' => $validacion->evaluacionId]) }}" target="_blank" class="inline-flex min-h-11 items-center gap-xxs font-semibold text-primary underline underline-offset-4 hover:no-underline">
                <x-ui.icon nombre="arrow-down-tray" class="h-5 w-5" />
                Descargar reporte en PDF
            </a>
            <span class="text-body-sm text-muted">Validado el {{ $validacion->generadaEn->format('d/m/Y H:i') }}</span>
        </div>
    @endif

    <div class="mt-xl border-t border-hairline pt-lg">
        <a href="{{ route('tramite.resumen', ['escuela' => $escuela->id]) }}" class="inline-flex min-h-11 items-center font-semibold text-primary underline underline-offset-4 hover:no-underline">Volver al resumen</a>
    </div>
</div>
