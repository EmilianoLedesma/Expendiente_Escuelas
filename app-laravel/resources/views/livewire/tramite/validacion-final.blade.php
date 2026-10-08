<div class="max-w-3xl">
    <x-ui.page-header eyebrow="Último paso" title="Validación final">
        <x-slot:intro>Revisamos que los datos que capturaste coincidan con los documentos que subiste. Corrige lo que se indique y vuelve a validar.</x-slot:intro>
    </x-ui.page-header>

    <x-ui.error-summary />

    @if ($validacion)
        @php
            $etiquetas = ['cumple' => 'Correcto', 'advertencia' => 'Alerta', 'no_cumple' => 'Debe corregirse', 'no_evaluable' => 'No verificado'];
            $tags = ['cumple' => 'completado', 'advertencia' => 'en_curso', 'no_cumple' => 'error', 'no_evaluable' => 'pendiente'];
            $etiquetasCapacidad = ['cumple' => 'Correcto', 'no_cumple' => 'Bloquea el envío', 'no_evaluable' => 'Falta capturar: bloquea el envío', 'no_verificable' => 'No verificable por el sistema'];
            $tagsCapacidad = ['cumple' => 'completado', 'no_cumple' => 'error', 'no_evaluable' => 'error', 'no_verificable' => 'no_disponible'];
            $motivos = $validacion->motivosBloqueo();
            $alertas = $validacion->totalConEstado('advertencia');
        @endphp

        @if ($validacion->listaParaEnvio)
            <x-ui.alert tipo="success" titulo="Tu expediente no tiene errores que impidan enviarlo">
                @if ($alertas !== [])
                    Hay {{ count($alertas) }} {{ count($alertas) === 1 ? 'alerta' : 'alertas' }} que SEDEQ revisará; puedes corregirlas si lo necesitas.
                @else
                    Todos los datos coinciden con tus documentos.
                @endif
            </x-ui.alert>
        @else
            <x-ui.alert tipo="error" titulo="Hay errores que debes corregir antes de enviar">
                {{ count($motivos) }} {{ count($motivos) === 1 ? 'revisión no se cumple' : 'revisiones no se cumplen' }}. Usa «Corregir» o «Revisar la captura» en cada punto señalado.
            </x-ui.alert>
        @endif

        @php
            $secciones = [[
                'titulo' => $validacion->niveles === [] ? null : 'Documentos del trámite',
                'filas' => $validacion->filas,
                'corregir' => fn (string $clave) => route('tramite.paso2-documentos', ['escuela' => $escuela->id, 'corregir' => $clave]),
            ]];
            foreach ($validacion->niveles as $seccion) {
                $secciones[] = [
                    'titulo' => 'Documentos del nivel: '.$seccion->nivel,
                    'filas' => $seccion->filas,
                    'corregir' => fn (string $clave) => route('tramite.paso2-nivel-documentos', ['escuelaNivel' => $seccion->escuelaNivelId]),
                ];
            }
        @endphp

        @foreach ($secciones as $seccion)
            @if ($seccion['titulo'])
                <h2 class="mt-xl text-title-md font-semibold text-ink">{{ $seccion['titulo'] }}</h2>
            @endif
            <ol class="mt-lg divide-y divide-hairline rounded-lg border border-hairline px-md sm:px-lg">
                @foreach (['no_cumple', 'advertencia', 'no_evaluable', 'cumple'] as $estado)
                    @foreach (array_filter($seccion['filas'], fn ($fila) => $fila->estado === $estado) as $fila)
                        <li class="py-md" data-regla="{{ $fila->clave }}" data-estado="{{ $fila->estado }}">
                            <div class="flex flex-wrap items-center justify-between gap-sm">
                                <h3 class="text-body-md font-semibold text-ink">{{ $fila->titulo }}</h3>
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
                                                <a href="{{ $seccion['corregir']($clave) }}" class="inline-flex min-h-11 items-center gap-xxs font-semibold text-primary underline underline-offset-4 hover:no-underline">
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
        @endforeach

        @foreach ($validacion->capacidad as $seccion)
            <section class="mt-xl" data-escuela-nivel="{{ $seccion->escuelaNivelId }}">
                <h2 class="text-title-md font-semibold text-ink">Capacidad instalada · {{ $seccion->nivel }}</h2>
                <p class="mt-xxs text-body-sm text-muted">Superficie, personal y mobiliario contra la norma, según la matrícula que capturaste. Lo que no se cumple, o lo que falta capturar, bloquea el envío. «No verificable por el sistema» no bloquea: SEDEQ lo revisará.</p>
                <ol class="mt-md divide-y divide-hairline rounded-lg border border-hairline px-md sm:px-lg">
                    @foreach (['no_cumple', 'no_evaluable', 'no_verificable', 'cumple'] as $estado)
                        @foreach ($seccion->conEstado($estado) as $fila)
                            <li class="py-md" data-regla="{{ $fila->clave }}" data-estado="{{ $fila->estado }}">
                                <div class="flex flex-wrap items-center justify-between gap-sm">
                                    <h3 class="text-body-md font-semibold text-ink">{{ $fila->titulo }}</h3>
                                    <x-ui.status-tag :estado="$tagsCapacidad[$fila->estado]" :texto="$etiquetasCapacidad[$fila->estado]" />
                                </div>
                                @if ($fila->estado !== 'cumple')
                                    @if ($fila->lineas !== [])
                                        <ul class="mt-xs list-disc space-y-xxs pl-lg text-body-sm text-muted">
                                            @foreach ($fila->lineas as $linea)
                                                <li>{{ $linea }}</li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="mt-xxs text-body-sm text-muted">{{ $fila->mensaje }}</p>
                                    @endif
                                    @if (in_array($fila->estado, ['no_cumple', 'no_evaluable'], true) && isset(\App\Application\Tramite\ResumenTramite::RUTAS_PASO3[$fila->pasoCorreccion]))
                                        <a href="{{ route(\App\Application\Tramite\ResumenTramite::RUTAS_PASO3[$fila->pasoCorreccion], ['escuelaNivel' => $seccion->escuelaNivelId]) }}" class="mt-sm inline-flex min-h-11 items-center font-semibold text-primary underline underline-offset-4 hover:no-underline">
                                            Revisar la captura
                                        </a>
                                    @endif
                                @endif
                            </li>
                        @endforeach
                    @endforeach
                </ol>
            </section>
        @endforeach

        <div class="mt-lg flex flex-wrap items-center gap-md">
            <x-ui.button-primary type="button" wire:click="validarDeNuevo">Validar de nuevo</x-ui.button-primary>
            <a href="{{ route('tramite.validacion.reporte', ['escuela' => $escuela->id, 'evaluacion' => $validacion->evaluacionId]) }}" target="_blank" class="inline-flex min-h-11 items-center gap-xxs font-semibold text-primary underline underline-offset-4 hover:no-underline">
                <x-ui.icon nombre="arrow-down-tray" class="h-5 w-5" />
                Descargar reporte en PDF
            </a>
            <span class="text-body-sm text-muted">Validado el {{ $validacion->generadaEn->format('d/m/Y H:i') }}</span>
        </div>

        {{-- WS-7a §6. Sin wire:ignore a propósito: si el envío se rechaza, el morph quita `open`, el diálogo se cierra y queda visible el resumen de errores. --}}
        <section id="envio" tabindex="-1" class="mt-xl border-t border-hairline pt-lg" aria-labelledby="envio-titulo">
            <h2 id="envio-titulo" class="text-title-md font-semibold text-ink">Enviar a SEDEQ</h2>
            @error('envio')
                <p class="mt-xs text-body-sm font-semibold text-error-ink">{{ $message }}</p>
            @enderror
            @if ($validacion->listaParaEnvio)
                <p class="mt-xxs text-body-sm text-muted">Al enviar revisamos todo de nuevo. Una vez enviado, el trámite ya no podrá modificarse.</p>
                <x-ui.button-primary type="button" onclick="document.getElementById('enviar-tramite').showModal()" class="mt-md">
                    Enviar a SEDEQ
                </x-ui.button-primary>
                <dialog id="enviar-tramite" aria-labelledby="enviar-tramite-titulo" onclick="if (event.target === this) this.close()" class="modal rounded-lg border border-hairline bg-canvas p-lg text-left text-body-md text-ink">
                    <h2 id="enviar-tramite-titulo" class="text-title-md font-semibold text-ink">¿Enviar el trámite a SEDEQ?</h2>
                    <p class="mt-sm">Una vez enviado, el trámite ya no podrá modificarse.</p>
                    <div class="mt-lg flex flex-wrap justify-end gap-sm">
                        <button type="button" onclick="this.closest('dialog').close()" class="inline-flex min-h-11 items-center justify-center rounded-md border border-control bg-canvas px-lg text-body-md font-semibold text-ink transition duration-150 hover:border-ink hover:bg-surface-card active:bg-surface-soft">
                            Cancelar
                        </button>
                        <x-ui.button-primary type="button" wire:click="enviar" wire:loading.attr="disabled">
                            Sí, enviar a SEDEQ
                        </x-ui.button-primary>
                    </div>
                </dialog>
            @else
                <x-ui.button-primary type="button" :disabled="true" class="mt-md">Enviar a SEDEQ</x-ui.button-primary>
                <p class="mt-xs text-body-sm text-muted">Corrige lo que bloquea el envío para poder enviar:</p>
                <ul class="mt-xs list-disc space-y-xxs pl-lg text-body-sm text-ink">
                    @foreach ($motivos as $motivo)
                        <li>{{ $motivo }}</li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    <div class="mt-xl border-t border-hairline pt-lg">
        <a href="{{ route('tramite.resumen', ['escuela' => $escuela->id]) }}" class="inline-flex min-h-11 items-center font-semibold text-primary underline underline-offset-4 hover:no-underline">Volver al resumen</a>
    </div>
</div>
