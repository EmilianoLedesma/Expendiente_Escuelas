<?php

namespace App\Application\ProyectoEducativo;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\CompuertaSubPaso3;
use App\Models\EscuelaNivel;
use Illuminate\Support\Facades\DB;

/**
 * El solicitante confirma el paso proyecto_educativo (media superior): solo con las 20 secciones completas se marca
 * escuela_nivel_pasos como completado, y con eso el trámite puede enviarse. La confirmación la pide el solicitante
 * con un botón, no se deduce al guardar. Idempotente. Un trámite enviado la rechaza (lo decide MarcarPasoCompletado).
 */
class ConfirmarProyectoEducativo
{
    public function __construct(
        private readonly CompuertaSubPaso3 $compuerta,
        private readonly EstadoSeccionesProyecto $estado,
        private readonly MarcarPasoCompletado $marcar,
    ) {}

    /** @throws PrecondicionIncumplida si falta una sección, el trámite ya se envió o el paso aún no es alcanzable. */
    public function ejecutar(int $escuelaNivelId): void
    {
        $escuelaNivel = EscuelaNivel::findOrFail($escuelaNivelId);
        // Fuera de media superior el paso no existe en el catálogo del nivel y la compuerta lanza InvalidArgumentException.
        $this->compuerta->verificar($escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);

        DB::transaction(function () use ($escuelaNivel) {
            $bloqueado = EscuelaNivel::lockForUpdate()->findOrFail($escuelaNivel->id);
            $this->compuerta->verificar($bloqueado, SeccionesProyectoEducativo::CLAVE_PASO);

            $faltan = array_keys(array_filter($this->estado->completas($bloqueado->id), fn (bool $completa) => ! $completa));
            if ($faltan !== []) {
                throw new PrecondicionIncumplida(
                    SeccionesProyectoEducativo::CLAVE_PASO,
                    'Completa las 20 secciones del proyecto educativo antes de confirmarlo. Faltan: '.implode(', ', array_map(
                        fn (string $slug) => SeccionesProyectoEducativo::todas()[$slug],
                        $faltan,
                    )).'.',
                );
            }

            $this->marcar->ejecutar($bloqueado->id, SeccionesProyectoEducativo::CLAVE_PASO);
        });
    }
}
