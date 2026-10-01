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
        $identidadAyuda = match ($tipoPersona) {
            'fisica_con_gestor' => 'Del gestor que realiza el trámite.',
            'moral' => 'Del representante legal.',
            default => null,
        };
        $titulos = [
            'ine' => 'Credencial de elector (INE)',
            'acta_nacimiento' => 'Acta de nacimiento',
            'escritura_poder_facultades' => 'Escritura o poder notarial de facultades del representante legal',
            'escritura_inmueble' => 'Escritura o acreditación de ocupación del inmueble',
            'dictamen_uso_suelo' => 'Dictamen de Uso de Suelo',
            'constancia_seguridad_estructural' => 'Constancia de Seguridad Estructural',
            'acta_constitutiva' => 'Acta constitutiva',
            'poder_gestor' => 'Poder general para actos de administración (gestor)',
            'visto_bueno_proteccion_civil' => 'Visto Bueno / Dictamen de Protección Civil',
            'plano_inmueble' => 'Plano o croquis del inmueble',
            'certificado_numero_oficial' => 'Certificado de número oficial',
            'constancia_curp' => 'Constancia de CURP',
            'constancia_situacion_fiscal' => 'Constancia de Situación Fiscal',
        ];
    @endphp

    <ul class="mt-lg divide-y divide-hairline rounded-lg border border-hairline px-md sm:px-lg">
        @foreach ($clavesAplicables as $clave)
            @php
                $fila = [
                    'clave' => $clave,
                    'titulo' => $titulos[$clave] ?? $nombres[$clave] ?? $clave,
                    'escuelaId' => $escuela->id,
                    'capturado' => $capturados[$clave] ?? null,
                    'editable' => ! $capturados->has($clave) || ($reemplazando[$clave] ?? false),
                    'vencido' => $vencidos[$clave] ?? null,
                ];
            @endphp

            @if (in_array($clave, ['ine', 'constancia_curp']))
                @php($form = $clave === 'ine' ? 'ineForm' : 'constanciaCurpForm')
                <x-tramite.documento-row wire:key="doc-{{ $clave }}" :clave="$fila['clave']" :titulo="$fila['titulo']" :escuela-id="$fila['escuelaId']" :capturado="$fila['capturado']" :editable="$fila['editable']" :vencido="$fila['vencido']" :accion="$clave === 'ine' ? 'guardarIne' : 'guardarConstanciaCurp'">
                    <x-ui.field :id="$form.'.nombre'" :label="$clave === 'ine' ? 'Nombre como aparece en la credencial' : 'Nombre como aparece en la constancia'" :hint="$identidadAyuda">
                        <x-ui.input maxlength="200" wire:model.blur="{{ $form }}.nombre" />
                    </x-ui.field>
                    <x-ui.field :id="$form.'.curp'" label="CURP">
                        <x-ui.input wire:model.blur="{{ $form }}.curp" class="uppercase" maxlength="18" />
                    </x-ui.field>
                </x-tramite.documento-row>
            @elseif ($clave === 'constancia_situacion_fiscal')
                <x-tramite.documento-row wire:key="doc-{{ $clave }}" :clave="$fila['clave']" :titulo="$fila['titulo']" :escuela-id="$fila['escuelaId']" :capturado="$fila['capturado']" :editable="$fila['editable']" :vencido="$fila['vencido']" accion="guardarSituacionFiscal">
                    <x-ui.field id="situacionFiscalForm.nombre" label="Nombre o razón social como aparece en la constancia"><x-ui.input maxlength="200" wire:model.blur="situacionFiscalForm.nombre" /></x-ui.field>
                    <x-ui.field id="situacionFiscalForm.rfc" label="RFC"><x-ui.input wire:model.blur="situacionFiscalForm.rfc" class="uppercase" maxlength="13" /></x-ui.field>
                </x-tramite.documento-row>
            @elseif ($clave === 'certificado_numero_oficial')
                <x-tramite.documento-row wire:key="doc-{{ $clave }}" :clave="$fila['clave']" :titulo="$fila['titulo']" :escuela-id="$fila['escuelaId']" :capturado="$fila['capturado']" :editable="$fila['editable']" :vencido="$fila['vencido']" accion="guardarNumeroOficial">
                    <x-ui.field id="numeroOficialForm.calle" label="Calle" hint="Tal como aparece en el certificado."><x-ui.input maxlength="150" wire:model.blur="numeroOficialForm.calle" /></x-ui.field>
                    <x-ui.field id="numeroOficialForm.numeroExt" label="Número exterior" optional><x-ui.input maxlength="20" wire:model.blur="numeroOficialForm.numeroExt" /></x-ui.field>
                    <x-ui.field id="numeroOficialForm.colonia" label="Colonia"><x-ui.input maxlength="150" wire:model.blur="numeroOficialForm.colonia" /></x-ui.field>
                    <x-ui.field id="numeroOficialForm.municipio" label="Municipio"><x-ui.input maxlength="150" wire:model.blur="numeroOficialForm.municipio" /></x-ui.field>
                    <x-ui.field id="numeroOficialForm.codigoPostal" label="Código postal"><x-ui.input wire:model.blur="numeroOficialForm.codigoPostal" inputmode="numeric" maxlength="5" /></x-ui.field>
                </x-tramite.documento-row>
            @elseif ($clave === 'escritura_inmueble')
                <x-tramite.documento-row wire:key="doc-{{ $clave }}" :clave="$fila['clave']" :titulo="$fila['titulo']" :escuela-id="$fila['escuelaId']" :capturado="$fila['capturado']" :editable="$fila['editable']" :vencido="$fila['vencido']" accion="guardarAcreditacion">
                    <x-ui.radio-group
                        id="acreditacionForm.tipo"
                        legend="Tipo de acreditación"
                        wire:model.live="acreditacionForm.tipo"
                        :opciones="['escritura_publica' => 'Escritura pública', 'arrendamiento' => 'Arrendamiento', 'comodato' => 'Comodato', 'otro' => 'Otro']"
                    />

                    @if ($acreditacionForm->tipo === 'escritura_publica')
                        <x-ui.field id="acreditacionForm.numeroEscritura" label="Número de escritura"><x-ui.input maxlength="50" wire:model.blur="acreditacionForm.numeroEscritura" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.notarioNombre" label="Nombre del notario"><x-ui.input maxlength="150" wire:model.blur="acreditacionForm.notarioNombre" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.notarioNumero" label="Número del notario" optional><x-ui.input maxlength="20" wire:model.blur="acreditacionForm.notarioNumero" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.notarioLocalidad" label="Localidad del notario" optional><x-ui.input maxlength="100" wire:model.blur="acreditacionForm.notarioLocalidad" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.folioRpp" label="Folio del Registro Público de la Propiedad" optional><x-ui.input maxlength="50" wire:model.blur="acreditacionForm.folioRpp" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.fechaInscripcionRpp" label="Fecha de inscripción RPP" optional><x-ui.input type="date" wire:model.blur="acreditacionForm.fechaInscripcionRpp" /></x-ui.field>
                    @endif

                    @if (in_array($acreditacionForm->tipo, ['arrendamiento', 'comodato']))
                        <x-ui.field id="acreditacionForm.arrendadorComodante" label="Arrendador / comodante"><x-ui.input maxlength="200" wire:model.blur="acreditacionForm.arrendadorComodante" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.arrendatarioComodatario" label="Arrendatario / comodatario"><x-ui.input maxlength="200" wire:model.blur="acreditacionForm.arrendatarioComodatario" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.fechaContrato" label="Fecha del contrato"><x-ui.input type="date" wire:model.blur="acreditacionForm.fechaContrato" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.vigenciaContrato" label="Vigencia del contrato"><x-ui.input type="date" wire:model.blur="acreditacionForm.vigenciaContrato" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.usoAutorizado" label="Uso autorizado" optional><x-ui.input maxlength="200" wire:model.blur="acreditacionForm.usoAutorizado" /></x-ui.field>
                    @endif

                    @if ($acreditacionForm->tipo === 'otro')
                        <x-ui.field id="acreditacionForm.otroEspecifique" label="Especifique"><x-ui.input maxlength="200" wire:model.blur="acreditacionForm.otroEspecifique" /></x-ui.field>
                    @endif

                    <x-ui.field id="acreditacionForm.observaciones" label="Observaciones" optional><x-ui.textarea maxlength="1000" wire:model.blur="acreditacionForm.observaciones" /></x-ui.field>
                </x-tramite.documento-row>
            @elseif ($clave === 'dictamen_uso_suelo')
                <x-tramite.documento-row wire:key="doc-{{ $clave }}" :clave="$fila['clave']" :titulo="$fila['titulo']" :escuela-id="$fila['escuelaId']" :capturado="$fila['capturado']" :editable="$fila['editable']" :vencido="$fila['vencido']" accion="guardarDictamen">
                    <x-ui.field id="dictamenForm.fechaEmision" label="Fecha de emisión"><x-ui.input type="date" wire:model.blur="dictamenForm.fechaEmision" /></x-ui.field>
                </x-tramite.documento-row>
            @elseif ($clave === 'constancia_seguridad_estructural')
                <x-tramite.documento-row wire:key="doc-{{ $clave }}" :clave="$fila['clave']" :titulo="$fila['titulo']" :escuela-id="$fila['escuelaId']" :capturado="$fila['capturado']" :editable="$fila['editable']" :vencido="$fila['vencido']" accion="guardarConstancia">
                    <x-ui.field id="constanciaForm.fechaEmision" label="Fecha de emisión"><x-ui.input type="date" wire:model.blur="constanciaForm.fechaEmision" /></x-ui.field>
                    <x-ui.field id="constanciaForm.peritoNombre" label="Nombre del perito"><x-ui.input maxlength="200" wire:model.blur="constanciaForm.peritoNombre" /></x-ui.field>
                    <x-ui.field id="constanciaForm.peritoRegistroDro" label="Número de registro DRO"><x-ui.input maxlength="50" wire:model.blur="constanciaForm.peritoRegistroDro" /></x-ui.field>
                    <x-ui.field id="constanciaForm.peritoRegistroVigencia" label="Vigencia del registro" optional><x-ui.input type="date" wire:model.blur="constanciaForm.peritoRegistroVigencia" /></x-ui.field>
                </x-tramite.documento-row>
            @else
                {{-- WS-5a M1: toda clave aplicable sin bloque propio se sube como documento simple. --}}
                <x-tramite.documento-row wire:key="doc-{{ $clave }}" :clave="$fila['clave']" :titulo="$fila['titulo']" :escuela-id="$fila['escuelaId']" :capturado="$fila['capturado']" :editable="$fila['editable']" :vencido="$fila['vencido']" accion="guardarDocumentoSimple('{{ $clave }}')" />
            @endif
        @endforeach
    </ul>

    <div class="mt-xl border-t border-hairline pt-lg">
        <a href="{{ route('tramite.resumen', ['escuela' => $escuela->id]) }}" class="inline-flex min-h-11 items-center font-semibold text-primary underline underline-offset-4 hover:no-underline">Volver al resumen</a>
    </div>
</div>
