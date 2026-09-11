<?php

namespace Tests\Feature\Application\Documentos;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentosCompletosTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuela(): Escuela
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    public function test_claves_aplicables_para_fisica_incluye_acta_nacimiento_no_escritura_poder(): void
    {
        (new TiposDocumentosSeeder)->run();

        $claves = (new DocumentosCompletos)->clavesAplicables('fisica');

        $this->assertContains('acta_nacimiento', $claves);
        $this->assertNotContains('escritura_poder_facultades', $claves);
        $this->assertCount(6, $claves);
    }

    public function test_claves_aplicables_para_moral_incluye_escritura_poder_no_acta_nacimiento(): void
    {
        (new TiposDocumentosSeeder)->run();

        $claves = (new DocumentosCompletos)->clavesAplicables('moral');

        $this->assertContains('escritura_poder_facultades', $claves);
        $this->assertNotContains('acta_nacimiento', $claves);
    }

    public function test_para_escuela_falso_sin_ningun_documento(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();

        $this->assertFalse((new DocumentosCompletos)->paraEscuela($escuela->id, 'fisica'));
        $this->assertCount(6, (new DocumentosCompletos)->clavesPendientes($escuela->id, 'fisica'));
    }

    public function test_para_escuela_verdadero_cuando_los_6_estan_registrados(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $registrar = new RegistrarDocumento;

        foreach (['ine', 'acta_nacimiento', 'escritura_inmueble', 'dictamen_uso_suelo', 'constancia_seguridad_estructural', 'formato_solicitud'] as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }

        $this->assertTrue((new DocumentosCompletos)->paraEscuela($escuela->id, 'fisica'));
        $this->assertSame([], (new DocumentosCompletos)->clavesPendientes($escuela->id, 'fisica'));
    }
}
