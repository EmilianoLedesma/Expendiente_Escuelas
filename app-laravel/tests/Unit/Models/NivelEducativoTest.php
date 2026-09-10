<?php

namespace Tests\Unit\Models;

use App\Models\NivelEducativo;
use Database\Seeders\CatalogoMinimoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NivelEducativoTest extends TestCase
{
    use RefreshDatabase;

    public function test_educacion_basica_devuelve_solo_los_4_niveles_en_orden(): void
    {
        (new CatalogoMinimoSeeder)->run();

        $claves = NivelEducativo::educacionBasica()->pluck('clave')->all();

        $this->assertSame(['inicial', 'preescolar', 'primaria', 'secundaria'], $claves);
    }
}
