<?php

namespace Tests\Unit\Domain\Validaciones\Documental\Reglas;

use App\Domain\Validaciones\Documental\ContextoValidacion;
use App\Domain\Validaciones\Documental\Hecho;
use App\Domain\Validaciones\Documental\NormalizadorDomicilio;
use App\Domain\Validaciones\Documental\Reglas\DomicilioCoincide;
use App\Domain\Validaciones\Documental\TipoHecho;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use PHPUnit\Framework\TestCase;

class DomicilioCoincideTest extends TestCase
{
    private const FUENTE = 'certificado_numero_oficial';

    private const DECLARADO = [
        'domicilio_calle' => 'Av. Juárez',
        'domicilio_numero_ext' => '12',
        'domicilio_colonia' => 'Centro',
        'domicilio_municipio' => 'Querétaro',
        'domicilio_codigo_postal' => '76000',
    ];

    private function regla(): DomicilioCoincide
    {
        return new DomicilioCoincide(self::FUENTE, new NormalizadorDomicilio);
    }

    /**
     * @param  array<string, string>  $declarado
     * @param  array<string, string>|null  $certificado  null = subido sin datos
     */
    private function contexto(array $declarado, ?array $certificado, bool $subido = true): ContextoValidacion
    {
        $hechos = [];
        foreach ($declarado as $tipo => $valor) {
            $hechos[] = Hecho::declarado(TipoHecho::from($tipo), $valor);
        }
        foreach ($certificado ?? [] as $tipo => $valor) {
            $hechos[] = Hecho::deDocumento(TipoHecho::from($tipo), $valor, self::FUENTE);
        }

        return new ContextoValidacion('fisica', $hechos, [self::FUENTE], $subido ? [self::FUENTE] : []);
    }

    public function test_cumple_con_abreviaturas_y_prefijos_distintos(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto(self::DECLARADO, [
            'domicilio_calle' => 'AVENIDA JUAREZ',
            'domicilio_numero_ext' => 'No. 12',
            'domicilio_colonia' => 'Col. Centro',
            'domicilio_municipio' => 'QUERETARO',
            'domicilio_codigo_postal' => '76000',
        ]));

        $this->assertSame(EstadoResultado::Cumple, $resultado->estado);
        $this->assertSame('domicilio_coincide', $resultado->clave);
    }

    public function test_calle_distinta_alerta_y_senala_el_certificado(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto(self::DECLARADO, [...self::DECLARADO, 'domicilio_calle' => 'Hidalgo']));

        $this->assertSame(EstadoResultado::Advertencia, $resultado->estado);
        $this->assertSame([self::FUENTE], $resultado->documentos);
        $this->assertSame('difiere', $resultado->detalles['partes']['domicilio_calle']['estado']);
        $this->assertSame('coincide', $resultado->detalles['partes']['domicilio_colonia']['estado']);
    }

    public function test_codigo_postal_distinto_no_cumple(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto(self::DECLARADO, [...self::DECLARADO, 'domicilio_codigo_postal' => '76010']));

        $this->assertSame(EstadoResultado::NoCumple, $resultado->estado);
        $this->assertSame([self::FUENTE], $resultado->documentos);
    }

    public function test_numero_declarado_ausente_en_el_certificado_alerta(): void
    {
        $certificado = self::DECLARADO;
        unset($certificado['domicilio_numero_ext']);

        $resultado = $this->regla()->evaluar($this->contexto(self::DECLARADO, $certificado));

        $this->assertSame(EstadoResultado::Advertencia, $resultado->estado);
    }

    public function test_parte_no_declarada_no_se_compara(): void
    {
        $declarado = self::DECLARADO;
        unset($declarado['domicilio_numero_ext']);

        $resultado = $this->regla()->evaluar($this->contexto($declarado, self::DECLARADO));

        $this->assertSame(EstadoResultado::Cumple, $resultado->estado);
        $this->assertArrayNotHasKey('domicilio_numero_ext', $resultado->detalles['partes']);
    }

    public function test_certificado_subido_sin_datos_no_cumple(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto(self::DECLARADO, null));

        $this->assertSame(EstadoResultado::NoCumple, $resultado->estado);
        $this->assertSame([self::FUENTE], $resultado->documentos);
    }

    public function test_no_evaluable_si_el_certificado_no_esta_subido(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto(self::DECLARADO, null, subido: false));

        $this->assertSame(EstadoResultado::NoEvaluable, $resultado->estado);
    }
}
