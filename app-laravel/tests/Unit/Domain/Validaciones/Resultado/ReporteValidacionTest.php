<?php

namespace Tests\Unit\Domain\Validaciones\Resultado;

use App\Domain\Validaciones\Resultado\EstadoResultado;
use App\Domain\Validaciones\Resultado\ReporteValidacion;
use App\Domain\Validaciones\Resultado\ResultadoRegla;
use PHPUnit\Framework\TestCase;

class ReporteValidacionTest extends TestCase
{
    public function test_cuenta_resultados_por_estado(): void
    {
        $reporte = new ReporteValidacion([
            new ResultadoRegla('a', EstadoResultado::Cumple, 'ok'),
            new ResultadoRegla('b', EstadoResultado::Cumple, 'ok'),
            new ResultadoRegla('c', EstadoResultado::Advertencia, 'revisar'),
            new ResultadoRegla('d', EstadoResultado::NoCumple, 'falta'),
            new ResultadoRegla('e', EstadoResultado::NoEvaluable, 'sin dato'),
        ]);

        $this->assertSame(5, $reporte->ejecutadas());
        $this->assertSame(2, $reporte->contar(EstadoResultado::Cumple));
        $this->assertSame(1, $reporte->contar(EstadoResultado::Advertencia));
        $this->assertSame(1, $reporte->contar(EstadoResultado::NoCumple));
        $this->assertSame(1, $reporte->contar(EstadoResultado::NoEvaluable));
    }

    public function test_tiene_no_cumplimientos_solo_si_hay_algun_no_cumple(): void
    {
        $sinFallas = new ReporteValidacion([
            new ResultadoRegla('a', EstadoResultado::Cumple, 'ok'),
            new ResultadoRegla('b', EstadoResultado::Advertencia, 'revisar'),
            new ResultadoRegla('c', EstadoResultado::NoEvaluable, 'sin dato'),
        ]);
        $conFalla = new ReporteValidacion([
            new ResultadoRegla('a', EstadoResultado::Cumple, 'ok'),
            new ResultadoRegla('d', EstadoResultado::NoCumple, 'falta'),
        ]);

        $this->assertFalse($sinFallas->tieneNoCumplimientos());
        $this->assertTrue($conFalla->tieneNoCumplimientos());
    }

    public function test_resultado_por_clave(): void
    {
        $reporte = new ReporteValidacion([
            new ResultadoRegla('a', EstadoResultado::Cumple, 'ok'),
            new ResultadoRegla('b', EstadoResultado::NoCumple, 'falta', ['faltantes' => ['ine']]),
        ]);

        $this->assertSame(EstadoResultado::NoCumple, $reporte->resultado('b')?->estado);
        $this->assertSame(['faltantes' => ['ine']], $reporte->resultado('b')?->detalles);
        $this->assertNull($reporte->resultado('inexistente'));
    }

    public function test_un_resultado_senala_los_documentos_afectados(): void
    {
        $sinDocumentos = new ResultadoRegla('a', EstadoResultado::Cumple, 'ok');
        $conDocumentos = new ResultadoRegla('b', EstadoResultado::NoCumple, 'falta', [], ['ine', 'constancia_curp']);

        $this->assertSame([], $sinDocumentos->documentos);
        $this->assertSame(['ine', 'constancia_curp'], $conDocumentos->documentos);
    }

    public function test_estados_serializan_a_su_clave_estable(): void
    {
        $this->assertSame(
            ['cumple', 'advertencia', 'no_cumple', 'no_evaluable'],
            array_map(fn (EstadoResultado $e) => $e->value, EstadoResultado::cases()),
        );
    }
}
