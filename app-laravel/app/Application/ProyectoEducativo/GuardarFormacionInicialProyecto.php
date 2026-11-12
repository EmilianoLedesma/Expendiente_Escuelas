<?php

namespace App\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosFormacionInicialProyecto;
use App\Application\Tramite\CompuertaSubPaso3;
use App\Application\Tramite\TramiteEditable;
use App\Models\EscuelaNivel;
use App\Models\ProyectoEducativoFormacionPolitica;
use Illuminate\Support\Facades\DB;

/**
 * Sección 11 del Proyecto Educativo (media superior), bloque "Formación Docente Inicial": guarda la política y la lista de
 * acciones, todo o nada: si alguna regla falla no se escribe nada y se devuelven todos los errores. Guardar reemplaza la lista
 * de acciones por la recibida (en ese orden). No marca el paso proyecto_educativo como completado.
 */
class GuardarFormacionInicialProyecto
{
    public function __construct(
        private readonly CompuertaSubPaso3 $compuerta,
        private readonly TramiteEditable $tramiteEditable,
    ) {}

    /**
     * @throws DatosInvalidos
     * @throws PrecondicionIncumplida
     */
    public function ejecutar(int $escuelaNivelId, DatosFormacionInicialProyecto $datos): void
    {
        $escuelaNivel = EscuelaNivel::findOrFail($escuelaNivelId);
        // Fuera de media superior el paso no existe en el catálogo del nivel y la compuerta lanza InvalidArgumentException.
        $this->compuerta->verificar($escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);

        $limpios = ReglasFormacionProyecto::normalizar($datos->politica_inicial, $datos->acciones);
        $errores = ReglasFormacionProyecto::errores($limpios);
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        DB::transaction(function () use ($escuelaNivel, $limpios) {
            $bloqueado = EscuelaNivel::lockForUpdate()->findOrFail($escuelaNivel->id);
            $this->compuerta->verificar($bloqueado, SeccionesProyectoEducativo::CLAVE_PASO);
            $this->tramiteEditable->asegurarEscuela((int) $bloqueado->escuela_id, SeccionesProyectoEducativo::CLAVE_PASO, $bloqueado->id);

            $clave = ['escuela_nivel_id' => $bloqueado->id, 'tipo' => ReglasFormacionProyecto::TIPO_INICIAL];
            ProyectoEducativoFormacionPolitica::updateOrCreate($clave, ['politica' => $limpios['politica']]);
            EscribirAccionesFormacion::reemplazar($clave, $limpios['acciones']);
        });
    }
}
