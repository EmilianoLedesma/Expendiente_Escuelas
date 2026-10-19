<?php

namespace App\Application\Documentos;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ResponsableLegal\TipoPersonaDeEscuela;
use App\Application\Tramite\EstadoPaso2;
use App\Application\Tramite\EstadoPaso24;
use App\Application\Tramite\TramiteEditable;
use App\Domain\Captura\Formatos;
use App\Infrastructure\Documentos\AlmacenDocumentos;
use App\Models\AcreditacionOcupacionLegal;
use App\Models\CertificadoNumeroOficial;
use App\Models\ConstanciaCurp;
use App\Models\ConstanciaSeguridadEstructural;
use App\Models\ConstanciaSituacionFiscal;
use App\Models\CredencialIne;
use App\Models\DictamenUsoSuelo;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoEscuelaNivel;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\ReciboPagoDerechos;
use App\Models\RelacionAcervoBibliografico;
use App\Models\TipoDocumento;
use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Caso de uso genérico para los documentos aplicables de Paso 2.2 y, desde
 * WS-5b, de Paso 2.4 (ámbito escuela_nivel) — despacha por
 * tipos_documentos.ambito/clave, no tiene una clase por documento (mismo
 * patrón que RegistrarResponsableLegal despachando por tipo_persona).
 */
class RegistrarDocumento
{
    public function __construct(
        private readonly ?AlmacenDocumentos $almacen = null,
        private readonly ?DocumentosCompletos $documentosCompletos = null,
        private readonly ?EstadoPaso2 $estadoPaso2 = null,
        private readonly ?TipoPersonaDeEscuela $tipoPersonaDeEscuela = null,
        private readonly ?DocumentosNivelCompletos $documentosNivelCompletos = null,
        private readonly ?EstadoPaso24 $estadoPaso24 = null,
        private readonly ?TramiteEditable $tramiteEditable = null,
    ) {}

