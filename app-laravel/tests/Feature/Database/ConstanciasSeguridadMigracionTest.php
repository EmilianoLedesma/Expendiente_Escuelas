<?php

namespace Tests\Feature\Database;

use App\Models\ConstanciaSeguridadEstructural;
use App\Models\DocumentoPlantel;
use App\Models\Plantel;
use App\Models\TipoDocumento;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Ampliación de constancias_seguridad_estructural (calidad del profesional, folio,
 * fechas del registro y vigencia declarada). Mismo patrón de CHECK que dictamenes_uso_suelo.
 */
class ConstanciasSeguridadMigracionTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRACION = '2026_10_20_000100_extend_constancias_seguridad_estructural';

    private function migracion(): object
    {
        return require database_path('migrations/'.self::MIGRACION.'.php');
    }

    private function documento(): DocumentoPlantel
    {
        (new TiposDocumentosSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $tipo = TipoDocumento::where('clave', 'constancia_seguridad_estructural')->firstOrFail();

        return DocumentoPlantel::create(['plantel_id' => $plantel->id, 'tipo_documento_id' => $tipo->id, 'archivo_path' => 'c.pdf']);
    }

    public function test_las_columnas_nuevas_se_guardan_y_se_leen(): void
    {
        $documento = $this->documento();

        ConstanciaSeguridadEstructural::create([
            'documento_plantel_id' => $documento->id,
            'perito_nombre' => 'Ing. Juan Pérez',
            'calidad_profesional' => 'otra',
            'calidad_profesional_otra' => 'Perito estructurista externo',
            'folio_constancia' => 'CSE-2026-001',
            'tipo_vigencia' => 'fecha_expresa',
            'vigencia_constancia' => '2031-06-30',
            'perito_registro_fecha' => '2026-02-10',
            'perito_registro_vencimiento' => '2027-02-09',
        ]);

        $fila = ConstanciaSeguridadEstructural::findOrFail($documento->id);
        $this->assertSame('otra', $fila->calidad_profesional);
        $this->assertSame('Perito estructurista externo', $fila->calidad_profesional_otra);
        $this->assertSame('CSE-2026-001', $fila->folio_constancia);
        $this->assertSame('fecha_expresa', $fila->tipo_vigencia);
        $this->assertSame('2026-02-10', $fila->perito_registro_fecha);
        $this->assertSame('2027-02-09', $fila->perito_registro_vencimiento);
    }

    public function test_una_fila_anterior_queda_no_capturada_y_sin_calidad(): void
    {
        $documento = $this->documento();

        DB::table('constancias_seguridad_estructural')->insert(['documento_plantel_id' => $documento->id, 'perito_nombre' => 'Ing. Juan Pérez']);

        $fila = ConstanciaSeguridadEstructural::findOrFail($documento->id);
        $this->assertSame('no_capturada', $fila->tipo_vigencia);
        $this->assertNull($fila->calidad_profesional);
        $this->assertNull($fila->folio_constancia);
    }

    public function test_check_rechaza_fecha_expresa_sin_fecha(): void
    {
        $documento = $this->documento();

        $this->expectException(QueryException::class);
        DB::table('constancias_seguridad_estructural')->insert(['documento_plantel_id' => $documento->id, 'tipo_vigencia' => 'fecha_expresa']);
    }

    public function test_check_rechaza_fecha_sin_fecha_expresa(): void
    {
        $documento = $this->documento();

        $this->expectException(QueryException::class);
        DB::table('constancias_seguridad_estructural')->insert(['documento_plantel_id' => $documento->id, 'tipo_vigencia' => 'sin_fecha_expresa', 'vigencia_constancia' => '2030-01-01']);
    }

    public function test_check_rechaza_calidad_invalida(): void
    {
        $documento = $this->documento();

        $this->expectException(QueryException::class);
        DB::table('constancias_seguridad_estructural')->insert(['documento_plantel_id' => $documento->id, 'calidad_profesional' => 'perito']);
    }

    public function test_check_rechaza_descripcion_de_calidad_sin_otra(): void
    {
        $documento = $this->documento();

        $this->expectException(QueryException::class);
        DB::table('constancias_seguridad_estructural')->insert(['documento_plantel_id' => $documento->id, 'calidad_profesional' => 'dro', 'calidad_profesional_otra' => 'Otro']);
    }

    /** El campo vigencia_constancia ya significaba "fecha que declara la constancia" (migración 2026_10_12_000200). */
    public function test_up_marca_fecha_expresa_en_las_filas_con_vigencia_y_deja_no_capturada_al_resto(): void
    {
        $con = $this->documento();
        $sin = DocumentoPlantel::create(['plantel_id' => $con->plantel_id, 'tipo_documento_id' => TipoDocumento::where('clave', 'plano_inmueble')->value('id'), 'archivo_path' => 'p.pdf']);
        $this->migracion()->down();
        DB::table('constancias_seguridad_estructural')->insert(['documento_plantel_id' => $con->id, 'vigencia_constancia' => '2027-05-01', 'perito_registro_vigencia' => '2025-03-01']);
        DB::table('constancias_seguridad_estructural')->insert(['documento_plantel_id' => $sin->id]);

        $this->migracion()->up();

        $this->assertSame('fecha_expresa', ConstanciaSeguridadEstructural::findOrFail($con->id)->tipo_vigencia);
        $this->assertSame('no_capturada', ConstanciaSeguridadEstructural::findOrFail($sin->id)->tipo_vigencia);
        $this->assertSame('2025-03-01', ConstanciaSeguridadEstructural::findOrFail($con->id)->perito_registro_vigencia, 'el dato histórico no se toca');
    }

    public function test_down_quita_las_columnas_y_conserva_los_datos_anteriores(): void
    {
        $documento = $this->documento();
        DB::table('constancias_seguridad_estructural')->insert(['documento_plantel_id' => $documento->id, 'perito_nombre' => 'Ing. Juan Pérez', 'tipo_vigencia' => 'fecha_expresa', 'vigencia_constancia' => '2027-05-01']);

        $this->migracion()->down();

        $this->assertFalse(Schema::hasColumn('constancias_seguridad_estructural', 'tipo_vigencia'));
        $this->assertFalse(Schema::hasColumn('constancias_seguridad_estructural', 'calidad_profesional'));
        $this->assertSame('2027-05-01', DB::table('constancias_seguridad_estructural')->value('vigencia_constancia'));
    }
}
