<?php

namespace App\Application\Tramite;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\ValidarVigenciaDocumentos;
use App\Models\ResponsableLegal;

/**
 * Única fuente de verdad para "¿Paso 2 está completo?" (responsable legal +
 * los documentos aplicables + en vigencia). Es la precondición para
 * seleccionar niveles (RegistrarNivelesSeleccionados) y para entrar a
 * cualquier página de Paso 3. Los componentes solo leen etapaFaltante() para
 * decidir a dónde redirigir; la decisión vive aquí (ADR-001).
 */
class EstadoPaso2
{
    public const RESPONSABLE = 'responsable';

    public const DOCUMENTOS = 'documentos';

    public function __construct(
        private readonly DocumentosCompletos $documentosCompletos,
        private readonly ValidarVigenciaDocumentos $validarVigencia,
    ) {}

    public function responsableCapturado(int $escuelaId): bool
    {
        return $this->tipoPersona($escuelaId) !== null;
    }

    public function documentosCompletos(int $escuelaId): bool
    {
        $tipoPersona = $this->tipoPersona($escuelaId);

        return $tipoPersona !== null && $this->documentosCompletos->paraEscuela($escuelaId, $tipoPersona);
    }

    public function documentosVigentes(int $escuelaId): bool
    {
        return $this->validarVigencia->ejecutar($escuelaId) === [];
    }

    public function puedeSeleccionarNiveles(int $escuelaId): bool
    {
        return $this->etapaFaltante($escuelaId) === null;
    }

    /** Primera etapa de Paso 2 sin completar (self::RESPONSABLE | self::DOCUMENTOS), o null si Paso 2 está completo. */
    public function etapaFaltante(int $escuelaId): ?string
    {
        if (! $this->responsableCapturado($escuelaId)) {
            return self::RESPONSABLE;
        }

        if (! $this->documentosCompletos($escuelaId) || ! $this->documentosVigentes($escuelaId)) {
            return self::DOCUMENTOS;
        }

        return null;
    }

    private function tipoPersona(int $escuelaId): ?string
    {
        return ResponsableLegal::where('escuela_id', $escuelaId)->value('tipo_persona');
    }
}
