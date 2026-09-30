<?php

namespace App\Application\Documentos;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ResponsableLegal\TipoPersonaDeEscuela;
use App\Application\Tramite\EstadoPaso2;
use App\Application\Tramite\EstadoPaso24;
use App\Infrastructure\Documentos\AlmacenDocumentos;
use App\Models\AcreditacionOcupacionLegal;
use App\Models\ConstanciaSeguridadEstructural;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoEscuelaNivel;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\ReciboPagoDerechos;
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
    ) {}

    /**
     * @param  int|null  $escuelaNivelId  nivel dueño del documento en Paso 2.4 (ámbito escuela_nivel); null en Paso 2.2.
     *
     * @throws PrecondicionIncumplida si no hay responsable legal; en Paso 2.4 además si Paso 2 no está completo o si se sube el Formato antes de turno y tipo de alumnado.
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
        } else {
            $this->verificarNivel($escuelaId, $escuelaNivelId, $tipoDocumentoClave, $estadoPaso2);
        }

        if ($archivo->getMimeType() !== 'application/pdf') {
            throw new DatosInvalidos(["archivos.{$tipoDocumentoClave}" => 'El archivo debe ser un PDF.']);
        }

        if ($tipoDocumentoClave === 'recibo_pago_derechos') {
            $this->validarRecibo($datos);
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

        $fechaVigencia = $datos->fechaEmision !== null && $tipo->vigencia_max_dias !== null
            ? date('Y-m-d', strtotime("{$datos->fechaEmision} +{$tipo->vigencia_max_dias} days"))
            : null;

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

        try {
            DB::transaction(function () use ($tipo, $modelo, $columna, $ownerId, $ruta, $rutaAnterior, $datos, $fechaVigencia, $almacen, &$confirmado) {
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
                    EscuelaNivel::lockForUpdate()->findOrFail($ownerId);
                }

                $documento = $modelo->newQuery()->updateOrCreate(
                    [$columna => $ownerId, 'tipo_documento_id' => $tipo->id],
                    $atributos,
                );

                if ($tipo->clave === 'constancia_seguridad_estructural') {
                    ConstanciaSeguridadEstructural::updateOrCreate(
                        ['documento_plantel_id' => $documento->getKey()],
                        [
                            'perito_nombre' => $datos->peritoNombre,
                            'perito_cedula_profesional' => $datos->peritoCedulaProfesional,
                            'perito_registro_dro' => $datos->peritoRegistroDro,
                            'perito_registro_autoridad' => $datos->peritoRegistroAutoridad,
                            'perito_registro_vigencia' => $datos->peritoRegistroVigencia,
                        ],
                    );
                }

                if ($tipo->clave === 'escritura_inmueble' && $datos->tipoAcreditacion !== null) {
                    AcreditacionOcupacionLegal::updateOrCreate(
                        ['documento_plantel_id' => $documento->getKey()],
                        [
                            'tipo' => $datos->tipoAcreditacion,
                            'numero_escritura' => $datos->numeroEscritura,
                            'notario_nombre' => $datos->notarioNombre,
                            'notario_numero' => $datos->notarioNumero,
                            'notario_localidad' => $datos->notarioLocalidad,
                            'folio_rpp' => $datos->folioRpp,
                            'fecha_inscripcion_rpp' => $datos->fechaInscripcionRpp,
                            'arrendador_comodante' => $datos->arrendadorComodante,
                            'arrendatario_comodatario' => $datos->arrendatarioComodatario,
                            'fecha_contrato' => $datos->fechaContrato,
                            'vigencia_contrato' => $datos->vigenciaContrato,
                            'uso_autorizado' => $datos->usoAutorizado,
                            'ratificado_notario' => $datos->ratificadoNotario,
                            'otro_especifique' => $datos->otroEspecifique,
                            'observaciones' => $datos->observaciones,
                        ],
                    );
                }

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

    /** WS-5b: el nivel debe ser de esta escuela, Paso 2 completo, la clave aplicable al nivel y, para el Formato, turno y tipo de alumnado ya capturados. */
    private function verificarNivel(int $escuelaId, int $escuelaNivelId, string $clave, EstadoPaso2 $estadoPaso2): void
    {
        if (! EscuelaNivel::where('id', $escuelaNivelId)->where('escuela_id', $escuelaId)->exists()) {
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
    }

    /** Invariantes de recibos_pago_derechos (DDL: folio VARCHAR(50), monto NUMERIC(10,2), portal_referencia VARCHAR(200)); el dominio del portal no se exige (spec §5.3). */
    private function validarRecibo(DatosDocumento $datos): void
    {
        $errores = [];

        if ($datos->folio === null || trim($datos->folio) === '' || mb_strlen($datos->folio) > 50) {
            $errores['recibo.folio'] = 'Captura el folio del recibo (máximo 50 caracteres).';
        }

        if ($datos->monto === null || ! is_numeric($datos->monto) || (float) $datos->monto <= 0 || (float) $datos->monto > 99999999.99) {
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
