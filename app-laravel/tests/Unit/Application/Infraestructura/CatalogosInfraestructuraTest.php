<?php

namespace Tests\Unit\Application\Infraestructura;

use App\Application\Infraestructura\CatalogosInfraestructura;
use App\Models\Plantel;
use Database\Seeders\CatalogoMinimoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogosInfraestructuraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new CatalogoMinimoSeeder)->run();
    }

    public function test_sanitarios_capturados_filtra_por_plantel(): void
    {
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $otroPlantel = Plantel::create(['calle' => 'Calle 2', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        DB::table('sanitarios')->insert([
            'plantel_id' => $plantel->id,
            'categoria' => 'alumnado_masculino',
            'cantidad_retretes' => 2,
            'created_at' => now(),
        ]);
        DB::table('sanitarios')->insert([
            'plantel_id' => $otroPlantel->id,
            'categoria' => 'personal_masculino',
            'cantidad_retretes' => 1,
            'created_at' => now(),
        ]);

        $resultado = app(CatalogosInfraestructura::class)->sanitariosCapturados($plantel->id);

        $this->assertCount(1, $resultado);
        $this->assertSame('alumnado_masculino', $resultado->first()->categoria);
    }

    public function test_materiales_biblioteca_disponibles_devuelve_catalogo_completo(): void
    {
        $resultado = app(CatalogosInfraestructura::class)->materialesBibliotecaDisponibles();

        $this->assertGreaterThan(0, $resultado->count());
        $this->assertSame($resultado->pluck('id')->sort()->values()->all(), $resultado->pluck('id')->all());
    }
}
