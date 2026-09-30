<?php

namespace Tests\Unit\Domain\Validaciones\Documental\Reglas;

use App\Domain\Validaciones\Documental\ContextoValidacion;
use App\Domain\Validaciones\Documental\Hecho;
use App\Domain\Validaciones\Documental\NormalizadorNombre;
use App\Domain\Validaciones\Documental\Reglas\NombreTitularCoincide;
use App\Domain\Validaciones\Documental\TipoHecho;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use PHPUnit\Framework\TestCase;

class NombreTitularCoincideTest extends TestCase
{
    private function regla(): NombreTitularCoincide
    {
        return new NombreTitularCoincide(new NormalizadorNombre);
    }

    private function contexto(?string $declarado, ?string $enIne, string $tipoPersona = 'fisica'): ContextoValidacion
    {
        $hechos = [];
        if ($declarado !== null) {
            $hechos[] = Hecho::declarado(TipoHecho::NombreTitular, $declarado);
        }
        if ($enIne !== null) {
            $hechos[] = Hecho::deDocumento(TipoHecho::NombreTitular, $enIne, 'ine');
        }

        return new ContextoValidacion($tipoPersona, $hechos, [], []);
    }

    public function test_cumple_cuando_los_nombres_son_identicos(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto('JUAN PEREZ GOMEZ', 'JUAN PEREZ GOMEZ'));

        $this->assertSame(EstadoResultado::Cumple, $resultado->estado);
        $this->assertSame('nombre_titular_coincide', $resultado->clave);
    }

    public function test_cumple_ignorando_acentos_mayusculas_y_espacios(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto('  juan  Pérez gómez', 'JUAN PEREZ GOMEZ'));

        $this->assertSame(EstadoResultado::Cumple, $resultado->estado);
        $this->assertFalse($resultado->detalles['orden_distinto']);
    }

    public function test_cumple_con_apellidos_primero_como_imprime_la_ine(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto('Juan Pérez Gómez', 'PEREZ GOMEZ JUAN'));

        $this->assertSame(EstadoResultado::Cumple, $resultado->estado);
        $this->assertTrue($resultado->detalles['orden_distinto']);
    }

    public function test_advierte_sin_reprobar_cuando_los_nombres_difieren(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto('Juan Pérez', 'MARIA LOPEZ'));

        $this->assertSame(EstadoResultado::Advertencia, $resultado->estado);
        $this->assertSame(['JUAN', 'PEREZ'], $resultado->detalles['solo_declarado']);
        $this->assertSame(['MARIA', 'LOPEZ'], $resultado->detalles['solo_documento']);
    }

    public function test_advierte_cuando_falta_un_segundo_nombre(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto('Juan Pérez Gómez', 'PEREZ GOMEZ JUAN CARLOS'));

        $this->assertSame(EstadoResultado::Advertencia, $resultado->estado);
        $this->assertSame([], $resultado->detalles['solo_declarado']);
        $this->assertSame(['CARLOS'], $resultado->detalles['solo_documento']);
    }

    public function test_un_token_repetido_no_cuenta_como_coincidencia(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto('Juan Juan Pérez', 'JUAN PEREZ PEREZ'));

        $this->assertSame(EstadoResultado::Advertencia, $resultado->estado);
    }

    public function test_no_evaluable_cuando_falta_el_hecho_del_documento(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto('Juan Pérez', null));

        $this->assertSame(EstadoResultado::NoEvaluable, $resultado->estado);
    }

    public function test_no_evaluable_cuando_falta_el_nombre_declarado(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto(null, 'JUAN PEREZ'));

        $this->assertSame(EstadoResultado::NoEvaluable, $resultado->estado);
    }

    public function test_no_evaluable_para_persona_moral_y_gestor_mientras_no_se_decida_de_quien_es_la_ine(): void
    {
        foreach (['moral', 'fisica_con_gestor'] as $tipoPersona) {
            $resultado = $this->regla()->evaluar($this->contexto('Juan Pérez', 'JUAN PEREZ', $tipoPersona));

            $this->assertSame(EstadoResultado::NoEvaluable, $resultado->estado, $tipoPersona);
            $this->assertStringContainsString('PENDIENTE-motor-validacion-hechos', $resultado->mensaje);
        }
    }
}