    /**
     * @param  int|null  $escuelaNivelId  nivel dueño del documento en Paso 2.4 (ámbito escuela_nivel); null en Paso 2.2.
     *
     * @throws PrecondicionIncumplida si no hay responsable legal; en Paso 2.4 además si Paso 2 no está completo o si se sube el Formato antes de turno y tipo de alumnado; si el trámite (o, para documentos del plantel, cualquier trámite del plantel) ya se envió (WS-7a).
     */
    public function ejecutar(int $escuelaId, string $tipoDocumentoClave, UploadedFile $archivo, DatosDocumento $datos, ?int $escuelaNivelId = null): void
    {
        $tipo = TipoDocumento::where('clave', $tipoDocumentoClave)->first();

        if ($tipo === null) {
            throw new InvalidArgumentException("tipo_documento desconocido: {$tipoDocumentoClave}");
        }

        // WS-2.4b: sin responsable legal no hay tipo_persona con qué verificar
        // aplicabilidad — antes esto se toleraba (bypass conocido), ahora se
        // rechaza antes de cualquier escritura.
        $estadoPaso2 = $this->estadoPaso2 ?? app(EstadoPaso2::class);
        if (! $estadoPaso2->responsableCapturado($escuelaId)) {
            throw new PrecondicionIncumplida(EstadoPaso2::RESPONSABLE, 'Captura el responsable legal (Paso 2) antes de subir documentos.');
        }

        // Paso 2.2 solo acepta claves plantel/escuela y Paso 2.4 solo
        // escuela_nivel, así que tras esto el ámbito y $escuelaNivelId concuerdan.
        if ($escuelaNivelId === null) {
            $tipoPersonaDeEscuela = $this->tipoPersonaDeEscuela ?? app(TipoPersonaDeEscuela::class);
            $tipoPersona = $tipoPersonaDeEscuela->ejecutar($escuelaId);
            $documentosCompletos = $this->documentosCompletos ?? app(DocumentosCompletos::class);

            if (! in_array($tipoDocumentoClave, $documentosCompletos->clavesAplicables($tipoPersona), true)) {
                // Minor 5 — 'clave' no mapea a ningún campo Livewire; "archivos.{clave}"
                // es la misma ruta que usa el rechazo de PDF más abajo.
                throw new DatosInvalidos(["archivos.{$tipoDocumentoClave}" => "El documento \"{$tipoDocumentoClave}\" no aplica al tipo de persona de esta escuela."]);
            }
            $datosNivelVerificados = null;
        } else {
            $datosNivelVerificados = $this->verificarNivel($escuelaId, $escuelaNivelId, $tipoDocumentoClave, $estadoPaso2);
        }

        if ($archivo->getMimeType() !== 'application/pdf') {
            throw new DatosInvalidos(["archivos.{$tipoDocumentoClave}" => 'El archivo debe ser un PDF.']);
        }

        if ($tipoDocumentoClave === 'recibo_pago_derechos') {
            $this->validarRecibo($datos);
        }

        $this->validarDatosTipados($tipoDocumentoClave, $datos);

        if ($tipoDocumentoClave === 'escritura_inmueble' && $datos->tipoAcreditacion !== null) {
            $this->validarAcreditacion($datos);
        }

        if ($datos->acervoTitulos !== null && $datos->acervoTitulos < 0) {
            throw new DatosInvalidos(['acervo.titulos' => 'El número de títulos no puede ser negativo.']);
        }

        $almacen = $this->almacen ?? app(AlmacenDocumentos::class);
        $escuela = Escuela::findOrFail($escuelaId);

        [$modelo, $columna, $ownerId] = match ($tipo->ambito) {
            'plantel' => [new DocumentoPlantel, 'plantel_id', $escuela->plantel_id],
            'escuela' => [new DocumentoEscuela, 'escuela_id', $escuelaId],
            'escuela_nivel' => [new DocumentoEscuelaNivel, 'escuela_nivel_id', (int) $escuelaNivelId],
            default => throw new InvalidArgumentException("ámbito desconocido: {$tipo->ambito}"),
        };

        $rutaAnterior = $modelo->newQuery()->where($columna, $ownerId)->where('tipo_documento_id', $tipo->id)->value('archivo_path');

        // Ruta nueva y única (WS-2.3): nunca pisa el archivo anterior, así
        // que un fallo en la transacción no puede dejar bytes nuevos bajo
        // metadatos viejos ni destruir el archivo previo antes de confirmar.
        $ruta = $almacen->guardar($tipo->ambito, $ownerId, $tipoDocumentoClave, $archivo);

        // La constancia de seguridad estructural declara su propia vigencia (no hay
        // vigencia_max_dias): esa fecha alimenta el control de vencimiento genérico.
        $fechaVigencia = match (true) {
            $datos->vigenciaConstancia !== null => $datos->vigenciaConstancia,
            $datos->fechaEmision !== null && $tipo->vigencia_max_dias !== null => date('Y-m-d', strtotime("{$datos->fechaEmision} +{$tipo->vigencia_max_dias} days")),
            default => null,
        };

        // WS-2 item 2 — confirmado solo se pone en true DENTRO del callback
        // afterCommit, no como última instrucción del closure de
        // DB::transaction(): ese closure termina, y luego el commit real
        // ocurre, ANTES de que DB::transaction() regrese a esta función — así
        // que "última instrucción del closure" y "después de que
        // DB::transaction() regrese" describen el mismo instante que el
        // afterCommit ya usa para el borrado diferido (ver comentario más
        // abajo), y son insuficientes por la misma razón: si algo lanzara
        // entre el commit real y el retorno de DB::transaction(), ninguna de
        // esas dos posiciones se alcanzaría. Poner la bandera dentro del
        // propio callback afterCommit la ata al mismo evento que ya dispara
        // el borrado, así que es información confiable sin importar si este
        // DB::transaction() es la transacción real o solo un savepoint
        // anidado de un caller externo.
        $confirmado = false;
        $tramiteEditable = $this->tramiteEditable ?? app(TramiteEditable::class);

        try {
            DB::transaction(function () use ($tipo, $modelo, $columna, $ownerId, $ruta, $rutaAnterior, $datos, $fechaVigencia, $almacen, $datosNivelVerificados, $escuelaId, $tramiteEditable, &$confirmado) {
                $atributos = [
                    'archivo_path' => $ruta,
                    'fecha_emision' => $datos->fechaEmision,
                    'fecha_vigencia' => $fechaVigencia,
                    'estado_validacion' => 'pendiente',
                ];

                // WS-5b: documentos_escuela_nivel no tiene UNIQUE y las lecturas
                // toman la última fila; el lock del nivel (el mismo que toma
                // RegistrarDatosNivel) serializa dos subidas concurrentes para
                // que updateOrCreate nunca inserte una segunda fila.
                if ($tipo->ambito === 'escuela_nivel') {
                    $escuelaNivel = EscuelaNivel::lockForUpdate()->findOrFail($ownerId);

                    // Revisión Task 4 (I1): RegistrarDatosNivel pudo cambiar
                    // turno/tipo de alumnado (y descartar el Formato) entre
                    // verificarNivel() y este lock; un Formato subido contra
                    // datos que ya no son los verificados sería obsoleto.
                    $datosActuales = [$escuelaNivel->turno, $escuelaNivel->tipo_alumnado];
                    if ($tipo->clave === 'formato_solicitud' && (in_array(null, $datosActuales, true) || $datosActuales !== $datosNivelVerificados)) {
                        throw new PrecondicionIncumplida(EstadoPaso24::DATOS, 'El turno o el tipo de alumnado cambiaron; vuelve a descargar y firmar el Formato de Solicitud.');
                    }
                }

                // WS-7a: después del bloqueo del nivel de arriba (orden escuela_niveles →
                // escuelas). Los documentos del plantel se comparten: se rechazan mientras
                // cualquier escuela del plantel esté enviada (spec §5.4).
                if ($tipo->ambito === 'plantel') {
                    $tramiteEditable->asegurarPlantel((int) $ownerId);
                } else {
                    $tramiteEditable->asegurarEscuela($escuelaId);
                }

                $documento = $modelo->newQuery()->updateOrCreate(
                    [$columna => $ownerId, 'tipo_documento_id' => $tipo->id],
                    $atributos,
                );

                if ($tipo->clave === 'dictamen_uso_suelo') {
                    DictamenUsoSuelo::updateOrCreate(
                        ['documento_plantel_id' => $documento->getKey()],
                        [
                            'autoridad_expide' => $datos->autoridadExpide,
                            'folio_dictamen' => $datos->folioDictamen,
                            'giro_autorizado' => $datos->giroAutorizado,
                        ],
                    );
                }

                if ($tipo->clave === 'constancia_seguridad_estructural') {
                    ConstanciaSeguridadEstructural::updateOrCreate(
                        ['documento_plantel_id' => $documento->getKey()],
                        [
                            'perito_nombre' => $datos->peritoNombre,
                            'perito_cedula_profesional' => $datos->peritoCedulaProfesional,
                            'perito_registro_dro' => $datos->peritoRegistroDro,
                            'perito_registro_autoridad' => $datos->peritoRegistroAutoridad,
                            'perito_registro_vigencia' => $datos->peritoRegistroVigencia,
                            'vigencia_constancia' => $datos->vigenciaConstancia,
                        ],
                    );
                }

                if ($tipo->clave === 'escritura_inmueble' && $datos->tipoAcreditacion !== null) {
                    AcreditacionOcupacionLegal::updateOrCreate(
                        ['documento_plantel_id' => $documento->getKey()],
                        $this->columnasAcreditacion($datos),
                    );
                }

                $this->registrarDatosTipados($tipo->clave, $documento->getKey(), $datos);

                if ($tipo->clave === 'recibo_pago_derechos') {
                    ReciboPagoDerechos::updateOrCreate(
                        ['documento_escuela_nivel_id' => $documento->getKey()],
                        [
                            'folio' => $datos->folio,
                            'monto' => $datos->monto,
                            'fecha_pago' => $datos->fechaPago,
                            'portal_referencia' => $datos->portalReferencia,
                        ],
                    );
                }

                // DB::afterCommit(), no borrado directo aquí: si quien llama a
                // ejecutar() envuelve esto en su propia transacción externa
                // (p. ej. un futuro caso de uso más grande, o un adaptador de
                // API), este DB::transaction() es solo un savepoint anidado —
                // el archivo anterior no debe borrarse hasta que la
                // transacción externa confirme de verdad, y si esa
                // transacción externa hace rollback, este callback nunca debe
                // ejecutarse. Verificado para este proyecto de Laravel:
                // Illuminate\Foundation\Testing\DatabaseTransactionsManager
                // (activo bajo RefreshDatabase) dispara los callbacks
                // afterCommit cuando el nivel de transacción baja a 1 (el
                // wrapper de test), no a 0, así que este callback sí se
                // observa en los tests sin necesitar un commit real a la BD.
                //
                // WS-2 item 2 — este callback corre DENTRO de commit(), ANTES
                // de que DB::transaction() regrese: si eliminar() lanzara
                // (disco caído, permisos, etc.) esa excepción escaparía de
                // DB::transaction() DESPUÉS del commit real y caería en el
                // catch(Throwable) de abajo, que borraría $ruta — el archivo
                // NUEVO al que la fila ya confirmada apunta. rescue(...,
                // report: true) reporta el fallo sin dejarlo propagar.
                DB::afterCommit(function () use (&$confirmado, $rutaAnterior, $ruta, $almacen) {
                    $confirmado = true;

                    if ($rutaAnterior !== null && $rutaAnterior !== $ruta) {
                        rescue(fn () => $almacen->eliminar($rutaAnterior), report: true);
                    }
                });

                // Minor 6 — si quien llama envuelve ejecutar() en su propia
                // transacción externa y esa transacción hace rollback DESPUÉS
                // de que este savepoint interno "confirmó", $ruta (el archivo
                // nuevo) ya está en disco pero ninguna fila lo referencia:
                // queda huérfano. afterRollBack solo dispara si la
                // transacción real termina en rollback, así que no interfiere
                // con el caso de commit real (afterCommit arriba).
                DB::afterRollBack(fn () => rescue(fn () => $almacen->eliminar($ruta), report: true));
            });
        } catch (Throwable $e) {
            if (! $confirmado) {
                // La fila previa nunca se tocó (updateOrCreate corrió dentro de
                // la transacción que acaba de hacer rollback): solo el archivo
                // nuevo, huérfano, necesita limpieza.
                // rescue: un fallo al limpiar no debe ocultar la excepción original.
                rescue(fn () => $almacen->eliminar($ruta), report: true);
            }

            throw $e;
        }
    }

