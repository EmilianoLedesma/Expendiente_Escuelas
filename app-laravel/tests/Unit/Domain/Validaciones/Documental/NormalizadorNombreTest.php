<?php

namespace Tests\Unit\Domain\Validaciones\Documental;

use App\Domain\Validaciones\Documental\NormalizadorNombre;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NormalizadorNombreTest extends TestCase
{
    #[DataProvider('casosProvider')]
    public function test_normaliza(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, (new NormalizadorNombre)->normalizar($entrada));
    }

    public static function casosProvider(): array
    {
        return [
            'mayusculas' => ['Juan Pérez', 'JUAN PEREZ'],
            'acentos' => ['María José Gómez Núñez', 'MARIA JOSE GOMEZ NUNEZ'],
            'dieresis' => ['Agüero', 'AGUERO'],
            'enie en mayuscula' => ['PEÑA', 'PENA'],
            'espacios multiples y bordes' => ["  Juan \t  Pérez  ", 'JUAN PEREZ'],
            'puntuacion' => ['Juan-Carlos Pérez.', 'JUAN CARLOS PEREZ'],
            'ya normalizado' => ['PEREZ GOMEZ JUAN', 'PEREZ GOMEZ JUAN'],
        ];
    }

    public function test_tokens_devuelve_palabras_normalizadas(): void
    {
        $this->assertSame(['PEREZ', 'GOMEZ', 'JUAN'], (new NormalizadorNombre)->tokens('Pérez  Gómez juan'));
    }
}
