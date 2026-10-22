<?php

namespace App\Application\Revision;

use App\Application\Tramite\ResumenTramite;

/** Catálogo de pasos que el revisor decide, en el orden de revisión. Títulos: los de ResumenTramite. */
final class PasosRevision
{
    /** Los cuatro generales del trámite, antes de los pasos propios de cada nivel. */
    public const GENERALES = ['plantel', 'responsable', 'documentos', 'niveles'];

    /** Orden de revisión de los pasos del nivel; mobiliario solo aplica a ResumenTramite::NIVEL_CON_MOBILIARIO. */
    private const DEL_NIVEL = ['documentos_nivel', 'inmueble', 'infraestructura', 'mobiliario', 'plan_estudios', 'plantilla_docente', 'matricula'];

    /** Las 11 claves; el CHECK de paso_clave en las tablas de decisiones lista las mismas. */
    public const TODOS = [...self::GENERALES, ...self::DEL_NIVEL];

    /** @return list<string> */
    public static function paraNivel(string $nivelClave): array
    {
        return array_values(array_filter(
            self::TODOS,
            fn (string $c) => $c !== 'mobiliario' || $nivelClave === ResumenTramite::NIVEL_CON_MOBILIARIO,
        ));
    }

    public static function titulo(string $clave): string
    {
        return ResumenTramite::tituloSeccion($clave);
    }
}
