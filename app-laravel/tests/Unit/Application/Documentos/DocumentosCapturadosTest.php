<?php

namespace Tests\Unit\Application\Documentos;

use App\Application\Documentos\DocumentosCapturados;
use App\Models\DocumentoEscuela;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use App\Models\TipoDocumento;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentosCapturadosTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuela(): Escuela
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    public function test_devuelve_solo_los_documentos_aplicables_ya_capturados(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $tipoIne = TipoDocumento::where('clave', 'ine')->firstOrFail();

        DocumentoEscuela::create([
            'escuela_id' => $escuela->id,
            'tipo_documento_id' => $tipoIne->id,
            'archivo_path' => "escuela/{$escuela->id}/ine-ABC.pdf",
        ]);

        $resultado = app(DocumentosCapturados::class)->paraEscuela($escuela->id, 'fisica');

        $this->assertTrue($resultado->has('ine'));
        $this->assertSame("escuela/{$escuela->id}/ine-ABC.pdf", $resultado['ine']['archivoPath']);
        $this->assertSame('ine-ABC.pdf', $resultado['ine']['nombreArchivo']);
        $this->assertFalse($resultado->has('acta_nacimiento'));
    }

    public function test_no_incluye_claves_no_capturadas(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();

        $resultado = app(DocumentosCapturados::class)->paraEscuela($escuela->id, 'fisica');

        $this->assertCount(0, $resultado);
    }
}
