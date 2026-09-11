<?php

namespace Tests\Unit\Models;

use App\Models\DocumentoEscuela;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use App\Models\TipoDocumento;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentoEscuelaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pertenece_a_una_escuela_y_a_un_tipo_documento(): void
    {
        (new TiposDocumentosSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $tipo = TipoDocumento::where('clave', 'ine')->firstOrFail();

        $documento = DocumentoEscuela::create([
            'escuela_id' => $escuela->id,
            'tipo_documento_id' => $tipo->id,
            'archivo_path' => 'documentos/escuela/1/ine.pdf',
        ]);

        $this->assertSame($escuela->id, $documento->escuela->id);
        $this->assertSame($tipo->id, $documento->tipoDocumento->id);
    }
}
