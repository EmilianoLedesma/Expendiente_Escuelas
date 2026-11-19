<?php

namespace App\Application\Visita;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Revision\RolesSedeq;
use App\Application\Visita\DTO\FilaVisitaRealizada;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Registro (solo lectura) de las visitas ya realizadas de todas las escuelas, para el supervisor: una fila por visita
 * `realizada`, la más reciente primero. Una sola consulta con subconsultas para los conteos; el listado se acota a
 * LIMITE filas y `total` dice cuántas hay con los filtros. Nunca muestra escuelas archivadas ni niveles en captura.
 */
final class ListarVisitasRealizadas
{
    public const LIMITE = 100;

    /** @return array{filas: list<FilaVisitaRealizada>, total: int} */
    public function ejecutar(User $actor, string $buscar = '', ?string $resultado = null, ?string $desde = null, ?string $hasta = null): array
    {
        RolesSedeq::exigir($actor, RolesSedeq::SUPERVISOR);
        $resultado = $resultado === '' ? null : $resultado;
        $desde = $desde === '' ? null : $desde;
        $hasta = $hasta === '' ? null : $hasta;

        $errores = [];
        if ($resultado !== null && ! in_array($resultado, ['cumple', 'con_observaciones'], true)) {
            $errores['resultado'] = 'Ese resultado no es válido.';
        }
        foreach (['desde' => $desde, 'hasta' => $hasta] as $campo => $valor) {
            if ($valor !== null && Validator::make(['f' => $valor], ['f' => 'date_format:Y-m-d'])->fails()) {
                $errores[$campo] = 'La fecha no es válida.';
            }
        }
        if ($errores === [] && $desde !== null && $hasta !== null && $desde > $hasta) {
            $errores['desde'] = 'La fecha inicial no puede ser posterior a la final.';
        }
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        $nombre = "COALESCE(e.nombre_aprobado, (SELECT t.nombre_propuesto FROM ternas_nombres t WHERE t.escuela_id = e.id ORDER BY t.numero_propuesta LIMIT 1), 'Plantel sin nombre')";
        $buscar = trim($buscar);
        $patron = '%'.addcslashes($buscar, '%_\\').'%';

        $consulta = DB::table('visitas_inspeccion as v')
            ->join('escuela_niveles as en', 'en.id', '=', 'v.escuela_nivel_id')
            ->join('escuelas as e', 'e.id', '=', 'en.escuela_id')
            ->join('niveles_educativos as ne', 'ne.id', '=', 'en.nivel_educativo_id')
            ->join('estados_expediente as est', 'est.id', '=', 'en.estado_id')
            ->where('v.estado', 'realizada')
            ->where('est.clave', '<>', 'en_captura')
            ->whereNull('e.archivado_at')
            ->when($buscar !== '', fn ($q) => $q->where(fn ($w) => $w->whereRaw("{$nombre} ILIKE ?", [$patron])->orWhere('ne.nombre', 'ilike', $patron)))
            ->when($resultado !== null, fn ($q) => $q->where('v.resultado', $resultado))
            ->when($desde !== null, fn ($q) => $q->where('v.realizada_el', '>=', $desde))
            ->when($hasta !== null, fn ($q) => $q->where('v.realizada_el', '<=', $hasta));

        $total = (clone $consulta)->count();

        $filas = $consulta
            ->selectRaw(
                "v.id, v.numero, v.realizada_el, v.inspector, v.resultado, v.subsanar_hasta, (v.acta_path IS NOT NULL) as tiene_acta,
                en.id as nivel_id, e.id as escuela_id, {$nombre} as escuela, ne.nombre as nivel, est.clave as estado_clave, est.nombre as estado_nombre,
                (SELECT COUNT(*) FROM observaciones_visita o WHERE o.visita_id = v.id) as observaciones,
                (SELECT COUNT(*) FROM respuestas_visita r WHERE r.visita_id = v.id) as respuestas"
            )
            ->orderByDesc('v.realizada_el')->orderByDesc('v.id')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn ($r) => new FilaVisitaRealizada(
                visitaId: (int) $r->id,
                escuelaNivelId: (int) $r->nivel_id,
                escuelaId: (int) $r->escuela_id,
                escuela: $r->escuela,
                nivel: $r->nivel,
                numero: (int) $r->numero,
                realizadaEl: Carbon::parse($r->realizada_el),
                inspector: $r->inspector,
                resultado: $r->resultado,
                subsanarHasta: $r->subsanar_hasta === null ? null : Carbon::parse($r->subsanar_hasta),
                observacionesCount: (int) $r->observaciones,
                respuestasCount: (int) $r->respuestas,
                tieneActa: (bool) $r->tiene_acta,
                nivelEstado: $r->estado_clave,
                nivelEstadoEtiqueta: $r->estado_nombre,
            ))->all();

        return ['filas' => $filas, 'total' => $total];
    }
}
