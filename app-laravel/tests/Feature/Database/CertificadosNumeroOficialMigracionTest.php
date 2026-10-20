<?php

namespace Tests\Feature\Database;

use App\Models\CertificadoNumeroOficial;
use App\Models\DocumentoPlantel;
use App\Models\Plantel;
use App\Models\TipoDocumento;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Campos del certificado de número oficial que el documento puede traer (autoridad, folio, número
 * interior, clave catastral). Todos nullable: el formato no está publicado y los registros previos no los tienen.
 */
class CertificadosNumeroOficialMigracionTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRACION = '2026_10_20_000300_extend_certificados_numero_oficial';

    private function migracion(): object
    {
        return require database_path('migrations/'.self::MIGRACION.'.php');
    }

    private function documento(): DocumentoPlantel
    {
        (new TiposDocumentosSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $tipo = TipoDocumento::where('clave', 'certificado_numero_oficial')->firstOrFail();

        return DocumentoPlantel::create(['plantel_id' => $plantel->id, 'tipo_documento_id' => $tipo->id, 'archivo_path' => 'cert.pdf']);
    }

    public function test_las_columnas_nuevas_se_guardan_y_se_leen(): void
    {
        $documento = $this->documento();

        CertificadoNumeroOficial::create([
            'documento_plantel_id' => $documento->id, 'calle' => 'Hidalgo', 'numero_ext' => '12', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000',
            'numero_int' => 'A-3', 'autoridad_emisora' => 'Dirección de Atención a Fraccionamientos e Inmobiliarias', 'folio_certificado' => 'CNO-2026-0001', 'clave_catastral' => '14 01 001 01 001',
        ]);

        $fila = CertificadoNumeroOficial::findOrFail($documento->id);
        $this->assertSame('A-3', $fila->numero_int);
        $this->assertSame('Dirección de Atención a Fraccionamientos e Inmobiliarias', $fila->autoridad_emisora);
        $this->assertSame('CNO-2026-0001', $fila->folio_certificado);
        $this->assertSame('14 01 001 01 001', $fila->clave_catastral);
    }

    public function test_una_fila_anterior_conserva_su_domicilio_y_deja_los_campos_nuevos_en_null(): void
    {
        $documento = $this->documento();
        $this->migracion()->down();
        DB::table('certificados_numero_oficial')->insert(['documento_plantel_id' => $documento->id, 'calle' => 'Hidalgo', 'numero_ext' => 'S/N', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        $this->migracion()->up();

        $fila = CertificadoNumeroOficial::findOrFail($documento->id);
        $this->assertSame('Hidalgo', $fila->calle);
        $this->assertSame('S/N', $fila->numero_ext);
        $this->assertNull($fila->numero_int);
        $this->assertNull($fila->autoridad_emisora);
        $this->assertNull($fila->folio_certificado);
        $this->assertNull($fila->clave_catastral);
    }

    public function test_down_quita_las_columnas_y_conserva_el_domicilio(): void
    {
        $documento = $this->documento();
        DB::table('certificados_numero_oficial')->insert(['documento_plantel_id' => $documento->id, 'calle' => 'Hidalgo', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000', 'numero_int' => 'B']);

        $this->migracion()->down();

        foreach (['numero_int', 'autoridad_emisora', 'folio_certificado', 'clave_catastral'] as $columna) {
            $this->assertFalse(Schema::hasColumn('certificados_numero_oficial', $columna), $columna);
        }
        $this->assertSame('Hidalgo', DB::table('certificados_numero_oficial')->value('calle'));
    }
}
