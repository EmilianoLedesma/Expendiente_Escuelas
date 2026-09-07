<?php

namespace App\Domain\Personal;

/**
 * Define cuándo una fila de `personal` está COMPLETA (lista para que el
 * Motor de Validación la cuente como plantilla real) frente a un borrador
 * a medio llenar — desde que `personal` admite filas parciales para el
 * wizard resumible. Clase pura: sin Eloquent, sin DB.
 *
 * Una cadena vacía no cuenta como valor presente: un campo de texto
 * capturado y luego vaciado sigue siendo un borrador, no un dato real.
 */
class RegistroPersonalCompleto
{
    public function esCompleto(
        ?int $cargoPuestoId,
        ?string $nombre,
        ?string $nacionalidad,
        ?string $sexo,
        ?string $estudios,
        ?string $cedulaODocumento,
    ): bool {
        return $cargoPuestoId !== null
            && $this->presente($nombre)
            && $this->presente($nacionalidad)
            && $this->presente($sexo)
            && $this->presente($estudios)
            && $this->presente($cedulaODocumento);
    }

    private function presente(?string $valor): bool
    {
        return $valor !== null && $valor !== '';
    }
}