    /**
     * ADR-007: typed data the documental validation engine reads. Written in
     * the same transaction as the upload; a re-upload overwrites it, and a
     * re-upload without data deletes it, so values captured from a replaced
     * file never outlive it. Required-ness is the form's job, as with the
     * constancia de seguridad estructural.
     */
    private function registrarDatosTipados(string $clave, int $documentoId, DatosDocumento $datos): void
    {
        [$modelo, $llave, $valores] = match ($clave) {
            'ine' => [CredencialIne::class, 'documento_escuela_id', ['nombre' => $datos->identidadNombre, 'curp' => $datos->identidadCurp]],
            'constancia_curp' => [ConstanciaCurp::class, 'documento_escuela_id', ['nombre' => $datos->identidadNombre, 'curp' => $datos->identidadCurp]],
            'constancia_situacion_fiscal' => [ConstanciaSituacionFiscal::class, 'documento_escuela_id', ['nombre_razon_social' => $datos->fiscalNombre, 'rfc' => $datos->fiscalRfc]],
            'certificado_numero_oficial' => [CertificadoNumeroOficial::class, 'documento_plantel_id', [
                'calle' => $datos->domicilioCalle,
                'numero_ext' => $datos->domicilioNumeroExt,
                'colonia' => $datos->domicilioColonia,
                'municipio' => $datos->domicilioMunicipio,
                'codigo_postal' => $datos->domicilioCodigoPostal,
            ]],
            'acervo_bibliografico_primaria', 'acervo_bibliografico_secundaria' => [RelacionAcervoBibliografico::class, 'documento_escuela_nivel_id', ['numero_titulos' => $datos->acervoTitulos]],
            default => [null, null, []],
        };

        if ($modelo === null) {
            return;
        }

        $requeridos = array_filter($valores, fn (string $columna) => $columna !== 'numero_ext', ARRAY_FILTER_USE_KEY);

        if (in_array(null, $requeridos, true)) {
            $modelo::where($llave, $documentoId)->delete();

            return;
        }

        $modelo::updateOrCreate([$llave => $documentoId], $valores);
    }

