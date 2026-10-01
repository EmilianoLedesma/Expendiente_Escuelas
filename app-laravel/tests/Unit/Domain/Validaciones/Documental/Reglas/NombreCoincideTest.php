<?php

namespace Tests\Unit\Domain\Validaciones\Documental\Reglas;

use App\Domain\Validaciones\Documental\ContextoValidacion;
use App\Domain\Validaciones\Documental\Hecho;
use App\Domain\Validaciones\Documental\NormalizadorNombre;
use App\Domain\Validaciones\Documental\Reglas\NombreCoincide;
use App\Domain\Validaciones\Documental\TipoHecho;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use PHPUnit\Framework\TestCase;

class NombreCoincideTest extends TestCase
{
    private function regla(): NombreCoincide
    {
        return new NombreCoincide('nombre_identidad_coincide', TipoHecho::NombreIdentidad, ['ine', 'constancia_curp'], new NormalizadorNombre);
    }

    /** @param array<string, string|null> $documentos clave => nombre capturado (null = subido sin datos) */
    private function contexto(?string $declarado, array $documentos): ContextoValidacion
    {
        $hechos = $declarado === null ? [] : [Hecho::declarado(TipoHecho::NombreIdentidad, $declarado)];
        foreach ($documentos as $clave => $valor) {
            if ($valor !== null) {
                $hechos[] = Hecho::deDocumento(TipoHecho::NombreIdentidad, $valor, $clave);
            }
        }

        return new ContextoValidacion('fisica', $hechos, array_keys($documentos), array_keys($documentos));
    }

    public function test_cumple_cuando_todos_los_documentos_coinciden_con_lo_declarado(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto('Juan Pérez Gómez', ['ine' => 'JUAN PEREZ GOMEZ', 'constancia_curp' => 'JUAN PEREZ GOMEZ']));

        $this->assertSame(EstadoResultado::Cumple, $resultado->estado);
        $this->assertSame('nombre_identidad_coincide', $resultado->clave);
        $this->assertSame([], $resultado->documentos);
    }

    public function test_cumple_ignorando_acentos_espacios_y_orden_de_apellidos(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto('  juan  Pérez gómez', ['ine' => 'PEREZ GOMEZ JUAN']));

        $this->assertSame(EstadoResultado::Cumple, $resultado->estado);
    }

    public function test_alerta_y_senala_solo_el_documento_que_difiere(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto('Juan Pérez Gómez', ['ine' => 'PEREZ GOMEZ JUAN', 'constancia_curp' => 'MARIA LOPEZ']));

        $this->assertSame(EstadoResultado::Advertencia, $resultado->estado);
        $this->assertSame(['constancia_curp'], $resultado->documentos);
        $this->assertSame('Juan Pérez Gómez', $resultado->detalles['declarado']);
        $this->assertSame(['estado' => 'coincide', 'valor' => 'PEREZ GOMEZ JUAN'], $resultado->detalles['documentos']['ine']);
        $this->assertSame(['estado' => 'difiere', 'valor' => 'MARIA LOPEZ'], $resultado->detalles['documentos']['constancia_curp']);
    }

    public function test_un_segundo_nombre_omitido_tambien_alerta(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto('Juan Pérez Gómez', ['ine' => 'PEREZ GOMEZ JUAN CARLOS']));

        $this->assertSame(EstadoResultado::Advertencia, $resultado->estado);
    }

    public function test_un_token_repetido_no_cuenta_como_coincidencia(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto('Juan Juan Pérez', ['ine' => 'JUAN PEREZ PEREZ']));

        $this->assertSame(EstadoResultado::Advertencia, $resultado->estado);
    }

    public function test_documento_subido_sin_sus_datos_no_cumple_y_se_senala(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto('Juan Pérez', ['ine' => 'JUAN PEREZ', 'constancia_curp' => null]));

        $this->assertSame(EstadoResultado::NoCumple, $resultado->estado);
        $this->assertSame(['constancia_curp'], $resultado->documentos);
        $this->assertSame(['estado' => 'sin_datos', 'valor' => null], $resultado->detalles['documentos']['constancia_curp']);
    }

    public function test_documentos_no_subidos_se_ignoran_aqui(): void
    {
        // Missing uploads are DocumentosRequeridosPresentes' job, not this rule's.
        $contexto = new ContextoValidacion('fisica', [
            Hecho::declarado(TipoHecho::NombreIdentidad, 'Juan Pérez'),
            Hecho::deDocumento(TipoHecho::NombreIdentidad, 'JUAN PEREZ', 'ine'),
        ], ['ine', 'constancia_curp'], ['ine']);

        $resultado = $this->regla()->evaluar($contexto);

        $this->assertSame(EstadoResultado::Cumple, $resultado->estado);
        $this->assertArrayNotHasKey('constancia_curp', $resultado->detalles['documentos']);
    }

    public function test_no_evaluable_sin_nombre_declarado(): void
    {
        $resultado = $this->regla()->evaluar($this->contexto(null, ['ine' => 'JUAN PEREZ']));

        $this->assertSame(EstadoResultado::NoEvaluable, $resultado->estado);
    }

    public function test_no_evaluable_si_ningun_documento_fuente_esta_subido(): void
    {
        $contexto = new ContextoValidacion('fisica', [Hecho::declarado(TipoHecho::NombreIdentidad, 'Juan Pérez')], ['ine'], []);

        $this->assertSame(EstadoResultado::NoEvaluable, $this->regla()->evaluar($contexto)->estado);
    }
}
