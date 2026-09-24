<?php

namespace App\Application\Documentos;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Infrastructure\Documentos\AlmacenDocumentos;
use App\Models\AcreditacionOcupacionLegal;
use App\Models\ConstanciaSeguridadEstructural;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\TipoDocumento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Caso de uso genérico para los 6 documentos de Paso 2.2 — despacha por
 * tipos_documentos.ambito/clave, no tiene una clase por documento (mismo
 * patrón que RegistrarResponsableLegal despachando por tipo_persona).
 */
class RegistrarDocumento
{
    public function __construct(private readonly ?AlmacenDocumentos $almacen = null) {}

    public function ejecutar(int $escuelaId, string $tipoDocumentoClave, UploadedFile $archivo, DatosDocumento $datos): void
    {
        $tipo = TipoDocumento::where('clave', $tipoDocumentoClave)->first();

        if ($tipo === null) {
            throw new InvalidArgumentException("tipo_documento desconocido: {$tipoDocumentoClave}");
        }

        $almacen = $this->almacen ?? app(AlmacenDocumentos::class);
        $escuela = Escuela::findOrFail($escuelaId);
        $ownerId = $tipo->ambito === 'plantel' ? $escuela->plantel_id : $escuelaId;

        $rutaAnterior = $tipo->ambito === 'plantel'
            ? DocumentoPlantel::where('plantel_id', $ownerId)->where('tipo_documento_id', $tipo->id)->value('archivo_path')
            : DocumentoEscuela::where('escuela_id', $ownerId)->where('tipo_documento_id', $tipo->id)->value('archivo_path');

        // Ruta nueva y única (WS-2.3): nunca pisa el archivo anterior, así
        // que un fallo en la transacción no puede dejar bytes nuevos bajo
        // metadatos viejos ni destruir el archivo previo antes de confirmar.
        $ruta = $almacen->guardar($tipo->ambito, $ownerId, $tipoDocumentoClave, $archivo);

        $fechaVigencia = $datos->fechaEmision !== null && $tipo->vigencia_max_dias !== null
            ? date('Y-m-d', strtotime("{$datos->fechaEmision} +{$tipo->vigencia_max_dias} days"))
            : null;

        try {
            DB::transaction(function () use ($tipo, $ownerId, $ruta, $datos, $fechaVigencia) {
                $atributos = [
                    'archivo_path' => $ruta,
                    'fecha_emision' => $datos->fechaEmision,
                    'fecha_vigencia' => $fechaVigencia,
                    'estado_validacion' => 'pendiente',
                ];

                if ($tipo->ambito === 'plantel') {
                    $documento = DocumentoPlantel::updateOrCreate(
                        ['plantel_id' => $ownerId, 'tipo_documento_id' => $tipo->id],
                        $atributos,
                    );
                } else {
                    $documento = DocumentoEscuela::updateOrCreate(
                        ['escuela_id' => $ownerId, 'tipo_documento_id' => $tipo->id],
                        $atributos,
                    );
                }

                if ($tipo->clave === 'constancia_seguridad_estructural') {
                    ConstanciaSeguridadEstructural::updateOrCreate(
                        ['documento_plantel_id' => $documento->id],
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
                        ['documento_plantel_id' => $documento->id],
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
            });
        } catch (Throwable $e) {
            // La fila previa nunca se tocó (updateOrCreate corrió dentro de la
            // transacción que acaba de hacer rollback): solo el archivo nuevo,
            // huérfano, necesita limpieza.
            $almacen->eliminar($ruta);

            throw $e;
        }

        // Fuera del try/catch: solo se llega aquí si la transacción confirmó.
        // RegistrarDocumento nunca corre anidado dentro de otra transacción
        // (único caller es Livewire), así que esto es equivalente a un
        // DB::afterCommit() real sin depender de que el nivel de transacción
        // llegue a 0 — cosa que no ocurre bajo RefreshDatabase, donde el
        // DB::transaction() de arriba es un savepoint anidado dentro de la
        // transacción de test y un callback afterCommit real nunca dispara.
        if ($rutaAnterior !== null && $rutaAnterior !== $ruta) {
            $almacen->eliminar($rutaAnterior);
        }
    }
}
