<?php

namespace App\Application\Documentos;

use App\Models\DocumentoEscuela;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\TipoDocumento;
use RuntimeException;

/**
 * Única fuente de verdad para "¿ya se completaron los 6 documentos de
 * Paso 2.2?" — Paso2Responsable::mount() y Paso2Documentos::mount() la
 * llaman, ninguno de los dos recalcula la lista de claves aplicables por
 * su cuenta (docs/superpowers/specs/2026-09-10-paso2-documentos-design.md
 * §5).
 */
class DocumentosCompletos
{
    /**
     * Catálogo-driven desde WS-5a: antes de esto era un array fijo de 6
     * claves; ahora es tipos_documentos, filtrado por aplica_persona y
     * limitado a los ámbitos de Paso 2.2 (plantel/escuela — los ámbitos
     * escuela_nivel de Paso 2.4 llegan en un sub-plan futuro y tienen su
     * propio método). Ordenado por id = orden de inserción del seeder, para
     * no reordenar el checklist que ya renderiza en un orden dado.
     *
     * @return list<string>
     */
    public function clavesAplicables(string $tipoPersona): array
    {
        return TipoDocumento::query()
            ->whereIn('ambito', ['plantel', 'escuela'])
            ->where(function ($query) use ($tipoPersona) {
                $query->where('aplica_persona', 'ambas')
                    ->orWhere('aplica_persona', $tipoPersona);
            })
            ->orderBy('id')
            ->pluck('clave')
            ->all();
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
            function (string $clave) use ($idsAplicables, $idsCompletados) {
                if (! $idsAplicables->has($clave)) {
                    throw new RuntimeException("tipos_documentos no tiene una fila con clave \"{$clave}\" — corre TiposDocumentosSeeder.");
                }

                return ! in_array($idsAplicables[$clave], $idsCompletados, true);
            },
        ));
    }

    public function paraEscuela(int $escuelaId, string $tipoPersona): bool
    {
        return $this->clavesPendientes($escuelaId, $tipoPersona) === [];
    }
}
