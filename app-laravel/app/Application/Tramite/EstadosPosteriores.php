<?php

namespace App\Application\Tramite;

/** Estados de un nivel que ya pasó la revisión documental (`aprobado`) y sigue su vida posterior (visita de inspección). */
final class EstadosPosteriores
{
    public const DESDE_APROBADO = ['aprobado', 'visita_programada', 'en_subsanacion', 'visita_aprobada'];
}
