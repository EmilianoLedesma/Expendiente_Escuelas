<?php

namespace App\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosProgramasEstudiosProyecto;
use App\Application\Tramite\CompuertaSubPaso3;
use App\Application\Tramite\TramiteEditable;
use App\Infrastructure\Documentos\AlmacenDocumentos;
use App\Models\EscuelaNivel;
use App\Models\ProyectoEducativoProgramasEstudios;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Sección 16 del Proyecto Educativo (media superior): guarda periodicidad, modalidad y si retoma los programas de la DGB
 * completos; solo si NO los retoma guarda además la justificación y el PDF de los programas adaptados (solo PDF de
 * verdad: se lee el contenido, sin tope de páginas, hasta 10 MB). Todo o nada: se valida todo antes de escribir nada.
 * Con "sí" la justificación y el archivo enviados se ignoran, y los ya guardados se borran (el PDF solo cuando el
 * guardado se confirma). Un archivo en null conserva el guardado; uno nuevo lo reemplaza. No marca el paso
 * proyecto_educativo como completado.
 */
class GuardarProgramasEstudiosProyecto
{
    public function __construct(
        private readonly CompuertaSubPaso3 $compuerta,
        private readonly TramiteEditable $tramiteEditable,
        private readonly AlmacenDocumentos $almacen,
        private readonly RevisarPdfProyecto $revisor,
    ) {}

    /**
     * @throws DatosInvalidos
     * @throws PrecondicionIncumplida
     */
    public function ejecutar(int $escuelaNivelId, DatosProgramasEstudiosProyecto $datos): void
    {
        $escuelaNivel = EscuelaNivel::findOrFail($escuelaNivelId);
        // Fuera de media superior el paso no existe en el catálogo del nivel y la compuerta lanza InvalidArgumentException.
        $this->compuerta->verificar($escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);

        $normalizar = fn (?string $valor) => ($valor = trim((string) $valor)) === '' ? null : $valor;
        $campos = [
            'periodicidad_ciclo' => $normalizar($datos->periodicidad_ciclo),
            'periodicidad_otra' => $normalizar($datos->periodicidad_otra),
            'modalidad_programas' => $normalizar($datos->modalidad_programas),
            'retoma_dgb' => $normalizar($datos->retoma_dgb),
            'justificacion_adaptacion' => $normalizar($datos->justificacion_adaptacion),
        ];
        $retoma = $campos['retoma_dgb'] === 'si';
        $guardado = ProyectoEducativoProgramasEstudios::where('escuela_nivel_id', $escuelaNivelId)->first();

        $errores = ReglasProgramasEstudiosProyecto::errores($campos);
        $paginas = null;
        if ($campos['retoma_dgb'] === 'no') {
            if ($datos->archivo_programas === null) {
                if ($guardado?->archivo_path === null) {
                    $errores['archivo_programas'] = 'Este campo es obligatorio.';
                }
            } else {
                try {
                    // Sin tope de páginas: los programas adaptados pueden ser un documento largo.
                    $paginas = $this->revisor->revisar($datos->archivo_programas, null);
                } catch (RuntimeException $e) {
                    $errores['archivo_programas'] = $e->getMessage();
                }
            }
        }
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        // Ruta nueva y única: nunca pisa el archivo anterior, así que un fallo antes de confirmar no destruye lo guardado.
        $nueva = $paginas === null ? null : $this->almacen->guardar('proyecto_educativo', $escuelaNivelId, 'programas_adaptados', $datos->archivo_programas);

        // Se pone en true solo dentro del afterCommit (ver RegistrarDocumento): ata la bandera al commit real.
        $confirmado = false;
        try {
            DB::transaction(function () use ($escuelaNivel, $campos, $retoma, $datos, $paginas, $nueva, &$confirmado) {
                $bloqueado = EscuelaNivel::lockForUpdate()->findOrFail($escuelaNivel->id);
                $this->compuerta->verificar($bloqueado, SeccionesProyectoEducativo::CLAVE_PASO);
                $this->tramiteEditable->asegurarEscuela((int) $bloqueado->escuela_id, SeccionesProyectoEducativo::CLAVE_PASO, $bloqueado->id);

                $fila = ProyectoEducativoProgramasEstudios::where('escuela_nivel_id', $bloqueado->id)->lockForUpdate()->first();

                $atributos = [
                    'periodicidad' => $campos['periodicidad_ciclo'],
                    'periodicidad_otra' => $campos['periodicidad_ciclo'] === 'otra' ? $campos['periodicidad_otra'] : null,
                    'modalidad' => $campos['modalidad_programas'],
                    'retoma_dgb' => $retoma,
                    'justificacion' => $retoma ? null : $campos['justificacion_adaptacion'],
                ];
                $anterior = null;
                if ($retoma) {
                    // Retomar los programas de la DGB completos deja sin efecto el PDF de programas adaptados guardado.
                    $anterior = $fila?->archivo_path;
                    $atributos += ['archivo_path' => null, 'archivo_nombre' => null, 'archivo_paginas' => null];
                } elseif ($nueva === null) {
                    // Sin archivo nuevo debe haber uno guardado (pudo desaparecer desde la revisión de arriba).
                    if ($fila?->archivo_path === null) {
                        throw new DatosInvalidos(['archivo_programas' => 'Este campo es obligatorio.']);
                    }
                } else {
                    $anterior = $fila?->archivo_path;
                    $atributos += [
                        'archivo_path' => $nueva,
                        'archivo_nombre' => mb_substr($datos->archivo_programas->getClientOriginalName(), 0, 255),
                        'archivo_paginas' => $paginas,
                    ];
                }

                ProyectoEducativoProgramasEstudios::updateOrCreate(['escuela_nivel_id' => $bloqueado->id], $atributos);

                // El archivo anterior se borra solo si el guardado se confirma de verdad (puede ser un savepoint de una
                // transacción exterior); el nuevo se borra si esa transacción termina en rollback. Un fallo de disco se
                // reporta, nunca revierte.
                DB::afterCommit(function () use (&$confirmado, $anterior) {
                    $confirmado = true;
                    if ($anterior !== null) {
                        rescue(fn () => $this->almacen->eliminar($anterior), report: true);
                    }
                });
                DB::afterRollBack(function () use ($nueva) {
                    if ($nueva !== null) {
                        rescue(fn () => $this->almacen->eliminar($nueva), report: true);
                    }
                });
            });
        } catch (Throwable $e) {
            if (! $confirmado && $nueva !== null) {
                rescue(fn () => $this->almacen->eliminar($nueva), report: true);
            }

            throw $e;
        }
    }
}
