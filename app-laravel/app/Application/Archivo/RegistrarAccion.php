<?php

namespace App\Application\Archivo;

use App\Models\BitacoraAccion;

/** Escribe una fila en bitacora_acciones. $detalle nunca debe contener nombre ni correo del sujeto. */
class RegistrarAccion
{
    public const ARCHIVAR_TRAMITE = 'archivar_tramite';

    public const RESTAURAR_TRAMITE = 'restaurar_tramite';

    public const PURGAR_TRAMITE = 'purgar_tramite';

    public const ARCHIVAR_CUENTA = 'archivar_cuenta';

    public const RESTAURAR_CUENTA = 'restaurar_cuenta';

    public const PURGAR_CUENTA = 'purgar_cuenta';

    public const CAMBIAR_ROL = 'cambiar_rol';

    /** @param array<string, mixed> $detalle */
    public function ejecutar(int $actorId, string $accion, string $objetoTipo, int $objetoId, ?string $motivo = null, array $detalle = []): void
    {
        BitacoraAccion::create([
            'actor_id' => $actorId,
            'accion' => $accion,
            'objeto_tipo' => $objetoTipo,
            'objeto_id' => $objetoId,
            'motivo' => $motivo,
            'detalle' => $detalle,
            'created_at' => now(),
        ]);
    }
}
