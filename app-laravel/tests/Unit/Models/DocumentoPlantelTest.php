<?php

namespace Tests\Unit\Models;

use App\Models\DocumentoPlantel;
use App\Models\Plantel;
use App\Models\TipoDocumento;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentoPlantelTest extends TestCase
{
    use RefreshDatabase;

    public function test_pertenece_a_un_plantel_y_a_un_tipo_documento(): void
    {
        (new TiposDocumentosSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $tipo = TipoDocumento::where('clave', 'dictamen_uso_suelo')->firstOrFail();

        $documento = DocumentoPlantel::create([
            'plantel_id' => $plantel->id,
            'tipo_documento_id' => $tipo->id,
            'archivo_path' => 'documentos/plantel/1/dictamen_uso_suelo.pdf',
            'fecha_emision' => '2026-09-01',
            'fecha_vigencia' => '2026-10-01',
        ]);

        $this->assertSame($plantel->id, $documento->plantel->id);
        $this->assertSame($tipo->id, $documento->tipoDocumento->id);
        $this->assertSame('pendiente', $documento->estado_validacion);
    }
}
