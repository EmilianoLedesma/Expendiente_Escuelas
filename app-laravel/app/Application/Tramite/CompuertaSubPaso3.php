<?php

namespace App\Application\Tramite;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Models\EscuelaNivel;

/**
 * Same gate every Paso 3 capture use case runs before writing (WS-2.4b): a
 * caller that skips the Livewire CompuertaPaso3 still cannot write out of
 * the wizard's order. Extracted for the sub-steps added after WS-8; the
 * older use cases keep their own private copy.
 */
class CompuertaSubPaso3
{
    public function __construct(
        private readonly EstadoPaso2 $estadoPaso2,
        private readonly EstadoPaso3 $estadoPaso3,
    ) {}

    /** @throws PrecondicionIncumplida */
    public function verificar(EscuelaNivel $escuelaNivel, string $clave): void
    {
        $etapaFaltante = $this->estadoPaso2->etapaFaltante($escuelaNivel->escuela_id);
        if ($etapaFaltante !== null) {
            throw new PrecondicionIncumplida($etapaFaltante, 'Completa el Paso 2 antes de continuar.');
        }

        if (! $this->estadoPaso3->puedeAcceder($escuelaNivel->id, $clave)) {
            throw new PrecondicionIncumplida($clave, 'Completa los pasos anteriores de Paso 3 antes de continuar.');
        }
    }
}
