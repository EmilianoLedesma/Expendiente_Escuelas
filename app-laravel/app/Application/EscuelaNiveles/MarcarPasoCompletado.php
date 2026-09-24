<?php

namespace App\Application\EscuelaNiveles;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\EstadoPaso3;
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
    public function __construct(private readonly ?EstadoPaso3 $estadoPaso3 = null) {}

    /** @throws PrecondicionIncumplida si el sub-paso $pasoClave aún no es alcanzable (WS-2.4b). Idempotente: re-marcar un paso ya completado siempre pasa, porque su predecesor ya lo estaba. */
    public function ejecutar(int $escuelaNivelId, string $pasoClave): void
    {
        $pasoCapturaId = DB::table('pasos_captura')->where('clave', $pasoClave)->value('id');

        if ($pasoCapturaId === null) {
            throw new InvalidArgumentException("paso_captura desconocido: {$pasoClave}");
        }

        $estadoPaso3 = $this->estadoPaso3 ?? app(EstadoPaso3::class);
        if (! $estadoPaso3->puedeAcceder($escuelaNivelId, $pasoClave)) {
            throw new PrecondicionIncumplida($pasoClave, "El paso \"{$pasoClave}\" no es alcanzable todavía.");
        }

        DB::transaction(function () use ($escuelaNivelId, $pasoCapturaId) {
            // ponytail: updateOrInsert porque RegistrarNivelesSeleccionados crea
            // escuela_niveles sin crear sus filas de escuela_nivel_pasos — en la
            // primera llamada no hay nada que actualizar. created_at tiene DEFAULT
            // now() en el DDL, así que la rama de inserción no lo necesita.
            DB::table('escuela_nivel_pasos')->updateOrInsert(
                ['escuela_nivel_id' => $escuelaNivelId, 'paso_captura_id' => $pasoCapturaId],
                ['estado' => 'completado', 'completado_at' => now(), 'updated_at' => now()],
            );
        });
    }
}
