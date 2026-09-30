<?php

namespace App\Application\Tramite;

use App\Application\Documentos\DocumentosNivelCompletos;
use App\Application\Documentos\ValidarVigenciaDocumentos;
use App\Models\EscuelaNivel;

/**
 * Única fuente de verdad para "¿el Paso 2.4 de este nivel está completo?"
 * (WS-5b): turno y tipo de alumnado capturados, más todos los documentos
 * aplicables al nivel, en vigencia. Derivado, no almacenado (no es un
 * pasos_captura: esos son solo de Paso 3). EstadoPaso3 lo exige para todo
 * Paso 3 del nivel; los componentes solo leen etapaFaltante() (ADR-001).
 */
class EstadoPaso24
{
    public const DATOS = 'datos';

    /** Distinto de EstadoPaso2::DOCUMENTOS ('documentos') a propósito: no deben confundirse al enrutar por etapaFaltante. */
    public const DOCUMENTOS_NIVEL = 'documentos_nivel';

    public function __construct(
        private readonly DocumentosNivelCompletos $documentosNivelCompletos,
        private readonly ValidarVigenciaDocumentos $validarVigencia,
    ) {}

    public function datosNivelCapturados(int $escuelaNivelId): bool
    {
        $escuelaNivel = EscuelaNivel::findOrFail($escuelaNivelId);

        return $escuelaNivel->turno !== null && $escuelaNivel->tipo_alumnado !== null;
    }

    public function documentosCompletos(int $escuelaNivelId): bool
    {
        return $this->documentosNivelCompletos->paraEscuelaNivel($escuelaNivelId);
    }

    public function documentosVigentes(int $escuelaNivelId): bool
    {
        return $this->validarVigencia->paraEscuelaNivel($escuelaNivelId) === [];
    }

    /** Primera etapa de 2.4 sin completar (self::DATOS | self::DOCUMENTOS_NIVEL), o null si está completo. */
    public function etapaFaltante(int $escuelaNivelId): ?string
    {
        if (! $this->datosNivelCapturados($escuelaNivelId)) {
            return self::DATOS;
        }

        if (! $this->documentosCompletos($escuelaNivelId) || ! $this->documentosVigentes($escuelaNivelId)) {
            return self::DOCUMENTOS_NIVEL;
        }

        return null;
    }
}
