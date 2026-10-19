<?php

namespace Tests\Concerns;

use App\Application\Documentos\DTO\DatosDocumento;

/**
 * Dictámenes de Uso de Suelo para pruebas. La vigencia jurídica es la que declara
 * el documento (cédula municipal TR-170100-017), así que "vencido" significa que
 * su fecha de vencimiento declarada ya pasó, no que se emitió hace más de 30 días.
 */
final class DictamenDeEjemplo
{
    public static function vencido(): DatosDocumento
    {
        return self::conVigencia('fecha_expresa', now()->subDay()->toDateString());
    }

    public static function conVigencia(string $tipoVigencia, ?string $fechaVencimiento = null): DatosDocumento
    {
        return new DatosDocumento(
            fechaEmision: now()->subDays(60)->toDateString(),
            autoridadExpide: 'Dirección de Desarrollo Urbano',
            folioDictamen: 'DUS-2026-001',
            giroAutorizado: 'Escuela de educación básica',
            tipoVigencia: $tipoVigencia,
            fechaVencimientoDocumento: $fechaVencimiento,
        );
    }
}
