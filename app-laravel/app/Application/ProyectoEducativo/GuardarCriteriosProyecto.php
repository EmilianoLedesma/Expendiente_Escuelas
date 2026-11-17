<?php

namespace App\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosCriteriosProyecto;
use App\Application\Tramite\CompuertaSubPaso3;
use App\Application\Tramite\TramiteEditable;
use App\Models\EscuelaNivel;
use App\Models\ProyectoEducativoCriterios;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Sección 13 del Proyecto Educativo (media superior): guarda UN bloque (1 = acreditación y promoción, 2 = titulación y
 * control escolar), todo o nada dentro del bloque: si alguno de sus campos falla no se escribe nada. Solo valida y
 * escribe los campos del bloque; los del otro bloque que lleguen en el DTO se ignoran y lo ya guardado de él no se toca.
 * No marca el paso proyecto_educativo como completado.
 */
class GuardarCriteriosProyecto
{
    public function __construct(
        private readonly CompuertaSubPaso3 $compuerta,
        private readonly TramiteEditable $tramiteEditable,
    ) {}

    /**
     * @throws DatosInvalidos
     * @throws PrecondicionIncumplida
     * @throws \InvalidArgumentException con un bloque que no existe
     */
    public function ejecutar(int $escuelaNivelId, int $bloque, DatosCriteriosProyecto $datos): void
    {
        $camposDelBloque = (ReglasCriteriosProyecto::BLOQUES[$bloque] ?? throw new \InvalidArgumentException("Bloque de criterios desconocido: {$bloque}"))['campos'];

        $escuelaNivel = EscuelaNivel::findOrFail($escuelaNivelId);
        // Fuera de media superior el paso no existe en el catálogo del nivel y la compuerta lanza InvalidArgumentException.
        $this->compuerta->verificar($escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);

        $campos = Arr::only(
            array_map(fn (?string $valor) => ($valor = trim((string) $valor)) === '' ? null : $valor, $datos->aArray()),
            $camposDelBloque,
        );
        $errores = ReglasCriteriosProyecto::errores($campos, $bloque);
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        DB::transaction(function () use ($escuelaNivel, $campos) {
            $bloqueado = EscuelaNivel::lockForUpdate()->findOrFail($escuelaNivel->id);
            $this->compuerta->verificar($bloqueado, SeccionesProyectoEducativo::CLAVE_PASO);
            $this->tramiteEditable->asegurarEscuela((int) $bloqueado->escuela_id, SeccionesProyectoEducativo::CLAVE_PASO, $bloqueado->id);

            ProyectoEducativoCriterios::updateOrCreate(['escuela_nivel_id' => $bloqueado->id], $campos);
        });
    }
}
