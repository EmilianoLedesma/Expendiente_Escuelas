<?php

namespace Tests\Feature;

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

class DocumentoDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_dueno_puede_descargar_un_documento_de_ambito_escuela(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        (new RegistrarDocumento)->ejecutar($escuela->id, 'ine', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'), new DatosDocumento);
        $this->actingAs($solicitante->user);

        $response = $this->get(route('tramite.paso2-documentos.descargar', ['escuela' => $escuela->id, 'clave' => 'ine']));

        $response->assertOk();
    }

    public function test_un_no_dueno_recibe_403(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        (new RegistrarDocumento)->ejecutar($escuela->id, 'ine', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'), new DatosDocumento);
        $otro = Solicitante::factory()->create();
        $this->actingAs($otro->user);

        $response = $this->get(route('tramite.paso2-documentos.descargar', ['escuela' => $escuela->id, 'clave' => 'ine']));

        $response->assertForbidden();
    }

    public function test_404_si_el_documento_no_existe(): void
    {
        (new TiposDocumentosSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $this->actingAs($solicitante->user);

        $response = $this->get(route('tramite.paso2-documentos.descargar', ['escuela' => $escuela->id, 'clave' => 'ine']));

        $response->assertNotFound();
    }
}
