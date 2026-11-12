<?php

namespace App\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosAcademiasProyecto;
use App\Application\Tramite\CompuertaSubPaso3;
use App\Application\Tramite\TramiteEditable;
use App\Models\EscuelaNivel;
use App\Models\ProyectoEducativoAcademias;
use Illuminate\Support\Facades\DB;

/**
 * Sección 12 del Proyecto Educativo (media superior): guarda el tipo de academia, su descripción (solo Mixta u Otros) y
 * la justificación, todo o nada: si alguna regla falla no se escribe nada. Con un tipo que no pide descripción, la
 * descripción se descarta (y borra la guardada). No marca el paso proyecto_educativo como completado.
 */
class GuardarAcademiasProyecto
{
    public function __construct(
        private readonly CompuertaSubPaso3 $compuerta,
        private readonly TramiteEditable $tramiteEditable,
    ) {}

    /**
     * @throws DatosInvalidos
     * @throws PrecondicionIncumplida
     */
    public function ejecutar(int $escuelaNivelId, DatosAcademiasProyecto $datos): void
    {
        $escuelaNivel = EscuelaNivel::findOrFail($escuelaNivelId);
        // Fuera de media superior el paso no existe en el catálogo del nivel y la compuerta lanza InvalidArgumentException.
        $this->compuerta->verificar($escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);

        $campos = array_map(fn (?string $valor) => ($valor = trim((string) $valor)) === '' ? null : $valor, $datos->aArray());
        $errores = ReglasAcademiasProyecto::errores($campos);
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        if (! ReglasAcademiasProyecto::requiereDescripcion($campos['tipo_academia'])) {
            $campos['descripcion_academia'] = null;
        }

        DB::transaction(function () use ($escuelaNivel, $campos) {
            $bloqueado = EscuelaNivel::lockForUpdate()->findOrFail($escuelaNivel->id);
            $this->compuerta->verificar($bloqueado, SeccionesProyectoEducativo::CLAVE_PASO);
            $this->tramiteEditable->asegurarEscuela((int) $bloqueado->escuela_id, SeccionesProyectoEducativo::CLAVE_PASO, $bloqueado->id);

            ProyectoEducativoAcademias::updateOrCreate(['escuela_nivel_id' => $bloqueado->id], $campos);
        });
    }
}
