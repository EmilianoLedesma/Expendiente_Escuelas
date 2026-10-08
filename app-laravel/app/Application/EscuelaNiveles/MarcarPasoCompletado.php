<?php

namespace App\Application\EscuelaNiveles;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\EstadoPaso2;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\TramiteEditable;
use App\Models\EscuelaNivel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Única escritura de escuela_nivel_pasos en todo el proyecto (ADR-001).
 * Los casos de uso de captura de Paso 3 lo llaman dentro de su propia
 * transacción, después de su escritura principal; los componentes Livewire
 * pueden *decidir* llamarlo (auto-completado multi-nivel, salto de
 * Mobiliario en niveles distintos de Inicial) pero nunca escriben la tabla.
 */
class MarcarPasoCompletado
{
    public function __construct(
        private readonly ?EstadoPaso3 $estadoPaso3 = null,
        private readonly ?EstadoPaso2 $estadoPaso2 = null,
        private readonly ?TramiteEditable $tramiteEditable = null,
    ) {}

    /** @throws PrecondicionIncumplida si Paso 2 no está completo, el sub-paso $pasoClave aún no es alcanzable (WS-2.4b, Minor 7) o el trámite ya se envió (WS-7a). Idempotente: re-marcar un paso ya completado siempre pasa, porque su predecesor ya lo estaba. */
    public function ejecutar(int $escuelaNivelId, string $pasoClave): void
    {
        $pasoCapturaId = DB::table('pasos_captura')->where('clave', $pasoClave)->value('id');

        if ($pasoCapturaId === null) {
            throw new InvalidArgumentException("paso_captura desconocido: {$pasoClave}");
        }

        // Minor 7 — misma compuerta que verificarPrecondicion() en los casos
        // de uso Registrar* de Paso 3: un caller que se salte CompuertaPaso3
        // no debe poder marcar ningún sub-paso completado si Paso 2 no lo
        // está.
        $escuelaId = EscuelaNivel::where('id', $escuelaNivelId)->value('escuela_id');
        $estadoPaso2 = $this->estadoPaso2 ?? app(EstadoPaso2::class);
        $etapaFaltante = $escuelaId !== null ? $estadoPaso2->etapaFaltante($escuelaId) : null;
        if ($etapaFaltante !== null) {
            throw new PrecondicionIncumplida($etapaFaltante, 'Completa el Paso 2 antes de continuar.');
        }

        $estadoPaso3 = $this->estadoPaso3 ?? app(EstadoPaso3::class);
        if (! $estadoPaso3->puedeAcceder($escuelaNivelId, $pasoClave)) {
            throw new PrecondicionIncumplida($pasoClave, "El paso \"{$pasoClave}\" no es alcanzable todavía.");
        }

        $tramiteEditable = $this->tramiteEditable ?? app(TramiteEditable::class);

        DB::transaction(function () use ($escuelaNivelId, $pasoCapturaId, $escuelaId, $tramiteEditable) {
            // WS-7a, orden escuela_niveles → escuelas: el nivel se bloquea antes de
            // cualquier escritura y antes de la guarda (que bloquea la escuela). Un
            // UPDATE de escuela_nivel_pasos no bloquea al nivel por la FK, así que se
            // pide explícito.
            EscuelaNivel::whereKey($escuelaNivelId)->sharedLock()->value('id');

            // ponytail: updateOrInsert porque RegistrarNivelesSeleccionados crea
            // escuela_niveles sin crear sus filas de escuela_nivel_pasos — en la
            // primera llamada no hay nada que actualizar. created_at tiene DEFAULT
            // now() en el DDL, así que la rama de inserción no lo necesita.
            DB::table('escuela_nivel_pasos')->updateOrInsert(
                ['escuela_nivel_id' => $escuelaNivelId, 'paso_captura_id' => $pasoCapturaId],
                ['estado' => 'completado', 'completado_at' => now(), 'updated_at' => now()],
            );

            // WS-7a: los seis casos de uso de Paso 3 llaman aquí dentro de su propia
            // transacción, así que una sola guarda los revierte a todos.
            if ($escuelaId !== null) {
                $tramiteEditable->asegurarEscuela((int) $escuelaId);
            }
        });
    }
}
