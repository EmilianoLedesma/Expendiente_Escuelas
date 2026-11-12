<?php

namespace App\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosCurriculumProyecto;
use App\Application\Tramite\CompuertaSubPaso3;
use App\Application\Tramite\TramiteEditable;
use App\Models\EscuelaNivel;
use App\Models\ProyectoEducativoCurriculum;
use Illuminate\Support\Facades\DB;

/**
 * Sección 7 del Proyecto Educativo (media superior): guarda la respuesta (Sí/No) y la justificación de los cinco ámbitos
 * del Currículum Ampliado, todo o nada: si alguna regla falla no se escribe nada y se devuelven todos los errores. No
 * marca el paso proyecto_educativo como completado.
 */
class GuardarCurriculumProyecto
{
    public function __construct(
        private readonly CompuertaSubPaso3 $compuerta,
        private readonly TramiteEditable $tramiteEditable,
    ) {}

    /**
     * @throws DatosInvalidos
     * @throws PrecondicionIncumplida
     */
    public function ejecutar(int $escuelaNivelId, DatosCurriculumProyecto $datos): void
    {
        $escuelaNivel = EscuelaNivel::findOrFail($escuelaNivelId);
        // Fuera de media superior el paso no existe en el catálogo del nivel y la compuerta lanza InvalidArgumentException.
        $this->compuerta->verificar($escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);

        $campos = array_map(fn (?string $valor) => ($valor = trim((string) $valor)) === '' ? null : $valor, $datos->aArray());
        $errores = ReglasCurriculumProyecto::errores($campos);
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        // Ya validado: cada *_trabaja es 'si' o 'no' y se guarda como booleano.
        foreach (array_keys(ReglasCurriculumProyecto::AMBITOS) as $ambito) {
            $campos["{$ambito}_trabaja"] = $campos["{$ambito}_trabaja"] === 'si';
        }

        DB::transaction(function () use ($escuelaNivel, $campos) {
            $bloqueado = EscuelaNivel::lockForUpdate()->findOrFail($escuelaNivel->id);
            $this->compuerta->verificar($bloqueado, SeccionesProyectoEducativo::CLAVE_PASO);
            $this->tramiteEditable->asegurarEscuela((int) $bloqueado->escuela_id, SeccionesProyectoEducativo::CLAVE_PASO, $bloqueado->id);

            ProyectoEducativoCurriculum::updateOrCreate(['escuela_nivel_id' => $bloqueado->id], $campos);
        });
    }
}
