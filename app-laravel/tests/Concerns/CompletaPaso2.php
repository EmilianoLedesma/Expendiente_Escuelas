<?php

namespace Tests\Concerns;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Fixture: deja Paso 2 (responsable + 6 documentos en vigencia) completo
 * para una escuela, que es la precondición de EstadoPaso2 para seleccionar
 * niveles y para entrar a Paso 3.
 */
trait CompletaPaso2
{
    protected function completarPaso2(int $escuelaId): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        (new RegistrarResponsableLegal)->ejecutar($escuelaId, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));

        $registrar = app(RegistrarDocumento::class);
        foreach (['ine', 'acta_nacimiento', 'escritura_inmueble', 'dictamen_uso_suelo', 'constancia_seguridad_estructural', 'formato_solicitud'] as $clave) {
            $registrar->ejecutar($escuelaId, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }
    }
}
