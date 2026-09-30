<?php

namespace Tests\Concerns;

use App\Application\Documentos\DocumentosNivelCompletos;
use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\EscuelaNiveles\RegistrarDatosNivel;
use App\Models\EscuelaNivel;
use Illuminate\Http\UploadedFile;

/**
 * Fixture: deja Paso 2.4 (turno, tipo de alumnado y todos los documentos
 * aplicables al nivel) completo para un escuela_nivel — la precondición de
 * EstadoPaso24 para entrar a Paso 3. Catalog-driven como CompletaPaso2.
 * Requiere Paso 2 completo (completarPaso2, que además finge el disco
 * 'documentos'): RegistrarDatosNivel y RegistrarDocumento lo exigen.
 */
trait CompletaPaso24
{
    protected function completarPaso24(int $escuelaNivelId): void
    {
        $escuelaId = EscuelaNivel::findOrFail($escuelaNivelId)->escuela_id;
        app(RegistrarDatosNivel::class)->ejecutar($escuelaNivelId, 'matutino', 'mixto');

        $registrar = app(RegistrarDocumento::class);
        foreach (app(DocumentosNivelCompletos::class)->clavesAplicables($escuelaNivelId) as $clave) {
            $datos = $clave === 'recibo_pago_derechos'
                ? new DatosDocumento(folio: 'F-0001', monto: '1500.00', fechaPago: now()->toDateString())
                : new DatosDocumento;

            $registrar->ejecutar($escuelaId, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), $datos, $escuelaNivelId);
        }
    }
}
