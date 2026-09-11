<?php

namespace App\Application\Documentos;

use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\TipoDocumento;

/**
 * Corre al avanzar de 2.2 a 2.3, no al subir cada documento
 * (docs/superpowers/specs/2026-09-10-paso2-documentos-design.md §5) —
 * evita penalizar a quien sube el Dictamen el día 1 y tarda semanas en
 * juntar el resto.
 */
class ValidarVigenciaDocumentos
{
    /** @return list<string> */
    public function ejecutar(int $escuelaId): array
    {
        $escuela = Escuela::findOrFail($escuelaId);
        $violaciones = [];

        $dictamenTipo = TipoDocumento::where('clave', 'dictamen_uso_suelo')->first();
        $dictamen = DocumentoPlantel::where('plantel_id', $escuela->plantel_id)
            ->where('tipo_documento_id', $dictamenTipo?->id)
            ->first();

        if ($dictamen !== null && $dictamen->fecha_vigencia !== null && now()->toDateString() > $dictamen->fecha_vigencia) {
            $violaciones[] = 'Dictamen de Uso de Suelo: ha superado su vigencia máxima, debe resubirse.';
        }

        $constanciaTipo = TipoDocumento::where('clave', 'constancia_seguridad_estructural')->first();
        $constancia = DocumentoPlantel::where('plantel_id', $escuela->plantel_id)
            ->where('tipo_documento_id', $constanciaTipo?->id)
            ->with('constanciaSeguridadEstructural')
            ->first();

        $extension = $constancia?->constanciaSeguridadEstructural;
        if ($extension !== null && $extension->perito_registro_vigencia !== null && $constancia->fecha_emision !== null) {
            $anioRegistro = date('Y', strtotime($extension->perito_registro_vigencia));
            $anioEmision = date('Y', strtotime($constancia->fecha_emision));

            if ($anioRegistro !== $anioEmision) {
                $violaciones[] = 'Constancia de Seguridad Estructural: el año del registro del perito no coincide con el año de emisión.';
            }
        }

        return $violaciones;
    }
}
