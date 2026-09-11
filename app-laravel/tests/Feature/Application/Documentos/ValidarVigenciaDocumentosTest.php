<?php

namespace Tests\Feature\Application\Documentos;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\Documentos\ValidarVigenciaDocumentos;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ValidarVigenciaDocumentosTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuela(): Escuela
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    public function test_sin_violaciones_cuando_no_hay_documentos(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();

        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id));
    }

    public function test_detecta_dictamen_de_uso_de_suelo_vencido(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        (new RegistrarDocumento)->ejecutar($escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'), new DatosDocumento(
            fechaEmision: now()->subDays(60)->toDateString(),
        ));

        $violaciones = (new ValidarVigenciaDocumentos)->ejecutar($escuela->id);

        $this->assertNotEmpty($violaciones);
        $this->assertStringContainsString('Dictamen de Uso de Suelo', $violaciones['dictamen_uso_suelo']);
    }

    public function test_acepta_dictamen_de_uso_de_suelo_vigente(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        (new RegistrarDocumento)->ejecutar($escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'), new DatosDocumento(
            fechaEmision: now()->subDays(5)->toDateString(),
        ));

        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id));
    }

    public function test_detecta_perito_registro_ano_distinto_de_emision(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        (new RegistrarDocumento)->ejecutar($escuela->id, 'constancia_seguridad_estructural', UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'), new DatosDocumento(
            fechaEmision: '2026-01-15',
            peritoRegistroVigencia: '2025-01-01',
        ));

        $violaciones = (new ValidarVigenciaDocumentos)->ejecutar($escuela->id);

        $this->assertNotEmpty($violaciones);
        $this->assertStringContainsString('Constancia de Seguridad Estructural', $violaciones['constancia_seguridad_estructural']);
    }
}
