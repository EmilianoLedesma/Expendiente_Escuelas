<?php

namespace Tests\Unit\Domain\Validaciones\Documental;

use App\Domain\Validaciones\Documental\NormalizadorDomicilio;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NormalizadorDomicilioTest extends TestCase
{
    #[DataProvider('casosProvider')]
    public function test_normaliza(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, (new NormalizadorDomicilio)->normalizar($entrada));
    }

    public static function casosProvider(): array
    {
        return [
            'acentos y mayusculas' => ['Av. Juárez', 'AVENIDA JUAREZ'],
            'abreviatura ave' => ['AVE JUAREZ', 'AVENIDA JUAREZ'],
            'boulevard' => ['Blvd. Bernardo Quintana', 'BOULEVARD BERNARDO QUINTANA'],
            'prolongacion y cerrada' => ['Prol. Cda. del Sol', 'PROLONGACION CERRADA DEL SOL'],
            'palabra calle se omite' => ['Calle Hidalgo', 'HIDALGO'],
            'prefijo de colonia se omite' => ['Col. Centro', 'CENTRO'],
            'numero conserva digitos' => ['No. 12-B', '12 B'],
            'numero con gato' => ['#12', '12'],
            'espacios' => ['  5 de   Mayo ', '5 DE MAYO'],
        ];
    }
}
