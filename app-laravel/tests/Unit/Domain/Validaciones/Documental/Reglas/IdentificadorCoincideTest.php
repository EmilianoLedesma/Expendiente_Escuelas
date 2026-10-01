<?php

namespace Tests\Unit\Domain\Validaciones\Documental\Reglas;

use App\Domain\Validaciones\Documental\ContextoValidacion;
use App\Domain\Validaciones\Documental\Hecho;
use App\Domain\Validaciones\Documental\Reglas\IdentificadorCoincide;
use App\Domain\Validaciones\Documental\TipoHecho;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use PHPUnit\Framework\TestCase;

class IdentificadorCoincideTest extends TestCase
{
    private const CURP = 'PEGJ800101HQTRML09';

    private const OTRA = 'PEGJ800101HQTRML08';

    private function regla(): IdentificadorCoincide
    {
        return IdentificadorCoincide::curp(['ine', 'constancia_curp']);
    }

    /** @param array<string, string|null> $documentos */
    private function contexto(?string $declarada, array $documentos): ContextoValidacion
    {
        $hechos = $declarada === null ? [] : [Hecho::declarado(TipoHecho::Curp, $declarada)];
        foreach ($documentos as $clave => $valor) {
            if ($valor !== null) {
                $hechos[] = Hecho::deDocumento(TipoHecho::Curp, $valor, $clave);
            }
        }

        return new ContextoValidacion('fisica', $hechos, array_keys($documentos), array_keys($documentos));
    }

    public function test_cumple_cuando_declarada_y_documentos_son_iguales(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto(self::CURP, ['ine' => self::CURP, 'constancia_curp' => ' pegj800101hqtrml09 ']));

        $this->assertSame(EstadoResultado::Cumple, $resultado->estado);
        $this->assertSame('curp_coincide', $resultado->clave);
    }

    public function test_no_cumple_y_senala_el_documento_distinto_a_lo_declarado(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto(self::CURP, ['ine' => self::CURP, 'constancia_curp' => self::OTRA]));

        $this->assertSame(EstadoResultado::NoCumple, $resultado->estado);
        $this->assertSame(['constancia_curp'], $resultado->documentos);
    }

    public function test_sin_declarada_compara_los_documentos_entre_si(): void
    {
        $iguales = $this->regla()->evaluar($this->contexto(null, ['ine' => self::CURP, 'constancia_curp' => self::CURP]));
        $distintos = $this->regla()->evaluar($this->contexto(null, ['ine' => self::CURP, 'constancia_curp' => self::OTRA]));

        $this->assertSame(EstadoResultado::Cumple, $iguales->estado);
        $this->assertSame(EstadoResultado::NoCumple, $distintos->estado);
        // With nothing declared there is no way to tell which one is wrong.
        $this->assertSame(['ine', 'constancia_curp'], $distintos->documentos);
    }

    public function test_sin_declarada_y_un_solo_documento_no_hay_con_que_comparar(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto(null, ['ine' => self::CURP]));

        $this->assertSame(EstadoResultado::NoEvaluable, $resultado->estado);
    }

    public function test_declarada_con_formato_invalido_se_ignora_como_referencia(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto('PEGJ80', ['ine' => self::CURP, 'constancia_curp' => self::CURP]));

        $this->assertSame(EstadoResultado::Cumple, $resultado->estado);
        $this->assertTrue($resultado->detalles['declarado_invalido']);
    }

    public function test_documento_con_formato_invalido_no_cumple(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto(self::CURP, ['ine' => 'NO ES CURP']));

        $this->assertSame(EstadoResultado::NoCumple, $resultado->estado);
        $this->assertSame(['ine'], $resultado->documentos);
        $this->assertSame('formato_invalido', $resultado->detalles['documentos']['ine']['estado']);
    }

    public function test_documento_subido_sin_sus_datos_no_cumple(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto(self::CURP, ['ine' => null]));

        $this->assertSame(EstadoResultado::NoCumple, $resultado->estado);
        $this->assertSame(['ine'], $resultado->documentos);
    }

    public function test_rfc_usa_su_propio_formato(): void
    {
        $regla = IdentificadorCoincide::rfc(['constancia_situacion_fiscal']);
        $contexto = new ContextoValidacion('fisica', [
            Hecho::declarado(TipoHecho::Rfc, 'PEGJ800101AB1'),
            Hecho::deDocumento(TipoHecho::Rfc, 'pegj800101ab1', 'constancia_situacion_fiscal'),
        ], ['constancia_situacion_fiscal'], ['constancia_situacion_fiscal']);
        $moral = new ContextoValidacion('moral', [
            Hecho::declarado(TipoHecho::Rfc, 'CEA010101AB1'),
            Hecho::deDocumento(TipoHecho::Rfc, 'CEA010101AB2', 'constancia_situacion_fiscal'),
        ], ['constancia_situacion_fiscal'], ['constancia_situacion_fiscal']);

        $this->assertSame('rfc_coincide', $regla->evaluar($contexto)->clave);
        $this->assertSame(EstadoResultado::Cumple, $regla->evaluar($contexto)->estado);
        $this->assertSame(EstadoResultado::NoCumple, $regla->evaluar($moral)->estado);
    }
}
