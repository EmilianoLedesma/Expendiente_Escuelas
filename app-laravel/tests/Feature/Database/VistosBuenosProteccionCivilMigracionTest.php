<?php

namespace Tests\Feature\Database;

use App\Models\DocumentoPlantel;
use App\Models\Plantel;
use App\Models\TipoDocumento;
use App\Models\VistoBuenoProteccionCivil;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Extensión documental del Visto Bueno de Protección Civil y retiro de la caducidad calculada
 * a 30 días (sin fundamento: Reglamento PC Querétaro 2017, art. 46 y cédulas TR-140600-007/-008/-009).
 */
class VistosBuenosProteccionCivilMigracionTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRACION = '2026_10_20_000200_create_vistos_buenos_proteccion_civil';

    private function migracion(): object
    {
        return require database_path('migrations/'.self::MIGRACION.'.php');
    }

    private function documento(?string $emision = null, ?string $vigencia = null, ?int $plantelId = null, string $clave = 'visto_bueno_proteccion_civil'): DocumentoPlantel
    {
        (new TiposDocumentosSeeder)->run();
        $plantelId ??= Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000'])->id;
        $tipo = TipoDocumento::where('clave', $clave)->firstOrFail();

        return DocumentoPlantel::create([
            'plantel_id' => $plantelId, 'tipo_documento_id' => $tipo->id, 'archivo_path' => "{$clave}.pdf",
            'fecha_emision' => $emision, 'fecha_vigencia' => $vigencia,
        ]);
    }

    public function test_el_seeder_ya_no_siembra_vigencia_de_30_dias(): void
    {
        (new TiposDocumentosSeeder)->run();

        $this->assertNull(DB::table('tipos_documentos')->where('clave', 'visto_bueno_proteccion_civil')->value('vigencia_max_dias'));
    }

    public function test_las_columnas_se_guardan_y_se_leen(): void
    {
        $documento = $this->documento();

        VistoBuenoProteccionCivil::create([
            'documento_plantel_id' => $documento->id,
            'tipo_instrumento' => 'otro', 'tipo_instrumento_otro' => 'Opinión técnica',
            'autoridad_emisora' => 'Coordinación Municipal de Protección Civil', 'jurisdiccion_emisora' => 'municipio_queretaro',
            'folio_documento' => 'VB-2026-001', 'clasificacion_riesgo' => 'alto', 'ejercicio' => 2026,
            'tipo_vigencia' => 'fecha_expresa', 'fecha_vencimiento_documento' => '2026-12-31',
            'regimen_normativo' => 'reglamento_pc_queretaro_2017', 'fecha_vencimiento_normativo' => '2026-12-31',
            'condicionantes' => 'Aforo máximo 200 personas.',
        ]);

        $fila = VistoBuenoProteccionCivil::findOrFail($documento->id);
        $this->assertSame('otro', $fila->tipo_instrumento);
        $this->assertSame('Opinión técnica', $fila->tipo_instrumento_otro);
        $this->assertSame('municipio_queretaro', $fila->jurisdiccion_emisora);
        $this->assertSame('alto', $fila->clasificacion_riesgo);
        $this->assertSame(2026, $fila->ejercicio);
        $this->assertSame('2026-12-31', $fila->fecha_vencimiento_normativo);
        $this->assertSame($documento->id, $documento->vistoBuenoProteccionCivil->documento_plantel_id);
    }

    public function test_una_fila_nueva_sin_datos_queda_no_capturada(): void
    {
        $documento = $this->documento();

        DB::table('vistos_buenos_proteccion_civil')->insert(['documento_plantel_id' => $documento->id]);

        $fila = VistoBuenoProteccionCivil::findOrFail($documento->id);
        $this->assertSame('no_capturada', $fila->tipo_vigencia);
        $this->assertNull($fila->clasificacion_riesgo);
        $this->assertNull($fila->fecha_vencimiento_normativo);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function filasQueLosCheckRechazan(): array
    {
        return [
            'fecha_expresa sin fecha' => [['tipo_vigencia' => 'fecha_expresa']],
            'fecha sin fecha_expresa' => [['tipo_vigencia' => 'sin_fecha_expresa', 'fecha_vencimiento_documento' => '2026-12-31']],
            'tipo de vigencia desconocido' => [['tipo_vigencia' => 'cinco_anios']],
            'instrumento desconocido' => [['tipo_instrumento' => 'certificado']],
            'descripción sin instrumento otro' => [['tipo_instrumento' => 'visto_bueno', 'tipo_instrumento_otro' => 'x']],
            'jurisdicción desconocida' => [['jurisdiccion_emisora' => 'corregidora']],
            'riesgo desconocido' => [['clasificacion_riesgo' => 'critico']],
            'vencimiento normativo sin régimen' => [['fecha_vencimiento_normativo' => '2026-12-31']],
            'ejercicio fuera de rango' => [['ejercicio' => 1999]],
        ];
    }

    /** @param  array<string, mixed>  $campos */
    #[DataProvider('filasQueLosCheckRechazan')]
    public function test_los_check_rechazan_filas_incoherentes(array $campos): void
    {
        $documento = $this->documento();

        $this->expectException(QueryException::class);
        DB::table('vistos_buenos_proteccion_civil')->insert(['documento_plantel_id' => $documento->id, ...$campos]);
    }

    public function test_up_retira_la_caducidad_calculada_y_marca_los_historicos_sin_perder_nada(): void
    {
        $historico = $this->documento('2026-09-01', '2026-10-01');
        $otro = $this->documento('2026-09-01', '2026-10-01', $historico->plantel_id, 'plano_inmueble');
        $this->migracion()->down();
        DB::table('documentos_plantel')->where('id', $historico->id)->update(['fecha_vigencia' => '2026-10-01']);

        $this->migracion()->up();

        $fila = VistoBuenoProteccionCivil::findOrFail($historico->id);
        $this->assertSame('no_capturada', $fila->tipo_vigencia);
        $this->assertSame('2026-10-01', $fila->fecha_calculada_30_dias_historica, 'la fecha calculada se conserva, marcada como no verificada');
        $documentoDespues = DocumentoPlantel::findOrFail($historico->id);
        $this->assertNull($documentoDespues->fecha_vigencia, 'la fecha calculada deja de ser un vencimiento');
        $this->assertSame('2026-09-01', $documentoDespues->fecha_emision, 'la emisión no cambia');
        $this->assertSame('visto_bueno_proteccion_civil.pdf', $documentoDespues->archivo_path, 'el PDF no cambia');
        $this->assertNull(DB::table('tipos_documentos')->where('clave', 'visto_bueno_proteccion_civil')->value('vigencia_max_dias'));
        $this->assertSame('2026-10-01', DocumentoPlantel::findOrFail($otro->id)->fecha_vigencia, 'otros documentos no se tocan');
        $this->assertSame(1, VistoBuenoProteccionCivil::count());
    }

    public function test_down_restaura_los_30_dias_y_la_fecha_calculada(): void
    {
        $historico = $this->documento('2026-09-01', '2026-10-01');
        $this->migracion()->down();
        DB::table('documentos_plantel')->where('id', $historico->id)->update(['fecha_vigencia' => '2026-10-01']);
        $this->migracion()->up();
        $nuevo = $this->documento('2026-09-10', null);
        DB::table('vistos_buenos_proteccion_civil')->insert(['documento_plantel_id' => $nuevo->id, 'tipo_vigencia' => 'sin_fecha_expresa']);

        $this->migracion()->down();

        $this->assertFalse(Schema::hasTable('vistos_buenos_proteccion_civil'));
        $this->assertSame(30, DB::table('tipos_documentos')->where('clave', 'visto_bueno_proteccion_civil')->value('vigencia_max_dias'));
        $this->assertSame('2026-10-01', DocumentoPlantel::findOrFail($historico->id)->fecha_vigencia);
        $this->assertSame('2026-10-10', DocumentoPlantel::findOrFail($nuevo->id)->fecha_vigencia, 'sin fecha histórica se restaura emisión + 30 días');
    }

    public function test_up_es_no_op_con_el_catalogo_vacio(): void
    {
        $this->migracion()->down();
        DB::table('documentos_plantel')->delete();
        DB::table('tipos_documentos')->delete();

        $this->migracion()->up();

        $this->assertSame(0, DB::table('vistos_buenos_proteccion_civil')->count());
    }
}
