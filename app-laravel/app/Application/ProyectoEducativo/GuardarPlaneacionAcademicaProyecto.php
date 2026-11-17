<?php

namespace App\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosPlaneacionAcademicaProyecto;
use App\Application\Tramite\CompuertaSubPaso3;
use App\Application\Tramite\TramiteEditable;
use App\Infrastructure\Documentos\AlmacenDocumentos;
use App\Models\EscuelaNivel;
use App\Models\ProyectoEducativoPlaneacionAcademica;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Sección 17 del Proyecto Educativo (media superior): guarda el PDF con los tres ejemplos de planeación académica y su
 * descripción (ambos requeridos), todo o nada: se valida todo antes de escribir nada. Solo se acepta un PDF de verdad (se
 * lee el contenido, no el nombre ni el tipo que declara el navegador; hasta 10 MB, sin tope de páginas). Un archivo en null
 * conserva el ya guardado; uno nuevo lo reemplaza y el anterior se borra solo cuando el guardado se confirma. No marca el
 * paso proyecto_educativo como completado.
 */
class GuardarPlaneacionAcademicaProyecto
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
    public function ejecutar(int $escuelaNivelId, DatosPlaneacionAcademicaProyecto $datos): void
    {
        $escuelaNivel = EscuelaNivel::findOrFail($escuelaNivelId);
        // Fuera de media superior el paso no existe en el catálogo del nivel y la compuerta lanza InvalidArgumentException.
        $this->compuerta->verificar($escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);

        $descripcion = ($recortada = trim((string) $datos->descripcion_planeacion)) === '' ? null : $recortada;
        $guardado = ProyectoEducativoPlaneacionAcademica::where('escuela_nivel_id', $escuelaNivelId)->first();

        $errores = ReglasPlaneacionAcademicaProyecto::erroresDescripcion($descripcion);
        $paginas = null;
        if ($datos->archivo_planeacion === null) {
            if ($guardado === null) {
                $errores['archivo_planeacion'] = 'Este campo es obligatorio.';
            }
        } else {
            try {
                // Sin tope de páginas: tres ejemplos de planeación pueden ocupar varias hojas.
                $paginas = $this->revisor->revisar($datos->archivo_planeacion, null);
            } catch (RuntimeException $e) {
                $errores['archivo_planeacion'] = $e->getMessage();
            }
        }
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        // Ruta nueva y única: nunca pisa el archivo anterior, así que un fallo antes de confirmar no destruye lo guardado.
        $nueva = $paginas === null ? null : $this->almacen->guardar('proyecto_educativo', $escuelaNivelId, 'planeacion_academica', $datos->archivo_planeacion);

        // Se pone en true solo dentro del afterCommit (ver RegistrarDocumento): ata la bandera al commit real.
        $confirmado = false;
        try {
            DB::transaction(function () use ($escuelaNivel, $descripcion, $datos, $paginas, $nueva, &$confirmado) {
                $bloqueado = EscuelaNivel::lockForUpdate()->findOrFail($escuelaNivel->id);
                $this->compuerta->verificar($bloqueado, SeccionesProyectoEducativo::CLAVE_PASO);
                $this->tramiteEditable->asegurarEscuela((int) $bloqueado->escuela_id, SeccionesProyectoEducativo::CLAVE_PASO, $bloqueado->id);

                $fila = ProyectoEducativoPlaneacionAcademica::where('escuela_nivel_id', $bloqueado->id)->lockForUpdate()->first();

                $atributos = ['descripcion' => $descripcion];
                $anterior = null;
                if ($nueva === null) {
                    // Sin archivo nuevo debe haber uno guardado (pudo desaparecer desde la revisión de arriba).
                    if ($fila === null) {
                        throw new DatosInvalidos(['archivo_planeacion' => 'Este campo es obligatorio.']);
                    }
                } else {
                    $anterior = $fila?->archivo_path;
                    $atributos['archivo_path'] = $nueva;
                    $atributos['archivo_nombre'] = mb_substr($datos->archivo_planeacion->getClientOriginalName(), 0, 255);
                    $atributos['archivo_paginas'] = $paginas;
                }

                ProyectoEducativoPlaneacionAcademica::updateOrCreate(['escuela_nivel_id' => $bloqueado->id], $atributos);

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
