<?php

namespace Tests\Unit\Domain\Validaciones\Documental\Reglas;

use App\Domain\Validaciones\Documental\ContextoValidacion;
use App\Domain\Validaciones\Documental\Hecho;
use App\Domain\Validaciones\Documental\Reglas\CurpCoincide;
use App\Domain\Validaciones\Documental\TipoHecho;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use PHPUnit\Framework\TestCase;

class CurpCoincideTest extends TestCase
{
    private const CURP = 'PEGJ800101HQTRML09';

    private function contexto(?string $declarada, ?string $enIne, string $tipoPersona = 'fisica'): ContextoValidacion
    {
        $hechos = [];
        if ($declarada !== null) {
            $hechos[] = Hecho::declarado(TipoHecho::Curp, $declarada);
        }
        if ($enIne !== null) {
            $hechos[] = Hecho::deDocumento(TipoHecho::Curp, $enIne, 'ine');
        }

        return new ContextoValidacion($tipoPersona, $hechos, [], []);
    }

    public function test_cumple_cuando_las_curp_son_iguales(): void
    {
        $resultado = (new CurpCoincide)->evaluar($this->contexto(self::CURP, self::CURP));

        $this->assertSame(EstadoResultado::Cumple, $resultado->estado);
        $this->assertSame('curp_coincide', $resultado->clave);
    }

    public function test_cumple_ignorando_mayusculas_y_espacios_en_bordes(): void
    {
        $resultado = (new CurpCoincide)->evaluar($this->contexto(' pegj800101hqtrml09 ', self::CURP));

        $this->assertSame(EstadoResultado::Cumple, $resultado->estado);
    }

    public function test_no_cumple_cuando_las_curp_difieren(): void
    {
        $resultado = (new CurpCoincide)->evaluar($this->contexto(self::CURP, 'PEGJ800101HQTRML08'));

        $this->assertSame(EstadoResultado::NoCumple, $resultado->estado);
    }

    public function test_no_evaluable_cuando_falta_la_curp_del_documento(): void
    {
        $resultado = (new CurpCoincide)->evaluar($this->contexto(self::CURP, null));

        $this->assertSame(EstadoResultado::NoEvaluable, $resultado->estado);
    }

    public function test_no_evaluable_cuando_no_se_declaro_curp(): void
    {
        $resultado = (new CurpCoincide)->evaluar($this->contexto(null, self::CURP));

        $this->assertSame(EstadoResultado::NoEvaluable, $resultado->estado);
    }

    public function test_no_evaluable_cuando_una_curp_tiene_formato_invalido(): void
    {
        $declaradaInvalida = (new CurpCoincide)->evaluar($this->contexto('PEGJ800101', self::CURP));
        $documentoInvalido = (new CurpCoincide)->evaluar($this->contexto(self::CURP, 'NO ES UNA CURP 1234'));

        $this->assertSame(EstadoResultado::NoEvaluable, $declaradaInvalida->estado);
        $this->assertSame(EstadoResultado::NoEvaluable, $documentoInvalido->estado);
    }

    public function test_no_evaluable_para_persona_moral_y_gestor(): void
    {
        foreach (['moral', 'fisica_con_gestor'] as $tipoPersona) {
            $resultado = (new CurpCoincide)->evaluar($this->contexto(self::CURP, self::CURP, $tipoPersona));

            $this->assertSame(EstadoResultado::NoEvaluable, $resultado->estado, $tipoPersona);
        }
    }
}
