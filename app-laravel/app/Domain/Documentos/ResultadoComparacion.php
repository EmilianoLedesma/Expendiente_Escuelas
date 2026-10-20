<?php

namespace App\Domain\Documentos;

/**
 * Resultado de contrastar dos domicilios. Una diferencia de cadenas no es una discrepancia jurídica:
 * el comparador solo acredita coincidencia o dice que la evidencia no alcanza.
 */
enum ResultadoComparacion: string
{
    /** Todas las partes comparables coinciden una vez normalizadas. */
    case CoincidenciaAcreditada = 'coincidencia_acreditada';

    /** Difieren o faltan partes: no se puede concluir nada. Requiere revisión humana. */
    case EvidenciaInsuficiente = 'evidencia_insuficiente';

    /** Solo puede originarla una decisión humana autorizada (SEDEQ); el comparador nunca la devuelve. */
    case DiscrepanciaConfirmada = 'discrepancia_confirmada';
}
