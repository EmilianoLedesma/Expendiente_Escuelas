<?php

namespace Tests\Feature\Database;

use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * WS-5b: 000003 mueve el Formato a escuela_nivel y, en una BD ya sembrada,
 * agrega los documentos por nivel; 000004 retira recibo_pago_derechos_plantel
 * (O1, compuerta G0). Mismo patrón que TiposDocumentosAplicaPersonaCheckTest:
 * se simula la BD previa y se corre up() a mano.
 */
class DocumentosPorNivelMigracionTest extends TestCase
{
    use RefreshDatabase;

    private function migracion(string $nombre): object
    {
        return require database_path("migrations/{$nombre}.php");
    }

    /** Catálogo tal como lo dejó WS-5a: Formato por escuela, sin filas por nivel. */
    private function catalogoAnterior(): void
    {
        (new TiposDocumentosSeeder)->run();
        DB::table('tipos_documentos')->where('clave', 'formato_solicitud')->update(['ambito' => 'escuela']);
        DB::table('tipos_documentos')
            ->whereIn('clave', ['recibo_pago_derechos', 'acervo_bibliografico_primaria', 'acervo_bibliografico_secundaria', 'inventario_laboratorio'])
            ->delete();
    }

    public function test_up_mueve_el_formato_y_agrega_los_documentos_por_nivel_en_una_bd_ya_sembrada(): void
    {
        $this->catalogoAnterior();

        $this->migracion('2026_01_02_000003_mover_formato_solicitud_a_escuela_nivel')->up();

        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'formato_solicitud', 'ambito' => 'escuela_nivel']);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'recibo_pago_derechos', 'ambito' => 'escuela_nivel']);
        $this->assertDatabaseHas('tipos_documentos', [
            'clave' => 'inventario_laboratorio',
            'nivel_educativo_id' => DB::table('niveles_educativos')->where('clave', 'secundaria')->value('id'),
        ]);
        $ids = DB::table('tipos_documentos')->whereIn('clave', ['formato_solicitud', 'recibo_pago_derechos'])->pluck('id', 'clave');
        $this->assertLessThan($ids['recibo_pago_derechos'], $ids['formato_solicitud'], 'el Formato va primero en el checklist de 2.4');
    }

    public function test_up_es_no_op_con_el_catalogo_vacio(): void
    {
        $this->migracion('2026_01_02_000003_mover_formato_solicitud_a_escuela_nivel')->up();

        $this->assertDatabaseCount('tipos_documentos', 0);
    }

    public function test_up_es_idempotente(): void
    {
        $this->catalogoAnterior();

        $this->migracion('2026_01_02_000003_mover_formato_solicitud_a_escuela_nivel')->up();
        $this->migracion('2026_01_02_000003_mover_formato_solicitud_a_escuela_nivel')->up();

        $this->assertDatabaseCount('tipos_documentos', 16);
    }

    public function test_retiro_borra_las_filas_del_recibo_por_plantel_y_su_tipo(): void
    {
        (new TiposDocumentosSeeder)->run();
        $reciboPlantelId = DB::table('tipos_documentos')->insertGetId([
            'clave' => 'recibo_pago_derechos_plantel', 'nombre' => 'Recibo de pago de derechos',
            'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
        DB::table('documentos_plantel')->insert([
            ['plantel_id' => $plantel->id, 'tipo_documento_id' => $reciboPlantelId, 'archivo_path' => 'plantel/1/recibo.pdf'],
            ['plantel_id' => $plantel->id, 'tipo_documento_id' => DB::table('tipos_documentos')->where('clave', 'plano_inmueble')->value('id'), 'archivo_path' => 'plantel/1/plano.pdf'],
        ]);
        // Fila anómala en otra tabla: sin borrarla, el DELETE del tipo violaría su FK.
        DB::table('documentos_escuela')->insert(['escuela_id' => $escuela->id, 'tipo_documento_id' => $reciboPlantelId, 'archivo_path' => 'escuela/1/recibo.pdf']);

        $this->migracion('2026_01_02_000004_retirar_recibo_pago_derechos_plantel')->up();

        $this->assertDatabaseMissing('tipos_documentos', ['clave' => 'recibo_pago_derechos_plantel']);
        $this->assertDatabaseCount('documentos_plantel', 1);
        $this->assertDatabaseHas('documentos_plantel', ['archivo_path' => 'plantel/1/plano.pdf']);
        $this->assertDatabaseCount('documentos_escuela', 0);
    }

    public function test_retiro_es_no_op_si_el_tipo_ya_no_existe(): void
    {
        (new TiposDocumentosSeeder)->run();

        $this->migracion('2026_01_02_000004_retirar_recibo_pago_derechos_plantel')->up();

        $this->assertDatabaseCount('tipos_documentos', 16);
    }
}
