<?php

namespace App\Application\Revision;

use App\Application\Tramite\EnviarTramite;
use App\Models\PrioridadRevision;
use Illuminate\Support\Facades\DB;

/** Lecturas compartidas del HUD SEDEQ: el estado en_revision y la prioridad de un nivel. */
final class EstadoRevision
{
    /** De menor a mayor: el orden en que se desempata al desasignar por sobrecarga. */
    public const PRIORIDADES = ['baja', 'media', 'alta'];

    /** Un nivel sin fila en prioridades_revision tiene esta prioridad. */
    public const PRIORIDAD_POR_DEFECTO = 'media';

    public static function idEnRevision(): ?int
    {
        $id = DB::table('estados_expediente')->where('clave', EnviarTramite::EN_REVISION)->value('id');

        return $id === null ? null : (int) $id;
    }

    public static function prioridadDe(int $escuelaNivelId): string
    {
        return PrioridadRevision::whereKey($escuelaNivelId)->value('prioridad') ?? self::PRIORIDAD_POR_DEFECTO;
    }
}
