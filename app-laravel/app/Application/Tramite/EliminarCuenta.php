<?php

namespace App\Application\Tramite;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Models\Escuela;
use App\Models\Solicitante;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Borra la cuenta de quien la pide (ADR-014). Un solicitante solo puede si ningún
 * trámite se ha enviado a SEDEQ: entonces se borran sus trámites (misma limpieza
 * que EliminarTramite, archivos incluidos) y todo se revierte si alguno ya se envió.
 * Un responsable por nivel pierde cuenta y accesos; lo capturado queda en el nivel.
 * Las cuentas de SEDEQ no se borran desde aquí. Los responsables por nivel invitados
 * por un solicitante que se va quedan sin accesos (huérfanos aceptados, ADR-015).
 */
class EliminarCuenta
{
    public function __construct(private readonly EliminarTramite $eliminarTramite) {}

    /** @throws PrecondicionIncumplida si es cuenta de SEDEQ o algún trámite ya se envió. */
    public function ejecutar(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            $usuario = User::whereKey($userId)->lockForUpdate()->firstOrFail();

            if ($usuario->hasRole('sedeq')) {
                throw new PrecondicionIncumplida('cuenta', 'Las cuentas de SEDEQ no se pueden eliminar desde el perfil.');
            }

            $solicitante = Solicitante::where('user_id', $userId)->lockForUpdate()->first();

            if ($solicitante !== null) {
                try {
                    foreach (Escuela::where('solicitante_id', $solicitante->id)->orderBy('id')->pluck('id') as $escuelaId) {
                        $this->eliminarTramite->ejecutar($escuelaId);
                    }
                } catch (PrecondicionIncumplida) {
                    throw new PrecondicionIncumplida(TramiteEditable::EN_CAPTURA, 'No puedes eliminar tu cuenta: ya enviaste un trámite a SEDEQ.');
                }

                $solicitante->delete();
            }

            $usuario->delete();
        });
    }
}
