<?php

namespace Tests\Unit\Models;

use App\Models\ConstanciaSeguridadEstructural;
use App\Models\DocumentoPlantel;
use App\Models\Plantel;
use App\Models\TipoDocumento;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConstanciaSeguridadEstructuralTest extends TestCase
{
    use RefreshDatabase;

    public function test_extiende_documento_plantel_con_pk_no_estandar(): void
    {
        (new TiposDocumentosSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $tipo = TipoDocumento::where('clave', 'constancia_seguridad_estructural')->firstOrFail();
        $documento = DocumentoPlantel::create(['plantel_id' => $plantel->id, 'tipo_documento_id' => $tipo->id, 'archivo_path' => 'x.pdf']);

        $constancia = ConstanciaSeguridadEstructural::create([
            'documento_plantel_id' => $documento->id,
            'perito_nombre' => 'Ing. Juan Pérez',
            'perito_cedula_profesional' => '1234567',
            'perito_registro_dro' => 'DRO-100',
            'perito_registro_autoridad' => 'Municipio de Querétaro',
            'perito_registro_vigencia' => '2026-12-31',
        ]);

        $this->assertSame($documento->id, $constancia->documento_plantel_id);
        $this->assertSame($documento->id, $constancia->documentoPlantel->id);
    }
}
