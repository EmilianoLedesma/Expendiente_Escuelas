<?php

namespace Tests\Concerns;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Fixture: responsable legal + todos los documentos generales aplicables al tipo de persona (por omisión
 * `fisica`) en vigencia, para una escuela.
 *
 * Desde 2026-10-20 los documentos generales exigen al menos un nivel (EstadoPaso2::puedeCapturarDocumentosGenerales).
 * Si la escuela aún no tiene niveles, el fixture crea uno temporal para poder subir los documentos y lo quita al
 * terminar: el resultado es el estado histórico «documentos completos sin niveles» (EstadoPaso2::NIVELES pendiente),
 * y la prueba agrega después los niveles que necesite. Si la escuela ya tiene niveles, se conservan y Paso 2
 * queda satisfecho.
 */
trait CompletaPaso2
{
    protected function completarPaso2(int $escuelaId, string $tipoPersona = 'fisica'): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        (new CatalogoMinimoSeeder)->run();
        (new RegistrarResponsableLegal)->ejecutar($escuelaId, new DatosResponsableLegal(
            tipoPersona: $tipoPersona,
            nombre: $tipoPersona === 'moral' ? null : 'Juana Pérez',
            razonSocial: $tipoPersona === 'moral' ? 'Colegio Ejemplo S.C.' : null,
            nombreRepresentanteLegal: $tipoPersona === 'moral' ? 'Miguel Torres' : null,
            gestorNombre: $tipoPersona === 'fisica_con_gestor' ? 'Gestor Uno' : null,
        ));

        $temporal = EscuelaNivel::where('escuela_id', $escuelaId)->exists() ? null : EscuelaNivel::create([
            'escuela_id' => $escuelaId,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'preescolar')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);

        $registrar = app(RegistrarDocumento::class);
        foreach (app(DocumentosCompletos::class)->clavesAplicables($tipoPersona) as $clave) {
            $registrar->ejecutar($escuelaId, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }

        $temporal?->delete();
    }

    /**
     * Elige un nivel (sin duplicarlo) por la vía directa del fixture: el estado mínimo para subir documentos
     * generales desde 2026-10-20 (responsable + al menos un nivel).
     */
    protected function elegirNivel(int $escuelaId, string $clave = 'primaria'): EscuelaNivel
    {
        (new CatalogoMinimoSeeder)->run();

        return EscuelaNivel::firstOrCreate(
            ['escuela_id' => $escuelaId, 'nivel_educativo_id' => NivelEducativo::where('clave', $clave)->value('id')],
            ['estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'), 'tipo_tramite' => 'alta_nueva'],
        );
    }
}
