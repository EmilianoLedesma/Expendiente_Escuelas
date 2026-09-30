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
                Hecho::declarado(TipoHecho::NombreTitular, 'Juan Pérez'),
                Hecho::deDocumento(TipoHecho::NombreTitular, 'PEREZ JUAN', 'ine'),
                Hecho::deDocumento(TipoHecho::NombreTitular, 'JUAN PEREZ', 'acta_nacimiento'),
            ],
            clavesRequeridas: ['ine', 'acta_nacimiento'],
            clavesPresentes: ['ine'],
        );
    }

    public function test_devuelve_el_hecho_declarado_por_tipo(): void
    {
        $this->assertSame('Juan Pérez', $this->contexto()->declarado(TipoHecho::NombreTitular)?->valor);
        $this->assertNull($this->contexto()->declarado(TipoHecho::Curp));
    }

    public function test_devuelve_el_hecho_de_un_documento_especifico(): void
    {
        $this->assertSame('PEREZ JUAN', $this->contexto()->deDocumento(TipoHecho::NombreTitular, 'ine')?->valor);
        $this->assertSame('JUAN PEREZ', $this->contexto()->deDocumento(TipoHecho::NombreTitular, 'acta_nacimiento')?->valor);
        $this->assertNull($this->contexto()->deDocumento(TipoHecho::Curp, 'ine'));
    }

    public function test_expone_claves_requeridas_y_presentes(): void
    {
        $this->assertSame(['ine', 'acta_nacimiento'], $this->contexto()->clavesRequeridas);
        $this->assertSame(['ine'], $this->contexto()->clavesPresentes);
        $this->assertSame('fisica', $this->contexto()->tipoPersona);
    }
}
