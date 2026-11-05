<?php

namespace Tests\Feature\Seguridad;

use App\Application\Reportes\ConsultarReportes;
use Tests\TestCase;

/** El CSV del admin se abre en Excel: ninguna celda de texto puede empezar con un carácter de fórmula. */
class CsvFormulasTest extends TestCase
{
    public function test_las_celdas_de_texto_que_parecen_formula_salen_con_comilla_inicial(): void
    {
        $seccion = ['detalle' => ['columnas' => ['=Encabezado'], 'filas' => [
            ['=HYPERLINK("http://x","y")'], ['+1+1'], ['-2'], ['@SUM(1)'], ["\tcmd"], ["\rcmd"],
            ['Colegio Sol'], [5], [null],
        ]]];

        $csv = app(ConsultarReportes::class)->csv($seccion); // @phpstan-ignore argument.type

        $filas = array_map('str_getcsv', array_values(array_filter(explode("\n", str_replace("\r\n", "\n", substr($csv, 3))))));

        $this->assertSame("'=Encabezado", $filas[0][0]);
        $this->assertSame("'=HYPERLINK(\"http://x\",\"y\")", $filas[1][0]);
        $this->assertSame("'+1+1", $filas[2][0]);
        $this->assertSame("'-2", $filas[3][0]);
        $this->assertSame("'@SUM(1)", $filas[4][0]);
        $this->assertStringStartsWith("'", $filas[5][0]);
        $this->assertStringContainsString('Colegio Sol', $csv);
        $this->assertSame('Colegio Sol', $filas[7][0]); // el texto normal no cambia
        $this->assertSame('5', $filas[8][0]);
    }
}
