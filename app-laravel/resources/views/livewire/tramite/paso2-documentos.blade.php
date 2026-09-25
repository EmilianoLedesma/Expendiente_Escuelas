<div class="max-w-3xl">
    <x-ui.page-header :eyebrow="$encabezado" title="Documentos">
        <x-slot:intro>Sube cada documento en PDF. Puedes hacerlo en el orden que prefieras: cada uno se guarda por separado.</x-slot:intro>
    </x-ui.page-header>

    <p class="text-title-sm font-semibold text-ink">{{ $totalCompletos }} de {{ $totalAplicables }} documentos completos</p>

    @error('vigencia')
        <x-ui.alert tipo="error" titulo="Un documento debe subirse de nuevo" id="vigencia" class="mt-md">{{ $message }}</x-ui.alert>
    @enderror

    <div class="mt-md">
        <x-ui.error-summary :excluir="['vigencia']" />
    </div>

    @php
        $titulos = [
            'ine' => 'Credencial de elector (INE)',
            'acta_nacimiento' => 'Acta de nacimiento',
            'escritura_poder_facultades' => 'Escritura o poder notarial de facultades del representante legal',
            'escritura_inmueble' => 'Escritura o acreditación de ocupación del inmueble',
            'dictamen_uso_suelo' => 'Dictamen de Uso de Suelo',
            'constancia_seguridad_estructural' => 'Constancia de Seguridad Estructural',
            'formato_solicitud' => 'Formato de Solicitud',
        ];
    @endphp

    <ul class="mt-lg divide-y divide-hairline rounded-lg border border-hairline px-md sm:px-lg">
        @foreach ($clavesAplicables as $clave)
            @php
                $fila = [
                    'clave' => $clave,
                    'titulo' => $titulos[$clave] ?? $clave,
                    'escuelaId' => $escuela->id,
                    'capturado' => $capturados[$clave] ?? null,
                    'editable' => ! $capturados->has($clave) || ($reemplazando[$clave] ?? false),
                    'vencido' => $vencidos[$clave] ?? null,
                ];
            @endphp

            @if (in_array($clave, ['ine', 'acta_nacimiento', 'escritura_poder_facultades']))
                <x-tramite.documento-row wire:key="doc-{{ $clave }}" :clave="$fila['clave']" :titulo="$fila['titulo']" :escuela-id="$fila['escuelaId']" :capturado="$fila['capturado']" :editable="$fila['editable']" :vencido="$fila['vencido']" accion="guardarDocumentoSimple('{{ $clave }}')" />
            @elseif ($clave === 'escritura_inmueble')
                <x-tramite.documento-row wire:key="doc-{{ $clave }}" :clave="$fila['clave']" :titulo="$fila['titulo']" :escuela-id="$fila['escuelaId']" :capturado="$fila['capturado']" :editable="$fila['editable']" :vencido="$fila['vencido']" accion="guardarAcreditacion">
                    <x-ui.radio-group
                        id="acreditacionForm.tipo"
                        legend="Tipo de acreditación"
                        wire:model.live="acreditacionForm.tipo"
                        :opciones="['escritura_publica' => 'Escritura pública', 'arrendamiento' => 'Arrendamiento', 'comodato' => 'Comodato', 'otro' => 'Otro']"
                    />

                    @if ($acreditacionForm->tipo === 'escritura_publica')
                        <x-ui.field id="acreditacionForm.numeroEscritura" label="Número de escritura"><x-ui.input wire:model="acreditacionForm.numeroEscritura" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.notarioNombre" label="Nombre del notario"><x-ui.input wire:model="acreditacionForm.notarioNombre" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.notarioNumero" label="Número del notario" optional><x-ui.input wire:model="acreditacionForm.notarioNumero" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.notarioLocalidad" label="Localidad del notario" optional><x-ui.input wire:model="acreditacionForm.notarioLocalidad" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.folioRpp" label="Folio del Registro Público de la Propiedad" optional><x-ui.input wire:model="acreditacionForm.folioRpp" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.fechaInscripcionRpp" label="Fecha de inscripción RPP" optional><x-ui.input type="date" wire:model="acreditacionForm.fechaInscripcionRpp" /></x-ui.field>
                    @endif

                    @if (in_array($acreditacionForm->tipo, ['arrendamiento', 'comodato']))
                        <x-ui.field id="acreditacionForm.arrendadorComodante" label="Arrendador / comodante"><x-ui.input wire:model="acreditacionForm.arrendadorComodante" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.arrendatarioComodatario" label="Arrendatario / comodatario"><x-ui.input wire:model="acreditacionForm.arrendatarioComodatario" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.fechaContrato" label="Fecha del contrato"><x-ui.input type="date" wire:model="acreditacionForm.fechaContrato" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.vigenciaContrato" label="Vigencia del contrato"><x-ui.input type="date" wire:model="acreditacionForm.vigenciaContrato" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.usoAutorizado" label="Uso autorizado" optional><x-ui.input wire:model="acreditacionForm.usoAutorizado" /></x-ui.field>
                    @endif

                    @if ($acreditacionForm->tipo === 'otro')
                        <x-ui.field id="acreditacionForm.otroEspecifique" label="Especifique"><x-ui.input wire:model="acreditacionForm.otroEspecifique" /></x-ui.field>
                    @endif

                    <x-ui.field id="acreditacionForm.observaciones" label="Observaciones" optional><x-ui.textarea wire:model="acreditacionForm.observaciones" /></x-ui.field>
                </x-tramite.documento-row>
            @elseif ($clave === 'dictamen_uso_suelo')
                <x-tramite.documento-row wire:key="doc-{{ $clave }}" :clave="$fila['clave']" :titulo="$fila['titulo']" :escuela-id="$fila['escuelaId']" :capturado="$fila['capturado']" :editable="$fila['editable']" :vencido="$fila['vencido']" accion="guardarDictamen">
                    <x-ui.field id="dictamenForm.fechaEmision" label="Fecha de emisión"><x-ui.input type="date" wire:model="dictamenForm.fechaEmision" /></x-ui.field>
                </x-tramite.documento-row>
            @elseif ($clave === 'constancia_seguridad_estructural')
                <x-tramite.documento-row wire:key="doc-{{ $clave }}" :clave="$fila['clave']" :titulo="$fila['titulo']" :escuela-id="$fila['escuelaId']" :capturado="$fila['capturado']" :editable="$fila['editable']" :vencido="$fila['vencido']" accion="guardarConstancia">
                    <x-ui.field id="constanciaForm.fechaEmision" label="Fecha de emisión"><x-ui.input type="date" wire:model="constanciaForm.fechaEmision" /></x-ui.field>
                    <x-ui.field id="constanciaForm.peritoNombre" label="Nombre del perito"><x-ui.input wire:model="constanciaForm.peritoNombre" /></x-ui.field>
                    <x-ui.field id="constanciaForm.peritoRegistroDro" label="Número de registro DRO"><x-ui.input wire:model="constanciaForm.peritoRegistroDro" /></x-ui.field>
                    <x-ui.field id="constanciaForm.peritoRegistroVigencia" label="Vigencia del registro" optional><x-ui.input type="date" wire:model="constanciaForm.peritoRegistroVigencia" /></x-ui.field>
                </x-tramite.documento-row>
            @elseif ($clave === 'formato_solicitud')
                <x-tramite.documento-row wire:key="doc-{{ $clave }}" :clave="$fila['clave']" :titulo="$fila['titulo']" :escuela-id="$fila['escuelaId']" :capturado="$fila['capturado']" :editable="$fila['editable']" :vencido="$fila['vencido']" accion="guardarFormatoSolicitud" etiqueta-archivo="Subir Formato de Solicitud firmado">
                    <x-slot:extra>
                        <a href="{{ route('tramite.paso2-documentos.formato-solicitud', ['escuela' => $escuela->id]) }}" target="_blank" class="inline-flex min-h-11 items-center gap-xxs font-semibold text-primary underline underline-offset-4 hover:no-underline">
                            <x-ui.icon nombre="arrow-down-tray" class="h-5 w-5" />
                            Generar y descargar Formato de Solicitud
                        </a>
                    </x-slot:extra>
                </x-tramite.documento-row>
            @endif
        @endforeach
    </ul>

    <div class="mt-xl border-t border-hairline pt-lg">
        <a href="{{ route('tramite.resumen', ['escuela' => $escuela->id]) }}" class="inline-flex min-h-11 items-center font-semibold text-primary underline underline-offset-4 hover:no-underline">Volver al resumen</a>
    </div>
</div>
