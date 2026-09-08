<?php

namespace Tests\Unit\Domain\Validaciones\Regla;

use App\Domain\Validaciones\Regla\CalculadoraRequerimiento;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CalculadoraRequerimientoTest extends TestCase
{
    private CalculadoraRequerimiento $calculadora;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculadora = new CalculadoraRequerimiento;
    }

    #[DataProvider('proporcionalAbajoProvider')]
    public function test_personal_proporcional_redondeo_abajo(int $magnitud, int $esperado): void
    {
        $requerido = $this->calculadora->calcular(
            tipoCalculo: 'personal_proporcional',
            valorNumerico: 60,
            condicionMin: null,
            redondeo: 'abajo',
            magnitud: $magnitud,
        );

        $this->assertSame($esperado, $requerido);
    }

    public static function proporcionalAbajoProvider(): array
    {
        return [
            '59 -> 0' => [59, 0],
            '60 -> 1' => [60, 1],
            '61 -> 1' => [61, 1],
            '119 -> 1' => [119, 1],
            '120 -> 2' => [120, 2],
            '121 -> 2' => [121, 2],
        ];
    }

    #[DataProvider('proporcionalArribaLactantesProvider')]
    public function test_personal_proporcional_redondeo_arriba_lactantes(int $magnitud, int $esperado): void
    {
        $requerido = $this->calculadora->calcular(
            tipoCalculo: 'personal_proporcional',
            valorNumerico: 5,
            condicionMin: null,
            redondeo: 'arriba',
            magnitud: $magnitud,
        );

        $this->assertSame($esperado, $requerido);
    }

    public static function proporcionalArribaLactantesProvider(): array
    {
        return [
            '4 -> 1' => [4, 1],
            '5 -> 1' => [5, 1],
            '6 -> 2' => [6, 2],
            '10 -> 2' => [10, 2],
            '11 -> 3' => [11, 3],
        ];
    }

    #[DataProvider('proporcionalArribaMaternalesProvider')]
    public function test_personal_proporcional_redondeo_arriba_maternales(int $magnitud, int $esperado): void
    {
        $requerido = $this->calculadora->calcular(
            tipoCalculo: 'personal_proporcional',
            valorNumerico: 10,
            condicionMin: null,
            redondeo: 'arriba',
            magnitud: $magnitud,
        );

        $this->assertSame($esperado, $requerido);
    }

    public static function proporcionalArribaMaternalesProvider(): array
    {
        return [
            '9 -> 1' => [9, 1],
            '10 -> 1' => [10, 1],
            '11 -> 2' => [11, 2],
        ];
    }

    #[DataProvider('umbralProvider')]
    public function test_personal_umbral(int $magnitud, int $esperado): void
    {
        $requerido = $this->calculadora->calcular(
            tipoCalculo: 'personal_umbral',
            valorNumerico: 1,
            condicionMin: 61,
            redondeo: 'na',
            magnitud: $magnitud,
        );

        $this->assertSame($esperado, $requerido);
    }

    public static function umbralProvider(): array
    {
        return [
            '59 -> 0' => [59, 0],
            '60 -> 0' => [60, 0],
            '61 -> 1' => [61, 1],
        ];
    }

    public function test_personal_obligatorio_ignores_magnitud(): void
    {
        $requerido = $this->calculadora->calcular(
            tipoCalculo: 'personal_obligatorio',
            valorNumerico: 1,
            condicionMin: null,
            redondeo: 'na',
            magnitud: 0,
        );

        $this->assertSame(1, $requerido);
    }

    public function test_personal_por_espacio_multiplies_by_magnitud(): void
    {
        $requerido = $this->calculadora->calcular(
            tipoCalculo: 'personal_por_espacio',
            valorNumerico: 1,
            condicionMin: null,
            redondeo: 'na',
            magnitud: 3,
        );

        $this->assertSame(3, $requerido);
    }

    public function test_ratio_por_alumno_multiplies_valor_by_magnitud(): void
    {
        $requerido = $this->calculadora->calcular(
            tipoCalculo: 'ratio_por_alumno',
            valorNumerico: 2.5,
            condicionMin: null,
            redondeo: 'na',
            magnitud: 40,
        );

        $this->assertSame(100.0, $requerido);
    }

    public function test_minimo_fijo_ignores_magnitud(): void
    {
        $requerido = $this->calculadora->calcular(
            tipoCalculo: 'minimo_fijo',
            valorNumerico: 25,
            condicionMin: null,
            redondeo: 'na',
            magnitud: 999,
        );

        $this->assertSame(25.0, $requerido);
    }

    /**
     * Guard: `$magnitud >= $condicionMin` with a null $condicionMin coerces
     * null to 0 in PHP, so an umbral rule with a missing threshold would
     * fire unconditionally instead of failing loudly. personal_umbral is
     * meaningless without a threshold, so this must throw, not silently
     * evaluate to "always required".
     */
    public function test_personal_umbral_throws_when_condicion_min_is_null(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculadora->calcular(
            tipoCalculo: 'personal_umbral',
            valorNumerico: 1,
            condicionMin: null,
            redondeo: 'na',
            magnitud: 61,
        );
    }

    /**
     * Guard: personal_por_espacio multiplies magnitud (an existing-space
     * count) by valor_numerico (headcount per space) — both are meant to be
     * whole numbers, so any non-integer product signals malformed input
     * upstream (e.g. magnitud accidentally holding a ratio instead of a
     * count). Silently truncating with (int) would mask that bug instead
     * of surfacing it.
     */
    public function test_personal_por_espacio_throws_on_non_integer_product(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculadora->calcular(
            tipoCalculo: 'personal_por_espacio',
            valorNumerico: 1.5,
            condicionMin: null,
            redondeo: 'na',
            magnitud: 3,
        );
    }
}
