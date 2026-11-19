<?php

namespace App\Application\Visita;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\RolesSedeq;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\User;
use App\Models\VisitaInspeccion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * El supervisor programa una visita de inspección para un nivel aprobado (o en subsanación). La visita toma el
 * siguiente número del nivel y el nivel pasa a visita_programada con su fila de historial. Orden de bloqueo: el
 * escuela_nivel y luego la escuela, como EnviarTramite.
 */
class ProgramarVisita
{
    private const ESTADOS_ORIGEN = ['aprobado', 'en_subsanacion'];

    /**
     * @return int id de la visita creada
     *
     * @throws DatosInvalidos|PrecondicionIncumplida
     */
    public function ejecutar(User $actor, int $nivelId, \DateTimeInterface $fecha, string $inspector): int
    {
        RolesSedeq::exigir($actor, RolesSedeq::SUPERVISOR);
        [$fecha, $inspector] = DatosVisita::validar($fecha, $inspector);

        try {
            return DB::transaction(function () use ($actor, $nivelId, $fecha, $inspector) {
                $nivel = EscuelaNivel::whereKey($nivelId)->lockForUpdate()->firstOrFail();
                Escuela::whereKey($nivel->escuela_id)->lockForUpdate()->firstOrFail();

                $clave = DB::table('estados_expediente')->where('id', $nivel->estado_id)->value('clave');
                if (! in_array($clave, self::ESTADOS_ORIGEN, true)) {
                    throw new DatosInvalidos(['nivel' => 'Solo se puede programar una visita en un nivel aprobado o en subsanación.']);
                }

                $visita = VisitaInspeccion::create([
                    'escuela_nivel_id' => $nivelId,
                    'numero' => ((int) VisitaInspeccion::where('escuela_nivel_id', $nivelId)->max('numero')) + 1,
                    'programada_para' => $fecha,
                    'inspector' => $inspector,
                    'estado' => 'programada',
                    'programada_por' => $actor->id,
                ]);

                $programada = (int) DB::table('estados_expediente')->where('clave', 'visita_programada')->value('id');
                $nivel->update(['estado_id' => $programada]);
                DB::table('historial_estados_expediente')->insert([
                    'escuela_nivel_id' => $nivelId,
                    'estado_id' => $programada,
                    'comentario' => 'Visita de inspección programada',
                    'usuario_sedeq_id' => $actor->id,
                    'fecha' => now(),
                ]);

                DB::afterCommit(fn () => event(new VisitaProgramada($visita->id)));

                return $visita->id;
            });
        } catch (QueryException $e) {
            // Respaldo de la carrera: la transacción ya revirtió, así que se traduce fuera de ella.
            if ($e->getCode() === '23505' && str_contains($e->getMessage(), 'visitas_inspeccion_una_programada')) {
                throw new DatosInvalidos(['nivel' => 'Ya hay una visita programada para este nivel.']);
            }

            throw $e;
        }
    }
}
