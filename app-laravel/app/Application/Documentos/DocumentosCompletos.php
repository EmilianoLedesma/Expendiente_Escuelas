<?php

namespace App\Application\Documentos;

use App\Models\DocumentoEscuela;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\TipoDocumento;

/**
 * Única fuente de verdad para "¿ya se completaron los 6 documentos de
 * Paso 2.2?" — Paso2Responsable::mount() y Paso2Documentos::mount() la
 * llaman, ninguno de los dos recalcula la lista de claves aplicables por
 * su cuenta (docs/superpowers/specs/2026-09-10-paso2-documentos-design.md
 * §5).
 */
class DocumentosCompletos
{
    /** @return list<string> */
    public function clavesAplicables(string $tipoPersona): array
    {
        $identidad = $tipoPersona === 'moral' ? 'escritura_poder_facultades' : 'acta_nacimiento';

        return [
            'ine',
            $identidad,
            'escritura_inmueble',
            'dictamen_uso_suelo',
            'constancia_seguridad_estructural',
            'formato_solicitud',
        ];
    }

    /** @return list<string> */
    public function clavesPendientes(int $escuelaId, string $tipoPersona): array
    {
        $escuela = Escuela::findOrFail($escuelaId);
        $aplicables = $this->clavesAplicables($tipoPersona);
        $idsAplicables = TipoDocumento::whereIn('clave', $aplicables)->pluck('id', 'clave');

        $completadasEscuela = DocumentoEscuela::where('escuela_id', $escuelaId)
            ->whereIn('tipo_documento_id', $idsAplicables->values())
            ->pluck('tipo_documento_id')
            ->all();

        $completadasPlantel = DocumentoPlantel::where('plantel_id', $escuela->plantel_id)
            ->whereIn('tipo_documento_id', $idsAplicables->values())
            ->pluck('tipo_documento_id')
            ->all();

        $idsCompletados = [...$completadasEscuela, ...$completadasPlantel];

        return array_values(array_filter(
            $aplicables,
            fn (string $clave) => ! in_array($idsAplicables[$clave], $idsCompletados, true),
        ));
    }

    public function paraEscuela(int $escuelaId, string $tipoPersona): bool
    {
        return $this->clavesPendientes($escuelaId, $tipoPersona) === [];
    }
}
