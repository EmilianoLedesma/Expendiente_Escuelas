<?php

namespace App\Application\Visita;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\RolesSedeq;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\User;
use App\Models\VisitaInspeccion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * El supervisor adjunta (o reemplaza) el PDF del acta de una visita ya realizada. El archivo anterior se borra solo
 * cuando el cambio se confirma; si la transacción falla, se borra el nuevo.
 */
class AdjuntarActaVisita
{
    public function __construct(private readonly ArchivoActaVisita $archivo) {}

    /**
     * @throws DatosInvalidos|PrecondicionIncumplida
     */
    public function ejecutar(User $actor, int $visitaId, UploadedFile $acta): void
    {
        RolesSedeq::exigir($actor, RolesSedeq::SUPERVISOR);
        $this->archivo->revisar($acta);

        $nivelId = (int) VisitaInspeccion::findOrFail($visitaId)->escuela_nivel_id;
        $escuelaId = (int) EscuelaNivel::findOrFail($nivelId)->escuela_id;
        $ruta = $this->archivo->guardar($escuelaId, $acta);

        // Se pone en true al inicio del afterCommit: un fallo posterior (p. ej. un listener) no debe borrar el archivo ya referenciado.
        $confirmado = false;
        try {
            DB::transaction(function () use ($visitaId, $nivelId, $escuelaId, $acta, $ruta, &$confirmado) {
                EscuelaNivel::whereKey($nivelId)->lockForUpdate()->firstOrFail();
                Escuela::whereKey($escuelaId)->lockForUpdate()->firstOrFail();
                $visita = VisitaInspeccion::whereKey($visitaId)->lockForUpdate()->firstOrFail();

                if ($visita->estado !== 'realizada') {
                    throw new DatosInvalidos(['visita' => 'Solo se puede adjuntar el acta a una visita realizada.']);
                }

                $anterior = $visita->acta_path;
                $visita->update(['acta_path' => $ruta, 'acta_nombre' => mb_substr($acta->getClientOriginalName(), 0, 255)]);

                DB::afterCommit(function () use (&$confirmado, $anterior) {
                    $confirmado = true;
                    if ($anterior !== null) {
                        $this->archivo->borrar($anterior);
                    }
                });
            });
        } catch (Throwable $e) {
            if (! $confirmado) {
                $this->archivo->borrar($ruta);
            }

            throw $e;
        }
    }
}
