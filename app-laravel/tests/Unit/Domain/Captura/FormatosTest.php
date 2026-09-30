<?php

namespace Tests\Unit\Domain\Captura;

use App\Domain\Captura\Formatos;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FormatosTest extends TestCase
{
    #[DataProvider('curps')]
    public function test_curp(string $valor, bool $esperado): void
    {
        $this->assertSame($esperado, Formatos::esCurp($valor));
    }

    public static function curps(): array
    {
        return [
            'válida' => ['GOMA800101HQTRRL09', true],
            'mujer, homoclave letra' => ['PEJL021231MDFRNSA5', true],
            'minúsculas (sin normalizar)' => ['goma800101hqtrrl09', false],
            '17 caracteres' => ['GOMA800101HQTRRL0', false],
            'mes 13' => ['GOMA801301HQTRRL09', false],
            'día 32' => ['GOMA800132HQTRRL09', false],
            'sexo inválido' => ['GOMA800101ZQTRRL09', false],
            'con espacios' => ['GOMA 800101HQTRRL09', false],
            'vacía' => ['', false],
        ];
    }

    #[DataProvider('rfcsFisica')]
    public function test_rfc_persona_fisica(string $valor, bool $esperado): void
    {
        $this->assertSame($esperado, Formatos::esRfcPersonaFisica($valor));
    }

    public static function rfcsFisica(): array
    {
        return [
            'válido' => ['GOMA800101AB1', true],
            'con Ñ' => ['NUÑE850315KX2', true],
            'de persona moral (12)' => ['ABC800101AB1', false],
            'mes 00' => ['GOMA800001AB1', false],
            'homoclave corta' => ['GOMA800101AB', false],
        ];
    }

    #[DataProvider('rfcsMoral')]
    public function test_rfc_persona_moral(string $valor, bool $esperado): void
    {
        $this->assertSame($esperado, Formatos::esRfcPersonaMoral($valor));
    }

    public static function rfcsMoral(): array
    {
        return [
            'válido' => ['ABC800101AB1', true],
            'con &' => ['A&C991231XY9', true],
            'de persona física (13)' => ['GOMA800101AB1', false],
        ];
    }

    #[DataProvider('codigosPostales')]
    public function test_codigo_postal(string $valor, bool $esperado): void
    {
        $this->assertSame($esperado, Formatos::esCodigoPostal($valor));
    }

    public static function codigosPostales(): array
    {
        return [
            'Querétaro' => ['76000', true],
            'CDMX con cero inicial' => ['01000', true],
            '4 dígitos' => ['7600', false],
            '6 dígitos' => ['760000', false],
            'letras' => ['76OOO', false],
            'prefijo 00 no existe' => ['00123', false],
        ];
    }

    #[DataProvider('telefonos')]
    public function test_telefono(string $valor, bool $esperado): void
    {
        $this->assertSame($esperado, Formatos::esTelefono($valor));
    }

    public static function telefonos(): array
    {
        return [
            '10 dígitos' => ['4421234567', true],
            '9 dígitos' => ['442123456', false],
            'con separadores (sin normalizar)' => ['442-123-4567', false],
            'empieza en 0' => ['0421234567', false],
        ];
    }

    #[DataProvider('correos')]
    public function test_correo(string $valor, bool $esperado): void
    {
        $this->assertSame($esperado, Formatos::esCorreo($valor));
    }

    public static function correos(): array
    {
        return [
            'válido' => ['direccion@escuela.edu.mx', true],
            'sin dominio' => ['direccion@', false],
            'sin punto en el dominio' => ['direccion@localhost', false],
            'con espacio' => ['dire ccion@escuela.mx', false],
        ];
    }
}
