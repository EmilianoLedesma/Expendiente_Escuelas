<?php

namespace Tests\Unit\Application\Captura;

use App\Application\Captura\Normalizacion;
use App\Application\Captura\NormalizadorEntrada;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NormalizadorEntradaTest extends TestCase
{
    #[DataProvider('casos')]
    public function test_normaliza(Normalizacion $como, string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, NormalizadorEntrada::aplicar($como, $entrada));
    }

    public static function casos(): array
    {
        return [
            'texto: recorta y colapsa espacios' => [Normalizacion::Texto, "  Av.   Reforma \t 100  ", 'Av. Reforma 100'],
            'texto: saltos de línea a un espacio' => [Normalizacion::Texto, "Centro\r\nHistórico", 'Centro Histórico'],
            'texto: espacio no separable y ancho cero' => [Normalizacion::Texto, "\u{00A0}Querétaro\u{200B}", 'Querétaro'],
            'texto: solo espacios queda vacío' => [Normalizacion::Texto, '   ', ''],
            'texto largo: conserva párrafos' => [Normalizacion::TextoLargo, "  Línea  uno\r\n\r\n\r\n\r\nLínea dos  ", "Línea uno\n\nLínea dos"],
            'identificador: mayúsculas sin espacios ni guiones' => [Normalizacion::Identificador, ' goma-800101 hqtrrl09 ', 'GOMA800101HQTRRL09'],
            'identificador: ñ a Ñ' => [Normalizacion::Identificador, 'nuñe850315kx2', 'NUÑE850315KX2'],
            'correo: minúsculas sin espacios' => [Normalizacion::Correo, '  Direccion@Escuela.MX ', 'direccion@escuela.mx'],
            'teléfono: quita separadores' => [Normalizacion::Telefono, '(442) 123-45.67', '4421234567'],
            'teléfono: quita lada internacional +52' => [Normalizacion::Telefono, '+52 442 123 4567', '4421234567'],
            'teléfono: conserva letras para que la validación las rechace' => [Normalizacion::Telefono, '442 ABC 4567', '442ABC4567'],
            'teléfono: no toca un número de 10 dígitos que empieza con 52' => [Normalizacion::Telefono, '5212345678', '5212345678'],
            'dígitos: quita espacios y guiones' => [Normalizacion::Digitos, ' 76 000 ', '76000'],
            'ninguna: no cambia nada' => [Normalizacion::Ninguna, '  secreta  ', '  secreta  '],
        ];
    }
}
