<?php

namespace Tests\Unit\Domain\Validaciones\Engine;

use App\Domain\Validaciones\Engine\EvaluadorMobiliario;
use App\Domain\Validaciones\Engine\LineaMobiliario;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use PHPUnit\Framework\TestCase;

class EvaluadorMobiliarioTest extends TestCase
{
    public function test_cumple_cuando_cada_sala_con_alumnos_tiene_lo_requerido(): void
    {
        $resultado = (new EvaluadorMobiliario)->evaluar(['lactantes_a' => 5], [
            new LineaMobiliario('Cuna con barandal', 'lactantes_a', 'por_alumno_ratio', 2, 3),
            new LineaMobiliario('Mueble para cambio de pañal', 'lactantes_a', 'fijo_por_sala', 1, 1),
        ]);

        $this->assertSame(EstadoResultado::Cumple, $resultado->estado);
        $this->assertSame('inicial.mobiliario', $resultado->clave);
    }

    public function test_senala_cada_faltante_redondeando_el_ratio_hacia_arriba(): void
    {
        // 5 niños, 1 cuna por cada 2 → 3 cunas
        $resultado = (new EvaluadorMobiliario)->evaluar(['lactantes_a' => 5], [
            new LineaMobiliario('Cuna con barandal', 'lactantes_a', 'por_alumno_ratio', 2, 2),
            new LineaMobiliario('Silla porta bebé', 'lactantes_a', 'por_alumno_ratio', 1, null),
        ]);

        $this->assertSame(EstadoResultado::NoCumple, $resultado->estado);
        $this->assertSame([
            ['concepto' => 'Cuna con barandal', 'sala' => 'lactantes_a', 'requerido' => 3, 'declarado' => 2],
            ['concepto' => 'Silla porta bebé', 'sala' => 'lactantes_a', 'requerido' => 5, 'declarado' => 0],
        ], $resultado->detalles['faltantes']);
    }

    public function test_salas_sin_alumnos_no_exigen_mobiliario(): void
    {
        $resultado = (new EvaluadorMobiliario)->evaluar(['lactantes_a' => 0, 'maternal_a' => 10], [
            new LineaMobiliario('Cuna con barandal', 'lactantes_a', 'por_alumno_ratio', 2, null),
            new LineaMobiliario('Mesa infantil', 'maternal_a', 'por_alumno_ratio', 6, 2),
        ]);

        $this->assertSame(EstadoResultado::Cumple, $resultado->estado);
    }

    public function test_los_conceptos_sin_sala_quedan_fuera_y_se_reportan(): void
    {
        $resultado = (new EvaluadorMobiliario)->evaluar(['maternal_a' => 10], [
            new LineaMobiliario('Mesa infantil', null, 'por_alumno_ratio', 6, 0),
        ]);

        $this->assertSame(EstadoResultado::Cumple, $resultado->estado);
        $this->assertSame(['Mesa infantil'], $resultado->detalles['no_evaluados']);
    }

    public function test_sin_matricula_por_sala_no_es_evaluable(): void
    {
        $resultado = (new EvaluadorMobiliario)->evaluar(null, [
            new LineaMobiliario('Cuna con barandal', 'lactantes_a', 'por_alumno_ratio', 2, 3),
        ]);

        $this->assertSame(EstadoResultado::NoEvaluable, $resultado->estado);
    }
}