    /** WS-5b: el nivel debe ser de esta escuela, Paso 2 completo, la clave aplicable al nivel y, para el Formato, turno y tipo de alumnado ya capturados. */
    /** @return array{0: ?string, 1: ?string} turno y tipo de alumnado vistos al verificar (se vuelven a comparar bajo lock). */
    private function verificarNivel(int $escuelaId, int $escuelaNivelId, string $clave, EstadoPaso2 $estadoPaso2): array
    {
        $escuelaNivel = EscuelaNivel::where('id', $escuelaNivelId)->where('escuela_id', $escuelaId)->first();
        if ($escuelaNivel === null) {
            throw new DatosInvalidos(["archivos.{$clave}" => 'El nivel educativo no pertenece a esta escuela.']);
        }

        $etapaFaltante = $estadoPaso2->etapaFaltante($escuelaId);
        if ($etapaFaltante !== null) {
            throw new PrecondicionIncumplida($etapaFaltante, 'Completa el Paso 2 antes de subir los documentos del nivel.');
        }

        $documentosNivel = $this->documentosNivelCompletos ?? app(DocumentosNivelCompletos::class);
        if (! in_array($clave, $documentosNivel->clavesAplicables($escuelaNivelId), true)) {
            throw new DatosInvalidos(["archivos.{$clave}" => "El documento \"{$clave}\" no aplica a este nivel educativo."]);
        }

        $estadoPaso24 = $this->estadoPaso24 ?? app(EstadoPaso24::class);
        if ($clave === 'formato_solicitud' && ! $estadoPaso24->datosNivelCapturados($escuelaNivelId)) {
            throw new PrecondicionIncumplida(EstadoPaso24::DATOS, 'Guarda el turno y el tipo de alumnado antes de subir el Formato de Solicitud firmado.');
        }

        return [$escuelaNivel->turno, $escuelaNivel->tipo_alumnado];
    }

