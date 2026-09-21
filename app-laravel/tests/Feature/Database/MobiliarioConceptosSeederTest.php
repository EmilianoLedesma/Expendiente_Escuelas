<?php

namespace Tests\Feature\Database;

use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\MobiliarioConceptosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MobiliarioConceptosSeederTest extends TestCase
{
    use RefreshDatabase;

    private function sembrar(): void
    {
        (new CatalogoMinimoSeeder)->run();
        (new MobiliarioConceptosSeeder)->run();
    }

    private function idSala(string $clave): int
    {
        return (int) DB::table('salas')->where('clave', $clave)->value('id');
    }

    public function test_siembra_las_41_filas_del_catalogo_de_inicial(): void
    {
        $this->sembrar();

        $this->assertSame(41, DB::table('mobiliario_conceptos')->count());
    }

    public function test_cada_sala_recibe_su_numero_de_conceptos(): void
    {
        $this->sembrar();

        $esperado = [
            'lactantes_a' => 6,
            'lactantes_b' => 9,
            'lactantes_c' => 7,
            'maternal_a' => 8,
            'maternal_b' => 8,
        ];

        foreach ($esperado as $clave => $cuantos) {
            $this->assertSame(
                $cuantos,
                DB::table('mobiliario_conceptos')->where('sala_id', $this->idSala($clave))->count(),
                "sala {$clave}"
            );
        }
    }

    public function test_los_conceptos_de_sala_de_usos_multiples_no_tienen_sala(): void
    {
        $this->sembrar();

        $conceptos = DB::table('mobiliario_conceptos')->whereNull('sala_id')->pluck('nombre')->all();

        $this->assertCount(3, $conceptos);
        $this->assertContains('Silla infantil con cinturón (lactantes)', $conceptos);
        $this->assertContains('Silla infantil (maternal)', $conceptos);
        $this->assertContains('Mesa infantil', $conceptos);
    }

    public function test_los_ratios_por_alumno_conservan_su_divisor(): void
    {
        $this->sembrar();

        $cuna = DB::table('mobiliario_conceptos')
            ->where('sala_id', $this->idSala('lactantes_a'))
            ->where('nombre', 'Cuna con barandal')
            ->first();

        $this->assertSame('por_alumno_ratio', $cuna->tipo_ratio);
        $this->assertEquals(2.0, (float) $cuna->valor_ratio);

        $mesa = DB::table('mobiliario_conceptos')
            ->where('sala_id', $this->idSala('maternal_a'))
            ->where('nombre', 'Mesa infantil')
            ->first();

        $this->assertSame('por_alumno_ratio', $mesa->tipo_ratio);
        $this->assertEquals(6.0, (float) $mesa->valor_ratio);
    }

    public function test_lactantes_b_tambien_requiere_cuna_con_barandal(): void
    {
        $this->sembrar();

        $this->assertTrue(
            DB::table('mobiliario_conceptos')
                ->where('sala_id', $this->idSala('lactantes_b'))
                ->where('nombre', 'Cuna con barandal')
                ->exists()
        );
    }

    public function test_el_material_didactico_declara_su_cantidad_no_cuantificada(): void
    {
        $this->sembrar();

        $filas = DB::table('mobiliario_conceptos')->where('nombre', 'Material didáctico adecuado a la edad')->get();

        $this->assertCount(4, $filas); // lactantes_b, lactantes_c, maternal_a, maternal_b
        foreach ($filas as $fila) {
            $this->assertSame('fijo_por_sala', $fila->tipo_ratio);
            $this->assertStringContainsString('no cuantificada', (string) $fila->fuente);
        }
    }

    public function test_es_idempotente(): void
    {
        $this->sembrar();

        (new MobiliarioConceptosSeeder)->run();

        $this->assertSame(41, DB::table('mobiliario_conceptos')->count());
    }
}
