<?php

namespace App\Domain\Validaciones\Documental;

/**
 * Declarado: what the applicant typed in the wizard (e.g. Paso 2.1).
 * Documento: what a specific uploaded document says, however it was
 * obtained (manual capture today; an extractor later) — rules don't care.
 */
enum OrigenHecho: string
{
    case Declarado = 'declarado';
    case Documento = 'documento';
}
