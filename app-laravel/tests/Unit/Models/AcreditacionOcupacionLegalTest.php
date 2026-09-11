<?php

namespace Tests\Unit\Models;

use App\Models\AcreditacionOcupacionLegal;
use App\Models\DocumentoPlantel;
use App\Models\Plantel;
use App\Models\TipoDocumento;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcreditacionOcupacionLegalTest extends TestCase
{
    use RefreshDatabase;

    public function test_extiende_documento_plantel_con_pk_no_estandar(): void
    {
        (new TiposDocumentosSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $tipo = TipoDocumento::where('clave', 'escritura_inmueble')->firstOrFail();
        $documento = DocumentoPlantel::create(['plantel_id' => $plantel->id, 'tipo_documento_id' => $tipo->id, 'archivo_path' => 'x.pdf']);

        $acreditacion = AcreditacionOcupacionLegal::create([
            'documento_plantel_id' => $documento->id,
            'tipo' => 'escritura_publica',
            'numero_escritura' => 'E-500',
            'notario_nombre' => 'Lic. Ana Notaria',
        ]);

        $this->assertSame($documento->id, $acreditacion->documento_plantel_id);
        $this->assertSame('escritura_publica', $acreditacion->tipo);
    }
}
