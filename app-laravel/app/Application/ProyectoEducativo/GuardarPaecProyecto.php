<?php

namespace App\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosPaecProyecto;
use App\Application\Tramite\CompuertaSubPaso3;
use App\Application\Tramite\TramiteEditable;
use App\Infrastructure\Documentos\AlmacenDocumentos;
use App\Models\EscuelaNivel;
use App\Models\ProyectoEducativoPaec;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Sección 6 del Proyecto Educativo (media superior): guarda el diagnóstico del PAEC y los dos PDF (hoja de ruta del PEC
 * y planeación del abordaje, de 1 a 2 páginas), todo o nada: se valida todo antes de escribir nada. Un archivo en null
 * conserva el ya guardado; uno nuevo lo reemplaza y el anterior se borra solo cuando el guardado se confirma. No marca
 * el paso proyecto_educativo como completado.
 */
class GuardarPaecProyecto
{
    /** campo del DTO => clave del archivo en disco. Las columnas son "{campo}_path|_nombre|_paginas". */
    private const ARCHIVOS = ['hoja_ruta', 'planeacion'];

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
    public function ejecutar(int $escuelaNivelId, DatosPaecProyecto $datos): void
    {
        $escuelaNivel = EscuelaNivel::findOrFail($escuelaNivelId);
        // Fuera de media superior el paso no existe en el catálogo del nivel y la compuerta lanza InvalidArgumentException.
        $this->compuerta->verificar($escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);

        $diagnostico = ($recortado = trim((string) $datos->diagnostico)) === '' ? null : $recortado;
        $guardado = ProyectoEducativoPaec::where('escuela_nivel_id', $escuelaNivelId)->first();

        $errores = ReglasPaecProyecto::erroresDiagnostico($diagnostico);
        $paginas = [];
        foreach (self::ARCHIVOS as $campo) {
            $archivo = $datos->{$campo};
            if ($archivo === null) {
                if ($guardado?->getAttribute("{$campo}_path") === null) {
                    $errores[$campo] = 'Este campo es obligatorio.';
                }

                continue;
            }

            try {
                $paginas[$campo] = $this->revisor->revisar($archivo);
            } catch (RuntimeException $e) {
                $errores[$campo] = $e->getMessage();
            }
        }
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        // Rutas nuevas y únicas: nunca pisan el archivo anterior, así que un fallo antes de confirmar no destruye lo guardado.
        $nuevas = [];
        foreach (array_keys($paginas) as $campo) {
            $nuevas[$campo] = $this->almacen->guardar('proyecto_educativo', $escuelaNivelId, "paec_{$campo}", $datos->{$campo});
        }

        // Se pone en true solo dentro del afterCommit (ver RegistrarDocumento): ata la bandera al commit real.
        $confirmado = false;
        try {
            DB::transaction(function () use ($escuelaNivel, $diagnostico, $datos, $paginas, $nuevas, &$confirmado) {
                $bloqueado = EscuelaNivel::lockForUpdate()->findOrFail($escuelaNivel->id);
                $this->compuerta->verificar($bloqueado, SeccionesProyectoEducativo::CLAVE_PASO);
                $this->tramiteEditable->asegurarEscuela((int) $bloqueado->escuela_id, SeccionesProyectoEducativo::CLAVE_PASO, $bloqueado->id);

                $fila = ProyectoEducativoPaec::where('escuela_nivel_id', $bloqueado->id)->lockForUpdate()->first();

                $atributos = ['diagnostico' => $diagnostico];
                $anteriores = [];
                foreach (self::ARCHIVOS as $campo) {
                    if (! isset($nuevas[$campo])) {
                        // Sin archivo nuevo debe haber uno guardado (pudo desaparecer desde la revisión de arriba).
                        if ($fila?->getAttribute("{$campo}_path") === null) {
                            throw new DatosInvalidos([$campo => 'Este campo es obligatorio.']);
                        }

                        continue;
                    }

                    $anterior = $fila?->getAttribute("{$campo}_path");
                    if ($anterior !== null) {
                        $anteriores[] = $anterior;
                    }
                    $atributos["{$campo}_path"] = $nuevas[$campo];
                    $atributos["{$campo}_nombre"] = mb_substr($datos->{$campo}->getClientOriginalName(), 0, 255);
                    $atributos["{$campo}_paginas"] = $paginas[$campo];
                }

                ProyectoEducativoPaec::updateOrCreate(['escuela_nivel_id' => $bloqueado->id], $atributos);

                // Los archivos anteriores se borran solo si el guardado se confirma de verdad (puede ser un savepoint de una
                // transacción exterior); los nuevos se borran si esa transacción termina en rollback. Un fallo de disco se
                // reporta, nunca revierte.
                DB::afterCommit(function () use (&$confirmado, $anteriores) {
                    $confirmado = true;
                    foreach ($anteriores as $ruta) {
                        rescue(fn () => $this->almacen->eliminar($ruta), report: true);
                    }
                });
                DB::afterRollBack(function () use ($nuevas) {
                    foreach ($nuevas as $ruta) {
                        rescue(fn () => $this->almacen->eliminar($ruta), report: true);
                    }
                });
            });
        } catch (Throwable $e) {
            if (! $confirmado) {
                foreach ($nuevas as $ruta) {
                    rescue(fn () => $this->almacen->eliminar($ruta), report: true);
                }
            }

            throw $e;
        }
    }
}
