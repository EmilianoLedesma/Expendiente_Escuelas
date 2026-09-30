<?php

namespace App\Application\PlanEstudios;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\PlanEstudios\DTO\DatosPlanEstudios;
use App\Application\Tramite\CompuertaSubPaso3;
use App\Models\EscuelaNivel;
use Illuminate\Support\Facades\DB;

/**
 * Paso 3, sub-step 4 (Plan de estudios y modalidad). Writes the escuela_niveles
 * columns the DDL already has for it; the allowed values mirror their CHECKs.
 * Turno and tipo de alumnado are Paso 2.4's (RegistrarDatosNivel, WS-5b), where
 * changing them discards the uploaded Formato de Solicitud — never written here.
 * COMPENDIO §7 keeps the exact plan fields open, so the plan itself is a
 * free-text reference. The online platform is only asked for outside the
 * escolarizada modality (provisional, PENDIENTE-motor-capacidad-provisionales).
 */
class RegistrarPlanEstudios
{
    public const MODALIDADES = ['escolarizada', 'no_escolarizada', 'mixta', 'virtual'];

    public const PLATAFORMAS = ['propia', 'rentada'];

    public function __construct(
        private readonly CompuertaSubPaso3 $compuerta,
        private readonly MarcarPasoCompletado $marcarPasoCompletado,
    ) {}

    public function ejecutar(int $escuelaNivelId, DatosPlanEstudios $datos): void
    {
        $escuelaNivel = EscuelaNivel::findOrFail($escuelaNivelId);
        $this->compuerta->verificar($escuelaNivel, 'plan_estudios');

        $errores = [];
        if (! in_array($datos->modalidad, self::MODALIDADES, true)) {
            $errores['modalidad'] = 'Elige una modalidad válida.';
        }

        $escolarizada = $datos->modalidad === 'escolarizada';
        if (! $escolarizada && ! isset($errores['modalidad']) && ! in_array($datos->plataformaEducativaTipo, self::PLATAFORMAS, true)) {
            $errores['plataformaEducativaTipo'] = 'Indica si la plataforma educativa es propia o rentada.';
        }

        $referencia = $datos->planEstudiosReferencia !== null ? trim($datos->planEstudiosReferencia) : null;
        if ($referencia !== null && mb_strlen($referencia) > 200) {
            $errores['planEstudiosReferencia'] = 'La referencia admite hasta 200 caracteres.';
        }

        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        DB::transaction(function () use ($escuelaNivel, $datos, $escolarizada, $referencia) {
            $escuelaNivel->update([
                'modalidad' => $datos->modalidad,
                'plan_estudios_referencia' => $referencia !== '' ? $referencia : null,
                'plataforma_educativa_tipo' => $escolarizada ? null : $datos->plataformaEducativaTipo,
            ]);

            $this->marcarPasoCompletado->ejecutar($escuelaNivel->id, 'plan_estudios');
        });
    }
}
