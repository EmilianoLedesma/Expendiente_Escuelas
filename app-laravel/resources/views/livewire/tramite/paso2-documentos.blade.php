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

        // Un sub-paso por documento aplicable, en el orden del catálogo.
        $clavesAplicables = array_values($clavesAplicables);
        $etiquetasPasos = array_map(fn ($c) => $titulos[$c] ?? $nombres[$c] ?? $c, $clavesAplicables);
        $completosPasos = array_map(fn ($c) => $capturados->has($c), $clavesAplicables);
    @endphp

    <x-ui.stepper :pasos="$etiquetasPasos" :completos="$completosPasos" compacto class="mt-lg">
    @foreach ($clavesAplicables as $indicePaso => $claveDelPaso)
    <x-ui.stepper-panel :indice="$indicePaso" :total="count($clavesAplicables)" :titulo="$etiquetasPasos[$indicePaso]">
    <ul class="divide-y divide-hairline rounded-lg border border-hairline px-md sm:px-lg">
        @foreach ([$claveDelPaso] as $clave)
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
                        <x-ui.field id="acreditacionForm.fechaEscritura" label="Fecha de la escritura"><x-ui.input type="date" wire:model.blur="acreditacionForm.fechaEscritura" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.propietarioNombre" label="Nombre completo o razón social del propietario" hint="Tal como aparece en la escritura."><x-ui.input maxlength="200" wire:model.blur="acreditacionForm.propietarioNombre" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.notarioNombre" label="Nombre del notario"><x-ui.input maxlength="150" wire:model.blur="acreditacionForm.notarioNombre" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.notarioNumero" label="Número de notaría" optional><x-ui.input maxlength="20" wire:model.blur="acreditacionForm.notarioNumero" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.notarioEntidadFederativaId" label="Entidad federativa" hint="Donde está la notaría." optional>
                            <x-ui.select wire:model.live="acreditacionForm.notarioEntidadFederativaId">
                                <option value="">Seleccione…</option>
                                @foreach ($entidades as $entidad)
                                    <option value="{{ $entidad['id'] }}">{{ $entidad['nombre'] }}</option>
                                @endforeach
                            </x-ui.select>
                            @if ($entidades === [])
                                <p class="mt-xs text-body-sm text-muted">Aún no hay entidades cargadas: pida a la persona administradora que importe el catálogo geográfico.</p>
                            @endif
                        </x-ui.field>
                        <x-ui.field id="acreditacionForm.notarioMunicipioId" label="Municipio" optional>
                            <x-ui.select wire:model.blur="acreditacionForm.notarioMunicipioId" :disabled="$acreditacionForm->notarioEntidadFederativaId === ''">
                                <option value="">Seleccione…</option>
                                @foreach ($municipios as $municipio)
                                    <option value="{{ $municipio['id'] }}">{{ $municipio['nombre'] }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.field id="acreditacionForm.folioRpp" label="Folio o antecedente registral (Registro Público de la Propiedad)" hint="Dato de inscripción de la escritura en el Registro Público de la Propiedad. Puede omitirlo si no lo tiene a la mano." optional><x-ui.input maxlength="50" wire:model.blur="acreditacionForm.folioRpp" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.fechaInscripcionRpp" label="Fecha de inscripción" optional><x-ui.input type="date" wire:model.blur="acreditacionForm.fechaInscripcionRpp" /></x-ui.field>
                    @endif

                    @if (in_array($acreditacionForm->tipo, ['arrendamiento', 'comodato']))
                        <x-ui.field id="acreditacionForm.arrendadorComodante" :label="$acreditacionForm->tipo === 'comodato' ? 'Comodante' : 'Arrendador'"><x-ui.input maxlength="200" wire:model.blur="acreditacionForm.arrendadorComodante" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.arrendatarioComodatario" :label="$acreditacionForm->tipo === 'comodato' ? 'Comodatario' : 'Arrendatario'"><x-ui.input maxlength="200" wire:model.blur="acreditacionForm.arrendatarioComodatario" /></x-ui.field>
                        <x-ui.checkbox id="acreditacionForm.arrendadorEsPropietario" wire:model.live="acreditacionForm.arrendadorEsPropietario">El {{ $acreditacionForm->tipo === 'comodato' ? 'comodante' : 'arrendador' }} es el propietario del inmueble</x-ui.checkbox>
                        @if (! $acreditacionForm->arrendadorEsPropietario)
                            <x-ui.field id="acreditacionForm.facultadesReferencia" label="Documento que acredita sus facultades" hint="Poder, mandato u otro documento por el que puede conceder el uso del inmueble."><x-ui.input maxlength="200" wire:model.blur="acreditacionForm.facultadesReferencia" /></x-ui.field>
                        @endif
                        <x-ui.field id="acreditacionForm.fechaContrato" label="Fecha de celebración del contrato" hint="El día en que se firmó."><x-ui.input type="date" wire:model.blur="acreditacionForm.fechaContrato" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.fechaInicioVigencia" label="Inicio de la vigencia" hint="Puede ser distinta de la fecha de celebración."><x-ui.input type="date" wire:model.blur="acreditacionForm.fechaInicioVigencia" /></x-ui.field>
                        <x-ui.checkbox id="acreditacionForm.vigenciaIndeterminada" wire:model.live="acreditacionForm.vigenciaIndeterminada" descripcion="Se registra tal como consta en el contrato; SEDEQ determina si es aceptable.">El contrato no tiene fecha de terminación</x-ui.checkbox>
                        @if (! $acreditacionForm->vigenciaIndeterminada)
                            <x-ui.field id="acreditacionForm.vigenciaContrato" label="Fin de la vigencia"><x-ui.input type="date" wire:model.blur="acreditacionForm.vigenciaContrato" /></x-ui.field>
                        @endif
                        <x-ui.field id="acreditacionForm.usoAutorizado" label="Uso autorizado del inmueble" hint="El destino que permite el contrato, por ejemplo: prestación de servicio educativo."><x-ui.input maxlength="200" wire:model.blur="acreditacionForm.usoAutorizado" /></x-ui.field>
                        <x-ui.checkbox id="acreditacionForm.ratificadoNotario" wire:model.live="acreditacionForm.ratificadoNotario" descripcion="Un notario público confirmó el contenido y las firmas del contrato. Si se exige o no depende del nivel educativo; aquí solo se registra.">Contrato ratificado ante notario público</x-ui.checkbox>
                        @if ($acreditacionForm->ratificadoNotario)
                            <x-ui.field id="acreditacionForm.ratificacionFecha" label="Fecha de ratificación" optional><x-ui.input type="date" wire:model.blur="acreditacionForm.ratificacionFecha" /></x-ui.field>
                            <x-ui.field id="acreditacionForm.ratificacionNotarioNombre" label="Notario que ratificó" optional><x-ui.input maxlength="150" wire:model.blur="acreditacionForm.ratificacionNotarioNombre" /></x-ui.field>
                            <x-ui.field id="acreditacionForm.ratificacionNotariaNumero" label="Número de notaría" optional><x-ui.input maxlength="20" wire:model.blur="acreditacionForm.ratificacionNotariaNumero" /></x-ui.field>
                        @endif
                    @endif

                    @if ($acreditacionForm->tipo === 'otro')
                        <x-ui.field id="acreditacionForm.otroEspecifique" label="Denominación del instrumento" hint="Por ejemplo: resolución judicial, convenio, concesión."><x-ui.input maxlength="200" wire:model.blur="acreditacionForm.otroEspecifique" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.otroReferencia" label="Número o referencia del documento"><x-ui.input maxlength="100" wire:model.blur="acreditacionForm.otroReferencia" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.otroTitular" label="Titular o beneficiario"><x-ui.input maxlength="200" wire:model.blur="acreditacionForm.otroTitular" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.otroFechaExpedicion" label="Fecha de expedición u otorgamiento"><x-ui.input type="date" wire:model.blur="acreditacionForm.otroFechaExpedicion" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.otroAutoridad" label="Autoridad o fedatario que lo emite"><x-ui.input maxlength="200" wire:model.blur="acreditacionForm.otroAutoridad" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.otroDerecho" label="Derecho que acredita" hint="Por ejemplo: propiedad, posesión, usufructo, uso."><x-ui.input maxlength="200" wire:model.blur="acreditacionForm.otroDerecho" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.usoAutorizado" label="Uso autorizado del inmueble" hint="La finalidad para la que el instrumento permite ocupar el inmueble. SEDEQ revisa si el instrumento es suficiente."><x-ui.input maxlength="200" wire:model.blur="acreditacionForm.usoAutorizado" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.fechaInicioVigencia" label="Inicio de la vigencia" optional><x-ui.input type="date" wire:model.blur="acreditacionForm.fechaInicioVigencia" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.vigenciaContrato" label="Fin de la vigencia" optional><x-ui.input type="date" wire:model.blur="acreditacionForm.vigenciaContrato" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.folioRpp" label="Folio o antecedente registral" hint="Solo si el instrumento está inscrito en el Registro Público de la Propiedad." optional><x-ui.input maxlength="50" wire:model.blur="acreditacionForm.folioRpp" /></x-ui.field>
                        <x-ui.field id="acreditacionForm.fechaInscripcionRpp" label="Fecha de inscripción" optional><x-ui.input type="date" wire:model.blur="acreditacionForm.fechaInscripcionRpp" /></x-ui.field>
                    @endif
                </x-tramite.documento-row>
            @elseif ($clave === 'dictamen_uso_suelo')
                <x-tramite.documento-row wire:key="doc-{{ $clave }}" :clave="$fila['clave']" :titulo="$fila['titulo']" :escuela-id="$fila['escuelaId']" :capturado="$fila['capturado']" :editable="$fila['editable']" :vencido="$fila['vencido']" accion="guardarDictamen">
                    <x-ui.field id="dictamenForm.autoridadExpide" label="Autoridad que expide"><x-ui.input maxlength="200" wire:model.blur="dictamenForm.autoridadExpide" /></x-ui.field>
                    <x-ui.field id="dictamenForm.folioDictamen" label="Folio del dictamen"><x-ui.input maxlength="50" wire:model.blur="dictamenForm.folioDictamen" /></x-ui.field>
                    <x-ui.field id="dictamenForm.fechaEmision" label="Fecha de emisión"><x-ui.input type="date" wire:model.blur="dictamenForm.fechaEmision" /></x-ui.field>
                    <x-ui.field id="dictamenForm.giroAutorizado" label="Uso o giro autorizado según el dictamen" hint="Capture el uso autorizado tal como aparece en el documento emitido por la autoridad municipal."><x-ui.input maxlength="200" wire:model.blur="dictamenForm.giroAutorizado" /></x-ui.field>
                    <x-ui.radio-group
                        id="dictamenForm.tipoVigencia"
                        legend="¿El documento indica una fecha de vencimiento?"
                        wire:model.live="dictamenForm.tipoVigencia"
                        :opciones="['fecha_expresa' => 'Sí, el documento indica una fecha de vencimiento', 'sin_fecha_expresa' => 'No, el documento no indica fecha de vencimiento']"
                    />
                    <p class="text-body-sm text-muted">Registre solo lo que dice el documento. SEDEQ revisa su vigencia.</p>
                    @if ($dictamenForm->tipoVigencia === 'fecha_expresa')
                        <x-ui.field id="dictamenForm.fechaVencimientoDocumento" label="Fecha de vencimiento" hint="Tal como aparece en el dictamen."><x-ui.input type="date" wire:model.blur="dictamenForm.fechaVencimientoDocumento" /></x-ui.field>
                    @endif
                    <x-ui.field id="dictamenForm.condicionantes" label="Condicionantes" hint="Restricciones que aparezcan en el documento: actividades permitidas, superficie, estacionamiento, etc." optional><x-ui.textarea wire:model.blur="dictamenForm.condicionantes" maxlength="1000" /></x-ui.field>
                </x-tramite.documento-row>
            @elseif ($clave === 'visto_bueno_proteccion_civil')
                <x-tramite.documento-row wire:key="doc-{{ $clave }}" :clave="$fila['clave']" :titulo="$fila['titulo']" :escuela-id="$fila['escuelaId']" :capturado="$fila['capturado']" :editable="$fila['editable']" :vencido="$fila['vencido']" accion="guardarProteccionCivil">
                    @isset($avisos[$clave])
                        <x-slot:extra>
                            <p class="flex items-start gap-xxs text-body-sm text-body">
                                <x-ui.icon nombre="exclamation-circle" class="mt-px h-5 w-5 shrink-0" />
                                {{ $avisos[$clave] }}
                            </p>
                        </x-slot:extra>
                    @endisset
                    <p class="text-body-sm text-muted">Capture lo que dice el documento. El sistema no valora si es suficiente; SEDEQ lo revisa.</p>

                    <h3 class="text-body-md font-semibold text-ink">Identificación del documento</h3>
                    <x-ui.field id="proteccionCivilForm.tipoInstrumento" label="Tipo de documento">
                        <x-ui.select wire:model.live="proteccionCivilForm.tipoInstrumento">
                            <option value="">Seleccione…</option>
                            <option value="visto_bueno">Visto Bueno</option>
                            <option value="dictamen">Dictamen</option>
                            <option value="otro">Otro</option>
                        </x-ui.select>
                    </x-ui.field>
                    @if ($proteccionCivilForm->tipoInstrumento === 'otro')
                        <x-ui.field id="proteccionCivilForm.tipoInstrumentoOtro" label="¿Cuál documento es?" hint="Tal como se llama en el documento."><x-ui.input maxlength="150" wire:model.blur="proteccionCivilForm.tipoInstrumentoOtro" /></x-ui.field>
                    @endif
                    <x-ui.field id="proteccionCivilForm.folioDocumento" label="Folio del documento" hint="Solo si el documento lo trae." optional><x-ui.input maxlength="50" wire:model.blur="proteccionCivilForm.folioDocumento" /></x-ui.field>
                    <x-ui.field id="proteccionCivilForm.fechaEmision" label="Fecha de emisión"><x-ui.input type="date" wire:model.blur="proteccionCivilForm.fechaEmision" /></x-ui.field>

                    <h3 class="text-body-md font-semibold text-ink">Autoridad emisora</h3>
                    <x-ui.field id="proteccionCivilForm.jurisdiccionEmisora" label="¿Quién expide el documento?">
                        <x-ui.select wire:model.live="proteccionCivilForm.jurisdiccionEmisora">
                            <option value="">Seleccione…</option>
                            <option value="municipio_queretaro">Coordinación Municipal de Protección Civil del Municipio de Querétaro</option>
                            <option value="otra">Otra autoridad (otro municipio, estatal u otra)</option>
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field id="proteccionCivilForm.autoridadEmisora" label="Autoridad emisora" hint="Tal como aparece en el documento."><x-ui.input maxlength="200" wire:model.blur="proteccionCivilForm.autoridadEmisora" /></x-ui.field>
                    @if ($proteccionCivilForm->pideEjercicio())
                        <x-ui.field id="proteccionCivilForm.ejercicio" label="Año del Visto Bueno" hint="El año que indica el documento (por ejemplo, «Visto Bueno 2026»). Con él se determina que vale hasta el 31 de diciembre de ese año (Reglamento de Protección Civil del Municipio de Querétaro, art. 46). Si no lo indica, déjelo vacío." optional><x-ui.input inputmode="numeric" maxlength="4" wire:model.blur="proteccionCivilForm.ejercicio" /></x-ui.field>
                    @endif

                    <h3 class="text-body-md font-semibold text-ink">Clasificación de riesgo</h3>
                    <x-ui.field id="proteccionCivilForm.clasificacionRiesgo" label="Riesgo que indica el documento" hint="Tal como aparece en el documento; el sistema no la calcula.">
                        <x-ui.select wire:model.live="proteccionCivilForm.clasificacionRiesgo">
                            <option value="">Seleccione…</option>
                            <option value="alto">Alto</option>
                            <option value="medio">Medio</option>
                            <option value="bajo">Bajo</option>
                            <option value="no_indicado">El documento no lo indica</option>
                        </x-ui.select>
                    </x-ui.field>

                    <h3 class="text-body-md font-semibold text-ink">Vigencia y condicionantes</h3>
                    <x-ui.radio-group
                        id="proteccionCivilForm.tipoVigencia"
                        legend="¿El documento indica una fecha de vencimiento?"
                        wire:model.live="proteccionCivilForm.tipoVigencia"
                        :opciones="['fecha_expresa' => 'Sí, el documento indica una fecha de vencimiento', 'sin_fecha_expresa' => 'No, el documento no indica fecha de vencimiento']"
                    />
                    @if ($proteccionCivilForm->tipoVigencia === 'fecha_expresa')
                        <x-ui.field id="proteccionCivilForm.fechaVencimientoDocumento" label="Fecha de vencimiento" hint="Tal como aparece en el documento."><x-ui.input type="date" wire:model.blur="proteccionCivilForm.fechaVencimientoDocumento" /></x-ui.field>
                    @endif
                    <x-ui.field id="proteccionCivilForm.condicionantes" label="Condicionantes u observaciones" hint="Restricciones o medidas que aparezcan en el documento." optional><x-ui.textarea wire:model.blur="proteccionCivilForm.condicionantes" maxlength="1000" /></x-ui.field>
                </x-tramite.documento-row>
            @elseif ($clave === 'constancia_seguridad_estructural')
                <x-tramite.documento-row wire:key="doc-{{ $clave }}" :clave="$fila['clave']" :titulo="$fila['titulo']" :escuela-id="$fila['escuelaId']" :capturado="$fila['capturado']" :editable="$fila['editable']" :vencido="$fila['vencido']" accion="guardarConstancia">
                    @isset($avisos[$clave])
                        <x-slot:extra>
                            <p class="flex items-start gap-xxs text-body-sm text-body">
                                <x-ui.icon nombre="exclamation-circle" class="mt-px h-5 w-5 shrink-0" />
                                {{ $avisos[$clave] }}
                            </p>
                        </x-slot:extra>
                    @endisset
                    <h3 class="text-body-md font-semibold text-ink">Datos de la constancia</h3>
                    <x-ui.field id="constanciaForm.folioConstancia" label="Folio o número de la constancia" hint="Solo si el documento lo trae." optional><x-ui.input maxlength="50" wire:model.blur="constanciaForm.folioConstancia" /></x-ui.field>
                    <x-ui.field id="constanciaForm.fechaEmision" label="Fecha de emisión"><x-ui.input type="date" wire:model.blur="constanciaForm.fechaEmision" /></x-ui.field>
                    <x-ui.radio-group
                        id="constanciaForm.tipoVigencia"
                        legend="¿El documento indica una fecha de vencimiento?"
                        wire:model.live="constanciaForm.tipoVigencia"
                        :opciones="['fecha_expresa' => 'Sí, el documento indica una fecha de vencimiento', 'sin_fecha_expresa' => 'No, el documento no indica fecha de vencimiento']"
                    />
                    <p class="text-body-sm text-muted">Registre solo lo que dice el documento. SEDEQ revisa su vigencia.</p>
                    @if ($constanciaForm->tipoVigencia === 'fecha_expresa')
                        <x-ui.field id="constanciaForm.vigenciaConstancia" label="Fecha de vencimiento" hint="Tal como aparece en la constancia."><x-ui.input type="date" wire:model.blur="constanciaForm.vigenciaConstancia" /></x-ui.field>
                    @endif
                    <h3 class="text-body-md font-semibold text-ink">Datos del profesional que firma</h3>
                    <x-ui.field id="constanciaForm.peritoNombre" label="Nombre del profesional"><x-ui.input maxlength="200" wire:model.blur="constanciaForm.peritoNombre" /></x-ui.field>
                    <x-ui.radio-group
                        id="constanciaForm.calidadProfesional"
                        legend="Calidad en que firma"
                        wire:model.live="constanciaForm.calidadProfesional"
                        :opciones="['dro' => 'Director Responsable de Obra (DRO)', 'corresponsable_seguridad_estructural' => 'Corresponsable en Seguridad Estructural', 'otra' => 'Otra calidad indicada en el documento']"
                    />
                    @if ($constanciaForm->calidadProfesional === 'otra')
                        <x-ui.field id="constanciaForm.calidadProfesionalOtra" label="¿Cuál calidad indica el documento?"><x-ui.input maxlength="150" wire:model.blur="constanciaForm.calidadProfesionalOtra" /></x-ui.field>
                    @endif
                    <x-ui.field id="constanciaForm.peritoCedulaProfesional" label="Cédula profesional" optional><x-ui.input maxlength="50" wire:model.blur="constanciaForm.peritoCedulaProfesional" /></x-ui.field>
                    <x-ui.field id="constanciaForm.peritoRegistroDro" label="Número de registro del profesional" hint="El que indica el documento, sea DRO o Corresponsable."><x-ui.input maxlength="50" wire:model.blur="constanciaForm.peritoRegistroDro" /></x-ui.field>
                    <x-ui.field id="constanciaForm.peritoRegistroAutoridad" label="Autoridad del registro" optional><x-ui.input maxlength="150" wire:model.blur="constanciaForm.peritoRegistroAutoridad" /></x-ui.field>
                    <x-ui.field id="constanciaForm.peritoRegistroFecha" label="Fecha de registro o refrendo" hint="Solo si el documento la indica." optional><x-ui.input type="date" wire:model.blur="constanciaForm.peritoRegistroFecha" /></x-ui.field>
                    <x-ui.field id="constanciaForm.peritoRegistroVencimiento" label="Vencimiento del registro" hint="Solo si el documento lo indica." optional><x-ui.input type="date" wire:model.blur="constanciaForm.peritoRegistroVencimiento" /></x-ui.field>
                </x-tramite.documento-row>
            @else
                {{-- WS-5a M1: toda clave aplicable sin bloque propio se sube como documento simple. --}}
                <x-tramite.documento-row wire:key="doc-{{ $clave }}" :clave="$fila['clave']" :titulo="$fila['titulo']" :escuela-id="$fila['escuelaId']" :capturado="$fila['capturado']" :editable="$fila['editable']" :vencido="$fila['vencido']" accion="guardarDocumentoSimple('{{ $clave }}')" />
            @endif
        @endforeach
    </ul>
    </x-ui.stepper-panel>
    @endforeach
    </x-ui.stepper>

    <div class="mt-xl border-t border-hairline pt-lg">
        <a href="{{ route('tramite.resumen', ['escuela' => $escuela->id]) }}" class="inline-flex min-h-11 items-center font-semibold text-primary">Volver al resumen</a>
    </div>
</div>
