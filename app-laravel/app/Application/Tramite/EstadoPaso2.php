<?php

namespace App\Application\Tramite;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\ValidarVigenciaDocumentos;
use App\Application\ResponsableLegal\TipoPersonaDeEscuela;
use App\Models\EscuelaNivel;

/**
 * Única fuente de verdad de las precondiciones de Paso 2 en el orden de 2026-10-20
 * (docs/specs/2026-10-20-paso2-integracion-end-to-end.md §4): responsable legal → niveles educativos →
 * documentos generales. Primero se fija el alcance educativo; después se calculan los documentos.
 *
 * Cada pregunta tiene su propio método y ningún consumidor reutiliza uno cuyo nombre no describa lo que
 * necesita: elegir niveles solo pide el responsable; capturar documentos generales pide además un nivel;
 * entrar a 2.4 o a Paso 3 pide `generalesSatisfechos()` (los tres, con los documentos completos y vigentes).
 * La editabilidad del trámite y la lista blanca de niveles las exige cada caso de uso dentro de su
 * transacción. Los componentes solo leen el resultado para decidir a dónde redirigir (ADR-001).
 */
class EstadoPaso2
{
    public const RESPONSABLE = 'responsable';

    public const NIVELES = 'niveles';

    public const DOCUMENTOS = 'documentos';

    public function __construct(
        private readonly DocumentosCompletos $documentosCompletos,
        private readonly ValidarVigenciaDocumentos $validarVigencia,
        private readonly TipoPersonaDeEscuela $tipoPersonaDeEscuela,
        private readonly PasosReabiertos $pasosReabiertos = new PasosReabiertos,
    ) {}

    public function responsableCapturado(int $escuelaId): bool
    {
        return $this->tipoPersona($escuelaId) !== null;
    }

    public function nivelesSeleccionados(int $escuelaId): bool
    {
        return EscuelaNivel::where('escuela_id', $escuelaId)->exists();
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

    /** Paso 2.2: basta el responsable legal; no depende de los documentos. */
    public function puedeSeleccionarNiveles(int $escuelaId): bool
    {
        return $this->responsableCapturado($escuelaId);
    }

    /** Paso 2.3: responsable y al menos un nivel (la aplicabilidad de los documentos depende de los niveles). */
    public function puedeCapturarDocumentosGenerales(int $escuelaId): bool
    {
        return $this->responsableCapturado($escuelaId) && $this->nivelesSeleccionados($escuelaId);
    }

    /** Responsable, niveles y documentos generales completos y vigentes: precondición de 2.4 y de Paso 3. */
    public function generalesSatisfechos(int $escuelaId): bool
    {
        return $this->etapaFaltante($escuelaId) === null;
    }

    /** Primera etapa de Paso 2 sin completar (self::RESPONSABLE | self::NIVELES | self::DOCUMENTOS), o null. */
    public function etapaFaltante(int $escuelaId): ?string
    {
        if (! $this->responsableCapturado($escuelaId)) {
            return self::RESPONSABLE;
        }

        if (! $this->nivelesSeleccionados($escuelaId)) {
            return self::NIVELES;
        }

        if ((! $this->documentosCompletos($escuelaId) || ! $this->documentosVigentes($escuelaId)) && ! $this->congeladoPorDevolucion($escuelaId)) {
            return self::DOCUMENTOS;
        }

        return null;
    }

    /**
     * Devolución (ronda cerrada): los documentos generales se consideran satisfechos como al envío — una vigencia que
     * vence o un cambio de tipo de persona no bloquea los pasos reabiertos ni el reenvío —, salvo que el paso
     * `documentos` mismo esté reabierto. Lo leen todas las compuertas por esta clase (CompuertaPaso3, CompuertaSubPaso3,
     * 2.4, los casos de uso del nivel y ResumenTramite::sinCongelar).
     */
    private function congeladoPorDevolucion(int $escuelaId): bool
    {
        return $this->pasosReabiertos->rondaCerrada($escuelaId)
            && ! $this->pasosReabiertos->estaReabierto($escuelaId, self::DOCUMENTOS);
    }

    private function tipoPersona(int $escuelaId): ?string
    {
        return $this->tipoPersonaDeEscuela->ejecutar($escuelaId);
    }
}
