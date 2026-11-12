<?php

namespace App\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosFormacionContinuaProyecto;
use App\Application\Tramite\CompuertaSubPaso3;
use App\Application\Tramite\TramiteEditable;
use App\Infrastructure\Documentos\AlmacenDocumentos;
use App\Models\EscuelaNivel;
use App\Models\ProyectoEducativoFormacionPolitica;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Sección 11 del Proyecto Educativo (media superior), bloque "Formación Docente Continua": guarda la política, el PDF de
 * planeación (1 a 2 páginas) y la lista de acciones, todo o nada: se valida todo antes de escribir nada. Un archivo en null
 * conserva el ya guardado; uno nuevo lo reemplaza y el anterior se borra solo cuando el guardado se confirma. Guardar
 * reemplaza la lista de acciones. No toca el bloque inicial ni marca el paso proyecto_educativo como completado.
 */
class GuardarFormacionContinuaProyecto
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
    public function ejecutar(int $escuelaNivelId, DatosFormacionContinuaProyecto $datos): void
    {
        $escuelaNivel = EscuelaNivel::findOrFail($escuelaNivelId);
        // Fuera de media superior el paso no existe en el catálogo del nivel y la compuerta lanza InvalidArgumentException.
        $this->compuerta->verificar($escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);

        $clave = ['escuela_nivel_id' => $escuelaNivelId, 'tipo' => ReglasFormacionProyecto::TIPO_CONTINUA];
        $limpios = ReglasFormacionProyecto::normalizar($datos->politica_continua, $datos->acciones_continua);
        $errores = ReglasFormacionProyecto::errores($limpios, 'politica_continua', 'acciones_continua');

        $paginas = null;
        if ($datos->planeacion_continua === null) {
            if (ProyectoEducativoFormacionPolitica::where($clave)->value('planeacion_path') === null) {
                $errores['planeacion_continua'] = 'Este campo es obligatorio.';
            }
        } else {
            try {
                $paginas = $this->revisor->revisar($datos->planeacion_continua);
            } catch (RuntimeException $e) {
                $errores['planeacion_continua'] = $e->getMessage();
            }
        }
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        // Ruta nueva y única: nunca pisa el archivo anterior, así que un fallo antes de confirmar no destruye lo guardado.
        $nueva = $paginas === null ? null : $this->almacen->guardar('proyecto_educativo', $escuelaNivelId, 'formacion_continua_planeacion', $datos->planeacion_continua);

        // Se pone en true solo dentro del afterCommit (ver RegistrarDocumento): ata la bandera al commit real.
        $confirmado = false;
        try {
            DB::transaction(function () use ($escuelaNivel, $datos, $limpios, $paginas, $nueva, &$confirmado) {
                $bloqueado = EscuelaNivel::lockForUpdate()->findOrFail($escuelaNivel->id);
                $this->compuerta->verificar($bloqueado, SeccionesProyectoEducativo::CLAVE_PASO);
                $this->tramiteEditable->asegurarEscuela((int) $bloqueado->escuela_id, SeccionesProyectoEducativo::CLAVE_PASO, $bloqueado->id);

                $clave = ['escuela_nivel_id' => $bloqueado->id, 'tipo' => ReglasFormacionProyecto::TIPO_CONTINUA];
                $fila = ProyectoEducativoFormacionPolitica::where($clave)->lockForUpdate()->first();

                $atributos = ['politica' => $limpios['politica']];
                $anterior = null;
                if ($nueva === null) {
                    // Sin archivo nuevo debe haber uno guardado (pudo desaparecer desde la revisión de arriba).
                    if ($fila?->planeacion_path === null) {
                        throw new DatosInvalidos(['planeacion_continua' => 'Este campo es obligatorio.']);
                    }
                } else {
                    $anterior = $fila?->planeacion_path;
                    $atributos['planeacion_path'] = $nueva;
                    $atributos['planeacion_nombre'] = mb_substr((string) $datos->planeacion_continua?->getClientOriginalName(), 0, 255);
                    $atributos['planeacion_paginas'] = $paginas;
                }

                ProyectoEducativoFormacionPolitica::updateOrCreate($clave, $atributos);
                EscribirAccionesFormacion::reemplazar($clave, $limpios['acciones']);

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
