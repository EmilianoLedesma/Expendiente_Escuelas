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

    public function test_persiste_columnas_nuevas_y_las_deja_nulas_en_filas_anteriores(): void
    {
        (new TiposDocumentosSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $tipo = TipoDocumento::where('clave', 'escritura_inmueble')->firstOrFail();
        $legado = DocumentoPlantel::create(['plantel_id' => $plantel->id, 'tipo_documento_id' => $tipo->id, 'archivo_path' => 'a.pdf']);
        $completo = DocumentoPlantel::create(['plantel_id' => Plantel::create(['calle' => 'Calle 2', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000'])->id, 'tipo_documento_id' => $tipo->id, 'archivo_path' => 'b.pdf']);

        AcreditacionOcupacionLegal::create(['documento_plantel_id' => $legado->id, 'tipo' => 'comodato']);
        $nuevas = [
            'propietario_nombre' => 'Inmobiliaria Norte SA',
            'fecha_inicio_vigencia' => '2025-01-01',
            'vigencia_indeterminada' => true,
            'arrendador_es_propietario' => false,
            'facultades_referencia' => 'Poder 123',
            'ratificacion_fecha' => '2025-01-02',
            'ratificacion_notario_nombre' => 'Lic. Ana Notaria',
            'ratificacion_notaria_numero' => '14',
            'otro_referencia' => 'REF-9',
            'otro_titular' => 'Ayuntamiento',
            'otro_fecha_expedicion' => '2024-12-31',
            'otro_autoridad' => 'Cabildo',
            'otro_derecho' => 'Usufructo',
        ];
        AcreditacionOcupacionLegal::create(['documento_plantel_id' => $completo->id, 'tipo' => 'otro'] + $nuevas);

        $fila = AcreditacionOcupacionLegal::findOrFail($legado->id);
        foreach (array_keys($nuevas) as $columna) {
            $this->assertNull($fila->{$columna}, $columna);
        }

        $fila = AcreditacionOcupacionLegal::findOrFail($completo->id);
        $this->assertTrue($fila->vigencia_indeterminada);
        $this->assertFalse($fila->arrendador_es_propietario);
        $this->assertSame('Inmobiliaria Norte SA', $fila->propietario_nombre);
        $this->assertSame('2025-01-01', $fila->fecha_inicio_vigencia);
        $this->assertSame('Usufructo', $fila->otro_derecho);
    }
}
