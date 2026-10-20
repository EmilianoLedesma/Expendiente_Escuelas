<?php

namespace Tests\Feature\Database;

use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El visto bueno de Protección Civil vence a los 30 días. TiposDocumentosSeeder
 * usa insertOrIgnore, así que una BD ya sembrada conserva vigencia_max_dias NULL:
 * la migración la actualiza. Mismo patrón que DocumentosPorNivelMigracionTest.
 */
class VigenciaProteccionCivilMigracionTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRACION = '2026_10_12_000000_vigencia_proteccion_civil';

    private function migracion(): object
    {
        return require database_path('migrations/'.self::MIGRACION.'.php');
    }

    private function vigencia(): ?int
    {
        return DB::table('tipos_documentos')->where('clave', 'visto_bueno_proteccion_civil')->value('vigencia_max_dias');
    }

    public function test_up_fija_30_dias_en_una_bd_ya_sembrada(): void
    {
        (new TiposDocumentosSeeder)->run();
        DB::table('tipos_documentos')->where('clave', 'visto_bueno_proteccion_civil')->update(['vigencia_max_dias' => null]);

        $this->migracion()->up();

        $this->assertSame(30, $this->vigencia());
    }

    public function test_up_es_no_op_con_el_catalogo_vacio(): void
    {
        $this->migracion()->up();

        $this->assertDatabaseCount('tipos_documentos', 0);
    }

    public function test_down_restaura_null(): void
    {
        (new TiposDocumentosSeeder)->run();

        $this->migracion()->down();

        $this->assertNull($this->vigencia());
    }

    /** Los 30 días se retiraron con 2026_10_20_000200: una BD nueva ya no los siembra. */
    public function test_el_seeder_ya_no_siembra_30_dias_en_una_bd_nueva(): void
    {
        (new TiposDocumentosSeeder)->run();

        $this->assertNull($this->vigencia());
    }
}
