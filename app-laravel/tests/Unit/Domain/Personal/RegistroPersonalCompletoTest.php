<?php

namespace Tests\Unit\Domain\Personal;

use App\Domain\Personal\RegistroPersonalCompleto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RegistroPersonalCompletoTest extends TestCase
{
    private function camposCompletos(): array
    {
        return [
            'cargoPuestoId' => 3,
            'nombre' => 'Juana Pérez',
            'nacionalidad' => 'Mexicana',
            'sexo' => 'F',
            'estudios' => 'Licenciatura en Educación',
            'cedulaODocumento' => '1234567',
        ];
    }

    public function test_es_completo_cuando_los_seis_campos_estan_presentes(): void
    {
        $regla = new RegistroPersonalCompleto;

        $this->assertTrue($regla->esCompleto(...$this->camposCompletos()));
    }

    #[DataProvider('camposFaltantesProvider')]
    public function test_no_es_completo_cuando_falta_un_campo(string $campo): void
    {
        $regla = new RegistroPersonalCompleto;
        $campos = $this->camposCompletos();
        $campos[$campo] = null;

        $this->assertFalse($regla->esCompleto(...$campos));
    }

    public static function camposFaltantesProvider(): array
    {
        return [
            'cargoPuestoId' => ['cargoPuestoId'],
            'nombre' => ['nombre'],
            'nacionalidad' => ['nacionalidad'],
            'sexo' => ['sexo'],
            'estudios' => ['estudios'],
            'cedulaODocumento' => ['cedulaODocumento'],
        ];
    }

    /**
     * Una cadena vacía no cuenta como valor presente — un campo de texto
     * "tocado pero vaciado" en el formulario debe seguir contando como
     * borrador, no como dato capturado.
     */
    public function test_cadena_vacia_no_cuenta_como_presente(): void
    {
        $regla = new RegistroPersonalCompleto;
        $campos = $this->camposCompletos();
        $campos['nombre'] = '';

        $this->assertFalse($regla->esCompleto(...$campos));
    }
}
