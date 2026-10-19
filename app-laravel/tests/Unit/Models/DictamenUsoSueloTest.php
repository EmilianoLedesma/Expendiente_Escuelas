<?php

namespace Tests\Unit\Models;

use App\Models\DictamenUsoSuelo;
use App\Models\DocumentoPlantel;
use App\Models\Plantel;
use App\Models\TipoDocumento;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DictamenUsoSueloTest extends TestCase
{
    use RefreshDatabase;

    private function documentoDictamen(?string $fechaEmision = null, ?string $fechaVigencia = null): DocumentoPlantel
    {
        (new TiposDocumentosSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $tipo = TipoDocumento::where('clave', 'dictamen_uso_suelo')->firstOrFail();

        return DocumentoPlantel::create([
            'plantel_id' => $plantel->id,
            'tipo_documento_id' => $tipo->id,
            'archivo_path' => 'd.pdf',
            'fecha_emision' => $fechaEmision,
            'fecha_vigencia' => $fechaVigencia,
        ]);
    }

    public function test_new_columns_round_trip(): void
    {
        $documento = $this->documentoDictamen();

        DictamenUsoSuelo::create([
            'documento_plantel_id' => $documento->id,
            'autoridad_expide' => 'Dirección de Desarrollo Urbano',
            'folio_dictamen' => 'DUS-1',
            'giro_autorizado' => 'Escuela',
            'tipo_vigencia' => 'fecha_expresa',
            'fecha_vencimiento_documento' => '2030-06-30',
            'condicionantes' => 'Máximo 200 alumnos por turno.',
        ]);

        $fila = DictamenUsoSuelo::findOrFail($documento->id);
        $this->assertSame('fecha_expresa', $fila->tipo_vigencia);
        $this->assertSame('2030-06-30', $fila->fecha_vencimiento_documento);
        $this->assertSame('Máximo 200 alumnos por turno.', $fila->condicionantes);
    }

    public function test_legacy_row_defaults_to_no_capturada(): void
    {
        $documento = $this->documentoDictamen();

        DB::table('dictamenes_uso_suelo')->insert(['documento_plantel_id' => $documento->id, 'autoridad_expide' => 'DDU']);

        $fila = DictamenUsoSuelo::findOrFail($documento->id);
        $this->assertSame('no_capturada', $fila->tipo_vigencia);
        $this->assertNull($fila->fecha_vencimiento_documento);
        $this->assertNull($fila->condicionantes);
    }

    public function test_check_rejects_fecha_expresa_without_date(): void
    {
        $documento = $this->documentoDictamen();

        $this->expectException(QueryException::class);
        DB::table('dictamenes_uso_suelo')->insert(['documento_plantel_id' => $documento->id, 'tipo_vigencia' => 'fecha_expresa']);
    }

    public function test_check_rejects_sin_fecha_expresa_with_date(): void
    {
        $documento = $this->documentoDictamen();

        $this->expectException(QueryException::class);
        DB::table('dictamenes_uso_suelo')->insert(['documento_plantel_id' => $documento->id, 'tipo_vigencia' => 'sin_fecha_expresa', 'fecha_vencimiento_documento' => '2030-01-01']);
    }

    public function test_check_rejects_unknown_tipo_vigencia(): void
    {
        $documento = $this->documentoDictamen();

        $this->expectException(QueryException::class);
        DB::table('dictamenes_uso_suelo')->insert(['documento_plantel_id' => $documento->id, 'tipo_vigencia' => 'perpetua']);
    }

    public function test_data_migration_up_nulls_calculated_dates_and_down_restores_them(): void
    {
        $documento = $this->documentoDictamen('2026-09-01', '2026-10-01');
        DB::table('tipos_documentos')->where('clave', 'dictamen_uso_suelo')->update(['vigencia_max_dias' => 30]);
        $migracion = require base_path('database/migrations/2026_10_19_000300_retirar_vigencia_calculada_del_dictamen.php');

        $migracion->up();

        $this->assertNull(DB::table('tipos_documentos')->where('clave', 'dictamen_uso_suelo')->value('vigencia_max_dias'));
        $this->assertNull(DocumentoPlantel::findOrFail($documento->id)->fecha_vigencia);

        $migracion->down();

        $this->assertSame(30, DB::table('tipos_documentos')->where('clave', 'dictamen_uso_suelo')->value('vigencia_max_dias'));
        $this->assertSame('2026-10-01', DocumentoPlantel::findOrFail($documento->id)->fecha_vigencia);
    }

    public function test_data_migration_does_not_touch_other_documents(): void
    {
        $documento = $this->documentoDictamen();
        $tipoPc = TipoDocumento::where('clave', 'visto_bueno_proteccion_civil')->firstOrFail();
        $pc = DocumentoPlantel::create(['plantel_id' => $documento->plantel_id, 'tipo_documento_id' => $tipoPc->id, 'archivo_path' => 'pc.pdf', 'fecha_emision' => '2026-09-01', 'fecha_vigencia' => '2026-10-01']);

        (require base_path('database/migrations/2026_10_19_000300_retirar_vigencia_calculada_del_dictamen.php'))->up();

        $this->assertSame('2026-10-01', DocumentoPlantel::findOrFail($pc->id)->fecha_vigencia);
        $this->assertSame(30, DB::table('tipos_documentos')->where('clave', 'visto_bueno_proteccion_civil')->value('vigencia_max_dias'));
    }
}
