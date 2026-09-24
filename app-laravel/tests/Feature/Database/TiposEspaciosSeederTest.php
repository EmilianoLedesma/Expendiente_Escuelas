<?php

namespace Tests\Feature\Database;

use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\TiposEspaciosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TiposEspaciosSeederTest extends TestCase
{
    use RefreshDatabase;

    private function sembrar(): void
    {
        (new CatalogoMinimoSeeder)->run();
        (new TiposEspaciosSeeder)->run();
    }

    private function idNivel(string $clave): int
    {
        return (int) DB::table('niveles_educativos')->where('clave', $clave)->value('id');
    }

    /** @return array<int, string> */
    private function clavesPara(string $claveNivel): array
    {
        return DB::table('niveles_tipos_espacios')
            ->join('tipos_espacios', 'tipos_espacios.id', '=', 'niveles_tipos_espacios.tipo_espacio_id')
            ->where('niveles_tipos_espacios.nivel_educativo_id', $this->idNivel($claveNivel))
            ->pluck('tipos_espacios.clave')
            ->all();
    }

    public function test_siembra_las_cuatro_categorias_de_tipos_espacios(): void
    {
        $this->sembrar();

        $categorias = DB::table('tipos_espacios')->distinct()->pluck('categoria')->sort()->values()->all();

        $this->assertSame(['administrativo', 'cubiculo', 'especial', 'recreativo_deportivo'], $categorias);
    }

    public function test_marca_las_banderas_de_extension_solo_donde_corresponde(): void
    {
        $this->sembrar();

        $this->assertTrue((bool) DB::table('tipos_espacios')->where('clave', 'campo_futbol')->value('permite_campo_futbol'));
        $this->assertTrue((bool) DB::table('tipos_espacios')->where('clave', 'biblioteca')->value('permite_material_biblioteca'));
        $this->assertSame(1, DB::table('tipos_espacios')->where('permite_campo_futbol', true)->count());
        $this->assertSame(1, DB::table('tipos_espacios')->where('permite_material_biblioteca', true)->count());
    }

    public function test_filtro_recepcion_solo_aplica_a_inicial(): void
    {
        $this->sembrar();

        $this->assertContains('filtro_recepcion', $this->clavesPara('inicial'));
        $this->assertNotContains('filtro_recepcion', $this->clavesPara('preescolar'));
        $this->assertNotContains('filtro_recepcion', $this->clavesPara('primaria'));
        $this->assertNotContains('filtro_recepcion', $this->clavesPara('secundaria'));
    }

    public function test_biblioteca_solo_aplica_a_primaria_y_secundaria(): void
    {
        $this->sembrar();

        $this->assertNotContains('biblioteca', $this->clavesPara('inicial'));
        $this->assertNotContains('biblioteca', $this->clavesPara('preescolar'));
        $this->assertContains('biblioteca', $this->clavesPara('primaria'));
        $this->assertContains('biblioteca', $this->clavesPara('secundaria'));
    }

    public function test_espacios_exclusivos_de_basica_no_aplican_a_inicial(): void
    {
        $this->sembrar();

        $inicial = $this->clavesPara('inicial');

        foreach (['subdireccion', 'atencion_publico', 'bodega', 'sala_maestros', 'taller', 'laboratorio_polifuncional', 'auditorio', 'sala_artes'] as $clave) {
            $this->assertNotContains($clave, $inicial, "{$clave} no debe aplicar a Inicial");
        }
    }

    public function test_salon_usos_multiples_cocina_comedor_aplican_a_inicial(): void
    {
        $this->sembrar();

        $inicial = $this->clavesPara('inicial');

        foreach (['salon_usos_multiples', 'cocina', 'comedor'] as $clave) {
            $this->assertContains($clave, $inicial, "{$clave} debe aplicar a Inicial (Requisitos Inicial ñ)");
        }
    }

    public function test_ningun_espacio_se_siembra_como_obligatorio(): void
    {
        $this->sembrar();

        $this->assertSame(0, DB::table('niveles_tipos_espacios')->where('obligatorio', true)->count());
    }

    public function test_es_idempotente(): void
    {
        $this->sembrar();
        $tipos = DB::table('tipos_espacios')->count();
        $mapeos = DB::table('niveles_tipos_espacios')->count();

        (new TiposEspaciosSeeder)->run();

        $this->assertSame($tipos, DB::table('tipos_espacios')->count());
        $this->assertSame($mapeos, DB::table('niveles_tipos_espacios')->count());
    }
}
