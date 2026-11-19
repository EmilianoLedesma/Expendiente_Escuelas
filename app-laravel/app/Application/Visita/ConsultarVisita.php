<?php

namespace App\Application\Visita;

use App\Application\Revision\RolesSedeq;
use App\Application\Tramite\ResumenTramite;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\RespuestaVisita;
use App\Models\RespuestaVisitaArchivo;
use App\Models\User;
use App\Models\VisitaInspeccion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Lectura de las visitas de inspección de un nivel: la última, el historial completo con observaciones y respuestas
 * (con sus archivos) y las banderas de qué puede hacer el actor. Lo leen el supervisor y, en la fase 8, la página del
 * solicitante. Autoriza con la habilidad `verVisita` (supervisor, dueño o responsable de ESTE nivel).
 *
 * Visibilidad: en esta fase nada es exclusivo del personal; los nombres de archivo son solo para mostrar (la descarga
 * va por id). Las banderas `puede*` son del supervisor: para cualquier otro actor son todas falsas.
 */
final class ConsultarVisita
{
    /**
     * @return array{
     *     escuela: string, escuelaId: int, nivel: string, domicilioPlantel: string,
     *     estado: array{clave: string, etiqueta: string},
     *     visita: array<string, mixed>|null, visitas: list<array<string, mixed>>,
     *     puedeProgramar: bool, puedeRegistrarActa: bool, puedeActualizar: bool, puedeAdjuntarActa: bool, puedeAceptar: bool,
     *     hayRespuestaNueva: bool
     * }
     *
     * @throws AuthorizationException
     */
    public function ejecutar(User $actor, int $escuelaNivelId): array
    {
        $nivel = EscuelaNivel::findOrFail($escuelaNivelId);
        Gate::forUser($actor)->authorize('verVisita', $nivel);

        $estado = DB::table('estados_expediente')->where('id', $nivel->estado_id)->first(['clave', 'nombre']);
        $escuela = DB::table('escuelas as e')
            ->where('e.id', $nivel->escuela_id)
            ->selectRaw("COALESCE(e.nombre_aprobado, (SELECT t.nombre_propuesto FROM ternas_nombres t WHERE t.escuela_id = e.id ORDER BY t.numero_propuesta LIMIT 1), 'Plantel sin nombre') as nombre")
            ->value('nombre');
        $plantel = Escuela::with('plantel')->findOrFail($nivel->escuela_id)->plantel;
        $nombreNivel = (string) DB::table('niveles_educativos')->where('id', $nivel->nivel_educativo_id)->value('nombre');

        $visitas = VisitaInspeccion::where('escuela_nivel_id', $nivel->id)
            ->with(['observaciones', 'respuestas.archivos'])->orderBy('numero')->get();
        $ultima = $visitas->last();
        $supervisor = $actor->hasRole(RolesSedeq::SUPERVISOR);
        $clave = (string) $estado->clave;

        $programada = $ultima?->estado === 'programada';
        $conObservaciones = $ultima?->estado === 'realizada' && $ultima->resultado === 'con_observaciones';

        return [
            'escuela' => (string) $escuela,
            'escuelaId' => (int) $nivel->escuela_id,
            'nivel' => $nombreNivel,
            'domicilioPlantel' => ResumenTramite::domicilio($plantel),
            'estado' => ['clave' => $clave, 'etiqueta' => (string) $estado->nombre],
            'visita' => $ultima === null ? null : $this->visita($ultima),
            'visitas' => $visitas->map(fn (VisitaInspeccion $v) => $this->visita($v))->all(),
            'puedeProgramar' => $supervisor && in_array($clave, ['aprobado', 'en_subsanacion'], true) && ! $programada,
            'puedeRegistrarActa' => $supervisor && $programada,
            'puedeActualizar' => $supervisor && $programada,
            'puedeAdjuntarActa' => $supervisor && $ultima?->estado === 'realizada',
            'puedeAceptar' => $supervisor && $clave === 'en_subsanacion' && $conObservaciones,
            'hayRespuestaNueva' => $this->hayRespuestaNueva($nivel->id, $ultima),
        ];
    }

    /** Una respuesta de la última visita más reciente que el último cambio de estado del nivel (acta o aceptación). */
    private function hayRespuestaNueva(int $nivelId, ?VisitaInspeccion $ultima): bool
    {
        if ($ultima === null) {
            return false;
        }
        $ultimoCambio = DB::table('historial_estados_expediente')->where('escuela_nivel_id', $nivelId)->max('fecha');

        return $ultima->respuestas->contains(fn (RespuestaVisita $r) => $ultimoCambio === null || $r->created_at->gt(Carbon::parse($ultimoCambio)));
    }

    /** @return array<string, mixed> */
    private function visita(VisitaInspeccion $v): array
    {
        return [
            'id' => $v->id,
            'numero' => (int) $v->numero,
            'estado' => $v->estado,
            'resultado' => $v->resultado,
            'programadaPara' => $v->programada_para,
            'inspector' => $v->inspector,
            'realizadaEl' => $v->realizada_el,
            'subsanarHasta' => $v->subsanar_hasta,
            'actaNombre' => $v->acta_path === null ? null : ($v->acta_nombre ?? 'acta.pdf'),
            'tieneActa' => $v->acta_path !== null,
            'observaciones' => $v->observaciones->pluck('texto')->all(),
            'respuestas' => $v->respuestas->map(fn (RespuestaVisita $r) => [
                'id' => $r->id,
                'nota' => $r->nota,
                'creadaEn' => $r->created_at,
                'archivos' => $r->archivos->map(fn (RespuestaVisitaArchivo $a) => ['id' => $a->id, 'nombre' => $a->archivo_nombre])->all(),
            ])->all(),
        ];
    }
}
