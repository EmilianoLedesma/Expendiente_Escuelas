<?php

namespace App\Application\Documentos;

use App\Application\ResponsableLegal\TipoPersonaDeEscuela;
use App\Models\DocumentoEscuelaNivel;
use App\Models\EscuelaNivel;
use App\Models\TipoDocumento;
use RuntimeException;

/**
 * Única fuente de verdad de "¿qué documentos pide Paso 2.4 a este nivel y
 * cuáles faltan?" (WS-5b). Hermana de DocumentosCompletos (Paso 2.2). Un
 * documento aplica si su ámbito es escuela_nivel, su nivel_educativo_id es
 * NULL o el del nivel, y su aplica_persona es 'ambas' o el tipo de persona
 * del responsable. Ordenado por id = orden del seeder = orden del checklist.
 */
class DocumentosNivelCompletos
{
    public function __construct(private readonly TipoPersonaDeEscuela $tipoPersonaDeEscuela) {}

    /** @return list<string> */
    public function clavesAplicables(int $escuelaNivelId): array
    {
        $escuelaNivel = EscuelaNivel::findOrFail($escuelaNivelId);
        $tipoPersona = $this->tipoPersonaDeEscuela->ejecutar($escuelaNivel->escuela_id);

        $claves = TipoDocumento::query()
            ->where('ambito', 'escuela_nivel')
            ->where(function ($query) use ($escuelaNivel) {
                $query->whereNull('nivel_educativo_id')
                    ->orWhere('nivel_educativo_id', $escuelaNivel->nivel_educativo_id);
            })
            ->where(function ($query) use ($tipoPersona) {
                $query->where('aplica_persona', 'ambas')
                    ->orWhere('aplica_persona', $tipoPersona);
            })
            ->orderBy('id')
            ->pluck('clave')
            ->all();

        // Todo nivel lleva al menos Formato y recibo: vacío solo puede ser un
        // catálogo sin sembrar, y [] dejaría pasar la compuerta de Paso 3 (WS-5a I1).
        if ($claves === []) {
            throw new RuntimeException('tipos_documentos no tiene documentos por nivel — corre TiposDocumentosSeeder.');
        }

        return $claves;
    }

    /** @return list<string> */
    public function clavesPendientes(int $escuelaNivelId): array
    {
        $capturadas = DocumentoEscuelaNivel::query()
            ->join('tipos_documentos', 'tipos_documentos.id', '=', 'documentos_escuela_nivel.tipo_documento_id')
            ->where('documentos_escuela_nivel.escuela_nivel_id', $escuelaNivelId)
            ->pluck('tipos_documentos.clave')
            ->all();

        return array_values(array_diff($this->clavesAplicables($escuelaNivelId), $capturadas));
    }

    public function paraEscuelaNivel(int $escuelaNivelId): bool
    {
        return $this->clavesPendientes($escuelaNivelId) === [];
    }
}
