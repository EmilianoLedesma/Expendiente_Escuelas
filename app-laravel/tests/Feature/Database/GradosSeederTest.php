<?php

namespace Tests\Feature\Database;

use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\GradosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GradosSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_siembra_los_grados_de_basica_escolarizada(): void
    {
        (new CatalogoMinimoSeeder)->run();
        (new GradosSeeder)->run();
        (new GradosSeeder)->run();

        $porNivel = DB::table('grados')
            ->join('niveles_educativos', 'niveles_educativos.id', '=', 'grados.nivel_educativo_id')
            ->selectRaw('niveles_educativos.clave, count(*) as total')
            ->groupBy('niveles_educativos.clave')
            ->pluck('total', 'clave')
            ->map(fn ($total) => (int) $total)
            ->all();

        $this->assertEquals(['preescolar' => 3, 'primaria' => 6, 'secundaria' => 3], array_intersect_key($porNivel, array_flip(['preescolar', 'primaria', 'secundaria'])));
        $this->assertArrayNotHasKey('inicial', $porNivel);
        $this->assertSame(['1°', '2°', '3°', '4°', '5°', '6°'], DB::table('grados')
            ->join('niveles_educativos', 'niveles_educativos.id', '=', 'grados.nivel_educativo_id')
            ->where('niveles_educativos.clave', 'primaria')->orderBy('grados.orden')->pluck('grados.nombre')->all());
    }
}
