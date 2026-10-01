<div class="max-w-3xl">
    <x-ui.page-header :eyebrow="$encabezado" title="Documentos del nivel">
        <x-slot:intro>Captura el turno y el tipo de alumnado de {{ $escuelaNivel->nivelEducativo->nombre }}, genera el Formato de Solicitud, fírmalo y súbelo junto con los demás documentos en PDF.</x-slot:intro>
    </x-ui.page-header>

    <x-ui.error-summary />

    <x-ui.section title="Datos del nivel">
        {{-- Decisión del dueño (WS-5b): cambiar turno o tipo de alumnado descarta el Formato firmado ya subido. --}}
        <form novalidate wire:submit="guardarDatos" @if ($formatoSubido) wire:confirm="Si cambias el turno o el tipo de alumnado se descartará el Formato de Solicitud firmado que ya subiste. ¿Deseas continuar?" @endif class="space-y-md">
            <x-ui.radio-group id="turno" legend="Turno" wire:model="turno" :opciones="['matutino' => 'Matutino', 'vespertino' => 'Vespertino', 'mixto' => 'Mixto']" />
            <x-ui.radio-group id="tipoAlumnado" legend="Tipo de alumnado" wire:model="tipoAlumnado" :opciones="['mixto' => 'Mixto', 'femenino' => 'Femenino', 'masculino' => 'Masculino']" />
            @if ($formatoSubido)
                <x-ui.alert tipo="warning">Si cambias el turno o el tipo de alumnado se descartará el Formato de Solicitud firmado que ya subiste, y tendrás que generarlo, firmarlo y subirlo de nuevo.</x-ui.alert>
            @endif
            <x-ui.button-primary type="submit" wire:loading.attr="disabled" wire:target="guardarDatos">Guardar datos del nivel</x-ui.button-primary>
        </form>
    </x-ui.section>

    <p class="mt-lg text-title-sm font-semibold text-ink">{{ $capturados->count() }} de {{ count($clavesAplicables) }} documentos completos</p>

    <ul class="mt-md divide-y divide-hairline rounded-lg border border-hairline px-md sm:px-lg">
        @foreach ($clavesAplicables as $clave)
            @php
                $fila = [
                    'titulo' => $titulos[$clave] ?? $clave,
                    'capturado' => $capturados[$clave] ?? null,
                    'editable' => ! $capturados->has($clave) || ($reemplazando[$clave] ?? false),
                    'descargarHref' => route('tramite.paso2-nivel-documentos.descargar', ['escuelaNivel' => $escuelaNivel->id, 'clave' => $clave]),
                    'vencido' => $vencidos[$clave] ?? null,
                ];
            @endphp

            @if ($clave === 'recibo_pago_derechos')
                <x-tramite.documento-row wire:key="doc-{{ $clave }}" :clave="$clave" :titulo="$fila['titulo']" :escuela-id="$escuelaNivel->escuela_id" :capturado="$fila['capturado']" :editable="$fila['editable']" :descargar-href="$fila['descargarHref']" :vencido="$fila['vencido']" accion="guardarRecibo">
                    <x-ui.field id="recibo.folio" label="Folio del recibo"><x-ui.input maxlength="50" wire:model.blur="recibo.folio" /></x-ui.field>
                    <x-ui.field id="recibo.monto" label="Monto pagado"><x-ui.input type="number" step="0.01" min="0.01" inputmode="decimal" unit="MXN" wire:model.blur="recibo.monto" /></x-ui.field>
                    <x-ui.field id="recibo.fechaPago" label="Fecha de pago"><x-ui.input type="date" wire:model.blur="recibo.fechaPago" /></x-ui.field>
                    <x-ui.field id="recibo.portalReferencia" label="Referencia del portal de pago" optional><x-ui.input maxlength="200" wire:model.blur="recibo.portalReferencia" /></x-ui.field>
                </x-tramite.documento-row>
            @elseif ($clave === 'formato_solicitud')
                <x-tramite.documento-row wire:key="doc-{{ $clave }}" :clave="$clave" :titulo="$fila['titulo']" :escuela-id="$escuelaNivel->escuela_id" :capturado="$fila['capturado']" :editable="$fila['editable']" :descargar-href="$fila['descargarHref']" :vencido="$fila['vencido']" accion="guardarDocumento('formato_solicitud')" etiqueta-archivo="Subir Formato de Solicitud firmado">
                    <x-slot:extra>
                        @if ($datosCapturados)
                            <a href="{{ route('tramite.paso2-nivel-documentos.formato-solicitud', ['escuelaNivel' => $escuelaNivel->id]) }}" target="_blank" class="inline-flex min-h-11 items-center gap-xxs font-semibold text-primary underline underline-offset-4 hover:no-underline">
                                <x-ui.icon nombre="arrow-down-tray" class="h-5 w-5" />
                                Generar y descargar Formato de Solicitud
                            </a>
                        @else
                            <p class="text-body-sm text-muted">Guarda el turno y el tipo de alumnado para generar el Formato de Solicitud.</p>
                        @endif
                    </x-slot:extra>
                </x-tramite.documento-row>
            @elseif (in_array($clave, \App\Livewire\Tramite\Paso24DocumentosNivel::CON_TITULOS, true))
                <x-tramite.documento-row wire:key="doc-{{ $clave }}" :clave="$clave" :titulo="$fila['titulo']" :escuela-id="$escuelaNivel->escuela_id" :capturado="$fila['capturado']" :editable="$fila['editable']" :descargar-href="$fila['descargarHref']" :vencido="$fila['vencido']" accion="guardarDocumento('{{ $clave }}')">
                    <x-ui.field id="acervoTitulos.{{ $clave }}" label="Número de títulos de la relación"><x-ui.input type="number" step="1" min="0" inputmode="numeric" wire:model.blur="acervoTitulos.{{ $clave }}" /></x-ui.field>
                </x-tramite.documento-row>
            @else
                {{-- WS-5a M1: toda clave aplicable sin bloque propio se sube como documento simple. --}}
                <x-tramite.documento-row wire:key="doc-{{ $clave }}" :clave="$clave" :titulo="$fila['titulo']" :escuela-id="$escuelaNivel->escuela_id" :capturado="$fila['capturado']" :editable="$fila['editable']" :descargar-href="$fila['descargarHref']" :vencido="$fila['vencido']" accion="guardarDocumento('{{ $clave }}')" />
            @endif
        @endforeach
    </ul>

    <div class="mt-xl flex flex-wrap items-center gap-md border-t border-hairline pt-lg">
        @if ($completo)
            <x-ui.button-primary :href="route('tramite.paso3-inmueble', ['escuelaNivel' => $escuelaNivel->id])">Continuar a Datos del inmueble</x-ui.button-primary>
        @endif
        <a href="{{ route('tramite.resumen', ['escuela' => $escuelaNivel->escuela_id]) }}" class="inline-flex min-h-11 items-center font-semibold text-primary underline underline-offset-4 hover:no-underline">Volver al resumen</a>
    </div>
</div>
