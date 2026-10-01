<?php

namespace Tests\Unit\Domain\Validaciones\Documental;

use App\Domain\Validaciones\Documental\ContextoValidacion;
use App\Domain\Validaciones\Documental\MotorValidacionDocumental;
use App\Domain\Validaciones\Documental\ReglaDocumental;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use App\Domain\Validaciones\Resultado\ResultadoRegla;
use PHPUnit\Framework\TestCase;

class MotorValidacionDocumentalTest extends TestCase
{
    private function reglaFija(string $clave, EstadoResultado $estado): ReglaDocumental
    {
        return new class($clave, $estado) implements ReglaDocumental
        {
            public function __construct(private string $clave, private EstadoResultado $estado) {}

            public function evaluar(ContextoValidacion $contexto): ResultadoRegla
            {
                return new ResultadoRegla($this->clave, $this->estado, $contexto->tipoPersona);
            }
        };
    }

    public function test_ejecuta_cada_regla_en_orden_y_agrega_sus_resultados(): void
    {
        $motor = new MotorValidacionDocumental([
            $this->reglaFija('a', EstadoResultado::Cumple),
            $this->reglaFija('b', EstadoResultado::NoCumple),
        ]);

        $reporte = $motor->ejecutar(new ContextoValidacion('fisica', [], [], []));

        $this->assertSame(['a', 'b'], array_map(fn (ResultadoRegla $r) => $r->clave, $reporte->resultados));
        $this->assertSame('fisica', $reporte->resultados[0]->mensaje);
        $this->assertTrue($reporte->tieneNoCumplimientos());
    }

    public function test_sin_reglas_produce_reporte_vacio(): void
    {
        $reporte = (new MotorValidacionDocumental([]))->ejecutar(new ContextoValidacion('fisica', [], [], []));

        $this->assertSame(0, $reporte->ejecutadas());
    }
}
