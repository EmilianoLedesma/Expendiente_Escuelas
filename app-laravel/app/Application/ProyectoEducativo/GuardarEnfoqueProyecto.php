<?php

namespace App\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosEnfoqueProyecto;
use App\Application\Tramite\CompuertaSubPaso3;
use App\Application\Tramite\TramiteEditable;
use App\Models\EscuelaNivel;
use App\Models\ProyectoEducativoEnfoque;
use Illuminate\Support\Facades\DB;

/**
 * Sección 4 del Proyecto Educativo (media superior): guarda la propuesta global, la apropiación del enfoque y las dos
 * listas de ejemplos, todo o nada: si alguna regla falla no se escribe nada y se devuelven todos los errores. No marca
 * el paso proyecto_educativo como completado.
 */
class GuardarEnfoqueProyecto
{
    public function __construct(
        private readonly CompuertaSubPaso3 $compuerta,
        private readonly TramiteEditable $tramiteEditable,
    ) {}

    /**
     * @throws DatosInvalidos
     * @throws PrecondicionIncumplida
     */
    public function ejecutar(int $escuelaNivelId, DatosEnfoqueProyecto $datos): void
    {
        $escuelaNivel = EscuelaNivel::findOrFail($escuelaNivelId);
        // Fuera de media superior el paso no existe en el catálogo del nivel y la compuerta lanza InvalidArgumentException.
        $this->compuerta->verificar($escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);

        $campos = array_map(self::normalizar(...), $datos->aArray());
        $errores = ReglasEnfoqueProyecto::errores($campos);
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        DB::transaction(function () use ($escuelaNivel, $campos) {
            $bloqueado = EscuelaNivel::lockForUpdate()->findOrFail($escuelaNivel->id);
            $this->compuerta->verificar($bloqueado, SeccionesProyectoEducativo::CLAVE_PASO);
            $this->tramiteEditable->asegurarEscuela((int) $bloqueado->escuela_id, SeccionesProyectoEducativo::CLAVE_PASO, $bloqueado->id);

            ProyectoEducativoEnfoque::updateOrCreate(['escuela_nivel_id' => $bloqueado->id], $campos);
        });
    }

    /** Recorta; vacío pasa a null. En las listas se normaliza cada ejemplo (un ejemplo vacío es un error, no se descarta). */
    private static function normalizar(mixed $valor): mixed
    {
        if (is_array($valor)) {
            return array_values(array_map(self::normalizar(...), $valor));
        }

        return is_string($valor) && ($recortado = trim($valor)) !== '' ? $recortado : null;
    }
}
