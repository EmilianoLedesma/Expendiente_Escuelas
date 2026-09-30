<?php

namespace Tests\Concerns;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Fixture: deja Paso 2 (responsable + todos los documentos aplicables al tipo de persona (por omisión `fisica`) en vigencia) completo
 * para una escuela, que es la precondición de EstadoPaso2 para seleccionar
 * niveles y para entrar a Paso 3.
 */
trait CompletaPaso2
{
    protected function completarPaso2(int $escuelaId, string $tipoPersona = 'fisica'): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        (new RegistrarResponsableLegal)->ejecutar($escuelaId, new DatosResponsableLegal(
            tipoPersona: $tipoPersona,
            nombre: $tipoPersona === 'moral' ? null : 'Juana Pérez',
            razonSocial: $tipoPersona === 'moral' ? 'Colegio Ejemplo S.C.' : null,
            nombreRepresentanteLegal: $tipoPersona === 'moral' ? 'Miguel Torres' : null,
            gestorNombre: $tipoPersona === 'fisica_con_gestor' ? 'Gestor Uno' : null,
        ));

        $registrar = app(RegistrarDocumento::class);
        foreach (app(DocumentosCompletos::class)->clavesAplicables($tipoPersona) as $clave) {
            $registrar->ejecutar($escuelaId, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }
    }
}