    /**
     * ADR-007: formato y largo de los datos tipados, con la ruta de campo del
     * formulario de Paso 2.2. La obligatoriedad sigue siendo del formulario
     * (registrarDatosTipados borra la fila si falta un dato); aquí solo se
     * impide guardar un valor mal formado o que no cabe en la columna.
     */
    private function validarDatosTipados(string $clave, DatosDocumento $datos): void
    {
        [$form, $campos] = match ($clave) {
            'ine', 'constancia_curp' => [$clave === 'ine' ? 'ineForm' : 'constanciaCurpForm', [
                'nombre' => [$datos->identidadNombre, 200, null],
                'curp' => [$datos->identidadCurp, 18, Formatos::esCurp(...)],
            ]],
            'constancia_situacion_fiscal' => ['situacionFiscalForm', [
                'nombre' => [$datos->fiscalNombre, 200, null],
                'rfc' => [$datos->fiscalRfc, 13, Formatos::esRfc(...)],
            ]],
            'certificado_numero_oficial' => ['numeroOficialForm', [
                'calle' => [$datos->domicilioCalle, 150, null],
                'numeroExt' => [$datos->domicilioNumeroExt, 20, null],
                'colonia' => [$datos->domicilioColonia, 150, null],
                'municipio' => [$datos->domicilioMunicipio, 150, null],
                'codigoPostal' => [$datos->domicilioCodigoPostal, 10, Formatos::esCodigoPostal(...)],
            ]],
            default => [null, []],
        };

        $errores = [];
        foreach ($campos as $campo => [$valor, $maximo, $formato]) {
            if ($valor === null) {
                continue;
            }
            if (mb_strlen($valor) > $maximo) {
                $errores["{$form}.{$campo}"] = "Admite hasta {$maximo} caracteres.";
            } elseif ($formato !== null && ! $formato($valor)) {
                $errores["{$form}.{$campo}"] = 'El dato no tiene un formato válido.';
            }
        }

        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }
    }

    private const ACREDITACION_ESCRITURA = [
        'numero_escritura', 'fecha_escritura', 'propietario_nombre', 'notario_nombre', 'notario_numero',
        'notario_localidad', 'folio_rpp', 'fecha_inscripcion_rpp',
    ];

    private const ACREDITACION_CONTRATO = [
        'arrendador_comodante', 'arrendatario_comodatario', 'fecha_contrato', 'fecha_inicio_vigencia',
        'vigencia_contrato', 'vigencia_indeterminada', 'uso_autorizado', 'ratificado_notario',
        'ratificacion_fecha', 'ratificacion_notario_nombre', 'ratificacion_notaria_numero',
        'arrendador_es_propietario', 'facultades_referencia',
    ];

    private const ACREDITACION_OTRO = [
        'otro_especifique', 'otro_referencia', 'otro_titular', 'otro_fecha_expedicion', 'otro_autoridad',
        'otro_derecho', 'uso_autorizado', 'fecha_inicio_vigencia', 'vigencia_contrato', 'folio_rpp',
        'fecha_inscripcion_rpp',
    ];

    /**
     * Invariantes de la acreditación de ocupación legal por variante de `tipo`.
     * Es solo completitud de datos: la suficiencia jurídica del instrumento la
     * decide la autoridad. La exigencia de ratificación notarial depende del
     * nivel educativo (PENDIENTE-dictamenes-por-nivel.md) y no se impone aquí.
     * Las rutas de error son las propiedades de AcreditacionOcupacionForm.
     */
    private function validarAcreditacion(DatosDocumento $datos): void
    {
        $variante = $datos->tipoAcreditacion;
        if (! in_array($variante, ['escritura_publica', 'arrendamiento', 'comodato', 'otro'], true)) {
            throw new DatosInvalidos(['acreditacionForm.tipo' => 'Selecciona un tipo de acreditación válido.']);
        }

        $errores = [];
        $texto = function (string $campo, ?string $valor, int $max, bool $requerido = true) use (&$errores): void {
            if ($valor === null || trim($valor) === '') {
                if ($requerido) {
                    $errores["acreditacionForm.{$campo}"] = 'Este dato es obligatorio.';
                }

                return;
            }
            if (mb_strlen($valor) > $max) {
                $errores["acreditacionForm.{$campo}"] = "Admite hasta {$max} caracteres.";
            }
        };
        $fecha = function (string $campo, ?string $valor, bool $requerido = true, bool $pasada = true) use (&$errores): void {
            if ($valor === null || $valor === '') {
                if ($requerido) {
                    $errores["acreditacionForm.{$campo}"] = 'Este dato es obligatorio.';
                }

                return;
            }
            $fechaValida = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
            if ($fechaValida === false || $fechaValida->format('Y-m-d') !== $valor) {
                $errores["acreditacionForm.{$campo}"] = 'La fecha no es válida.';
            } elseif ($pasada && $valor > now()->toDateString()) {
                $errores["acreditacionForm.{$campo}"] = 'La fecha no puede ser posterior a hoy.';
            }
        };
        $noAnterior = function (string $campo, ?string $fin, ?string $inicio, string $mensaje) use (&$errores): void {
            if ($fin !== null && $inicio !== null && ! isset($errores["acreditacionForm.{$campo}"]) && $fin < $inicio) {
                $errores["acreditacionForm.{$campo}"] = $mensaje;
            }
        };

        if ($variante === 'escritura_publica') {
            $texto('numeroEscritura', $datos->numeroEscritura, 50);
            $fecha('fechaEscritura', $datos->fechaEscritura);
            $texto('propietarioNombre', $datos->propietarioNombre, 200);
            $texto('notarioNombre', $datos->notarioNombre, 150);
            $texto('notarioNumero', $datos->notarioNumero, 20, false);
            $texto('notarioLocalidad', $datos->notarioLocalidad, 100, false);
            $texto('folioRpp', $datos->folioRpp, 50, false);
            $fecha('fechaInscripcionRpp', $datos->fechaInscripcionRpp, false);
        } elseif ($variante === 'otro') {
            $texto('otroEspecifique', $datos->otroEspecifique, 200);
            $texto('otroReferencia', $datos->otroReferencia, 100);
            $texto('otroTitular', $datos->otroTitular, 200);
            $fecha('otroFechaExpedicion', $datos->otroFechaExpedicion);
            $texto('otroAutoridad', $datos->otroAutoridad, 200);
            $texto('otroDerecho', $datos->otroDerecho, 200);
            $texto('usoAutorizado', $datos->usoAutorizado, 200);
            $fecha('fechaInicioVigencia', $datos->fechaInicioVigencia, false, false);
            $fecha('vigenciaContrato', $datos->vigenciaContrato, false, false);
            $noAnterior('vigenciaContrato', $datos->vigenciaContrato, $datos->fechaInicioVigencia, 'La vigencia no puede ser anterior a su inicio.');
            $texto('folioRpp', $datos->folioRpp, 50, false);
            $fecha('fechaInscripcionRpp', $datos->fechaInscripcionRpp, false);
        } else {
            $indeterminada = $datos->vigenciaIndeterminada === true;
            $texto('arrendadorComodante', $datos->arrendadorComodante, 200);
            $texto('arrendatarioComodatario', $datos->arrendatarioComodatario, 200);
            $fecha('fechaContrato', $datos->fechaContrato);
            $fecha('fechaInicioVigencia', $datos->fechaInicioVigencia, true, false);
            $texto('usoAutorizado', $datos->usoAutorizado, 200);
            if (! $indeterminada) {
                $fecha('vigenciaContrato', $datos->vigenciaContrato, true, false);
                $noAnterior('vigenciaContrato', $datos->vigenciaContrato, $datos->fechaInicioVigencia, 'La vigencia del contrato no puede ser anterior al inicio de la vigencia.');
                $noAnterior('vigenciaContrato', $datos->vigenciaContrato, $datos->fechaContrato, 'La vigencia del contrato no puede ser anterior a la fecha del contrato.');
            }
            if ($datos->ratificadoNotario === true) {
                $fecha('ratificacionFecha', $datos->ratificacionFecha, false);
                $texto('ratificacionNotarioNombre', $datos->ratificacionNotarioNombre, 150, false);
                $texto('ratificacionNotariaNumero', $datos->ratificacionNotariaNumero, 20, false);
            }
            if ($datos->arrendadorEsPropietario === false) {
                $texto('facultadesReferencia', $datos->facultadesReferencia, 200);
            }
        }

        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }
    }

    /**
     * Todas las columnas de la extensión: las que no pertenecen a la variante
     * elegida se escriben NULL, para que nada de otra figura sobreviva aunque el
     * caller las mande (el formulario Livewire tampoco es la única entrada).
     *
     * @return array<string, mixed>
     */
    private function columnasAcreditacion(DatosDocumento $datos): array
    {
        $ratificado = $datos->ratificadoNotario === true;
        $indeterminada = $datos->vigenciaIndeterminada === true;

        $valores = [
            'numero_escritura' => $datos->numeroEscritura,
            'fecha_escritura' => $datos->fechaEscritura,
            'propietario_nombre' => $datos->propietarioNombre,
            'notario_nombre' => $datos->notarioNombre,
            'notario_numero' => $datos->notarioNumero,
            'notario_localidad' => $datos->notarioLocalidad,
            'folio_rpp' => $datos->folioRpp,
            'fecha_inscripcion_rpp' => $datos->fechaInscripcionRpp,
            'arrendador_comodante' => $datos->arrendadorComodante,
            'arrendatario_comodatario' => $datos->arrendatarioComodatario,
            'fecha_contrato' => $datos->fechaContrato,
            'fecha_inicio_vigencia' => $datos->fechaInicioVigencia,
            'vigencia_contrato' => $indeterminada ? null : $datos->vigenciaContrato,
            'vigencia_indeterminada' => $datos->vigenciaIndeterminada ?? false,
            'uso_autorizado' => $datos->usoAutorizado,
            'ratificado_notario' => $ratificado,
            'ratificacion_fecha' => $ratificado ? $datos->ratificacionFecha : null,
            'ratificacion_notario_nombre' => $ratificado ? $datos->ratificacionNotarioNombre : null,
            'ratificacion_notaria_numero' => $ratificado ? $datos->ratificacionNotariaNumero : null,
            'arrendador_es_propietario' => $datos->arrendadorEsPropietario,
            'facultades_referencia' => $datos->arrendadorEsPropietario === false ? $datos->facultadesReferencia : null,
            'otro_especifique' => $datos->otroEspecifique,
            'otro_referencia' => $datos->otroReferencia,
            'otro_titular' => $datos->otroTitular,
            'otro_fecha_expedicion' => $datos->otroFechaExpedicion,
            'otro_autoridad' => $datos->otroAutoridad,
            'otro_derecho' => $datos->otroDerecho,
        ];

        $propias = match ($datos->tipoAcreditacion) {
            'escritura_publica' => self::ACREDITACION_ESCRITURA,
            'otro' => self::ACREDITACION_OTRO,
            default => self::ACREDITACION_CONTRATO,
        };

        $columnas = [];
        foreach ($valores as $columna => $valor) {
            $columnas[$columna] = in_array($columna, $propias, true) ? $valor : null;
        }

        return ['tipo' => $datos->tipoAcreditacion, 'observaciones' => $datos->observaciones, ...$columnas];
    }

    /** Invariantes de recibos_pago_derechos (DDL: folio VARCHAR(50), monto NUMERIC(10,2), portal_referencia VARCHAR(200)); el dominio del portal no se exige (spec §5.3). */
    private function validarRecibo(DatosDocumento $datos): void
    {
        $errores = [];

        if ($datos->folio === null || trim($datos->folio) === '' || mb_strlen($datos->folio) > 50) {
            $errores['recibo.folio'] = 'Captura el folio del recibo (máximo 50 caracteres).';
        }

        // NUMERIC(10,2): solo dígitos, hasta 8 enteros y 2 decimales (rechaza "1e3", "0.004").
        if ($datos->monto === null || preg_match('/^\d{1,8}(\.\d{1,2})?$/', $datos->monto) !== 1 || (float) $datos->monto <= 0) {
            $errores['recibo.monto'] = 'El monto pagado debe ser mayor a cero.';
        }

        $fecha = $datos->fechaPago === null ? false : DateTimeImmutable::createFromFormat('!Y-m-d', $datos->fechaPago);
        if ($fecha === false || $fecha->format('Y-m-d') !== $datos->fechaPago) {
            $errores['recibo.fechaPago'] = 'Captura la fecha de pago.';
        } elseif ($datos->fechaPago > now()->toDateString()) {
            $errores['recibo.fechaPago'] = 'La fecha de pago no puede ser posterior a hoy.';
        }

        if ($datos->portalReferencia !== null && mb_strlen($datos->portalReferencia) > 200) {
            $errores['recibo.portalReferencia'] = 'La referencia del portal admite máximo 200 caracteres.';
        }

        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }
    }
}
