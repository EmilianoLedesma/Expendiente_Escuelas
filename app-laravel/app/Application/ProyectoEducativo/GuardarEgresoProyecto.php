<?php

namespace App\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosEgresoProyecto;
use App\Application\Tramite\CompuertaSubPaso3;
use App\Application\Tramite\TramiteEditable;
use App\Models\EscuelaNivel;
use App\Models\ProyectoEducativoEgreso;
use Illuminate\Support\Facades\DB;

/**
 * Sección 9 del Proyecto Educativo (media superior): guarda el Perfil de Egreso. Si la regla falla no se escribe nada.
 * No marca el paso proyecto_educativo como completado.
 */
class GuardarEgresoProyecto
{
    public function __construct(
        private readonly CompuertaSubPaso3 $compuerta,
        private readonly TramiteEditable $tramiteEditable,
    ) {}

    /**
     * @throws DatosInvalidos
     * @throws PrecondicionIncumplida
     */
    public function ejecutar(int $escuelaNivelId, DatosEgresoProyecto $datos): void
    {
        $escuelaNivel = EscuelaNivel::findOrFail($escuelaNivelId);
        // Fuera de media superior el paso no existe en el catálogo del nivel y la compuerta lanza InvalidArgumentException.
        $this->compuerta->verificar($escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);

        $campos = array_map(fn (?string $valor) => ($valor = trim((string) $valor)) === '' ? null : $valor, $datos->aArray());
        $errores = ReglasEgresoProyecto::errores($campos);
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        DB::transaction(function () use ($escuelaNivel, $campos) {
            $bloqueado = EscuelaNivel::lockForUpdate()->findOrFail($escuelaNivel->id);
            $this->compuerta->verificar($bloqueado, SeccionesProyectoEducativo::CLAVE_PASO);
            $this->tramiteEditable->asegurarEscuela((int) $bloqueado->escuela_id, SeccionesProyectoEducativo::CLAVE_PASO, $bloqueado->id);

            ProyectoEducativoEgreso::updateOrCreate(['escuela_nivel_id' => $bloqueado->id], $campos);
        });
    }
}
