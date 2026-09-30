<?php

namespace Tests\Unit\Domain\Validaciones\Documental;

use App\Domain\Validaciones\Documental\Hecho;
use App\Domain\Validaciones\Documental\OrigenHecho;
use App\Domain\Validaciones\Documental\TipoHecho;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class HechoTest extends TestCase
{
    public function test_hecho_declarado_no_lleva_documento(): void
    {
        $hecho = Hecho::declarado(TipoHecho::Curp, 'PEGJ800101HQTRML09');

        $this->assertSame(TipoHecho::Curp, $hecho->tipo);
        $this->assertSame('PEGJ800101HQTRML09', $hecho->valor);
        $this->assertSame(OrigenHecho::Declarado, $hecho->origen);
        $this->assertNull($hecho->documentoClave);
    }

    public function test_hecho_de_documento_lleva_su_clave(): void
    {
        $hecho = Hecho::deDocumento(TipoHecho::NombreTitular, 'PEREZ GOMEZ JUAN', 'ine');

        $this->assertSame(OrigenHecho::Documento, $hecho->origen);
        $this->assertSame('ine', $hecho->documentoClave);
    }

    public function test_rechaza_valor_vacio_o_solo_espacios(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Hecho::declarado(TipoHecho::NombreTitular, '   ');
    }

    public function test_rechaza_hecho_de_documento_sin_clave(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Hecho(TipoHecho::Curp, 'PEGJ800101HQTRML09', OrigenHecho::Documento, null);
    }

    public function test_rechaza_hecho_declarado_con_clave_de_documento(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Hecho(TipoHecho::Curp, 'PEGJ800101HQTRML09', OrigenHecho::Declarado, 'ine');
    }
}
