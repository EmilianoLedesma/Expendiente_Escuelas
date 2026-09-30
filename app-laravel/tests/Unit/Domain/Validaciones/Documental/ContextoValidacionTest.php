<?php

namespace Tests\Unit\Domain\Validaciones\Documental;

use App\Domain\Validaciones\Documental\ContextoValidacion;
use App\Domain\Validaciones\Documental\Hecho;
use App\Domain\Validaciones\Documental\TipoHecho;
use PHPUnit\Framework\TestCase;

class ContextoValidacionTest extends TestCase
{
    private function contexto(): ContextoValidacion
    {
        return new ContextoValidacion(
            tipoPersona: 'fisica',
            hechos: [
                Hecho::declarado(TipoHecho::NombreIdentidad, 'Juan Pérez'),
                Hecho::deDocumento(TipoHecho::NombreIdentidad, 'PEREZ JUAN', 'ine'),
                Hecho::deDocumento(TipoHecho::NombreIdentidad, 'JUAN PEREZ', 'constancia_curp'),
            ],
            clavesRequeridas: ['ine', 'constancia_curp'],
            clavesPresentes: ['ine'],
        );
    }

    public function test_devuelve_el_hecho_declarado_por_tipo(): void
    {
        $this->assertSame('Juan Pérez', $this->contexto()->declarado(TipoHecho::NombreIdentidad)?->valor);
        $this->assertNull($this->contexto()->declarado(TipoHecho::Curp));
    }

    public function test_devuelve_el_hecho_de_un_documento_especifico(): void
    {
        $this->assertSame('PEREZ JUAN', $this->contexto()->deDocumento(TipoHecho::NombreIdentidad, 'ine')?->valor);
        $this->assertSame('JUAN PEREZ', $this->contexto()->deDocumento(TipoHecho::NombreIdentidad, 'constancia_curp')?->valor);
        $this->assertNull($this->contexto()->deDocumento(TipoHecho::Curp, 'ine'));
    }

    public function test_expone_claves_requeridas_y_presentes(): void
    {
        $this->assertSame(['ine', 'constancia_curp'], $this->contexto()->clavesRequeridas);
        $this->assertSame(['ine'], $this->contexto()->clavesPresentes);
        $this->assertTrue($this->contexto()->presente('ine'));
        $this->assertFalse($this->contexto()->presente('constancia_curp'));
        $this->assertSame('fisica', $this->contexto()->tipoPersona);
    }
}
