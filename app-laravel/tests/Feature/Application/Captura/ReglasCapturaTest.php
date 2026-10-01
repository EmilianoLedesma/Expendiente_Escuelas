<?php

namespace Tests\Feature\Application\Captura;

use App\Application\Captura\ReglasCaptura;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReglasCapturaTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Los Form objects dan su nombre visible con validationAttributes(); aquí se pasa igual. */
    private function primerError(array $reglas, mixed $valor, string $campo = 'campo', ?string $nombre = null): ?string
    {
        return Validator::make(Arr::undot([$campo => $valor]), [$campo => $reglas], [], $nombre ? [$campo => $nombre] : [])
            ->errors()->first($campo) ?: null;
    }

    #[DataProvider('invalidos')]
    public function test_rechaza_con_mensaje_en_espanol(string $regla, mixed $valor, string $nombre, string $mensaje): void
    {
        $this->assertSame($mensaje, $this->primerError(ReglasCaptura::$regla(), $valor, 'campo', $nombre));
    }

    public static function invalidos(): array
    {
        return [
            'curp' => ['curp', 'GOMA800101', 'CURP', 'La CURP no tiene un formato válido: son 18 caracteres, por ejemplo GOMA800101HQTRRL09.'],
            'rfc física' => ['rfcPersonaFisica', 'ABC800101AB1', 'RFC', 'El RFC no tiene un formato válido: son 13 caracteres para persona física, por ejemplo GOMA800101AB1.'],
            'rfc moral' => ['rfcPersonaMoral', 'GOMA800101AB1', 'campo', 'El campo no tiene un formato válido: son 12 caracteres para persona moral, por ejemplo ABC800101AB1.'],
            'rfc cualquiera' => ['rfc', 'X', 'campo', 'El campo no tiene un formato válido: son 13 caracteres para persona física o 12 para persona moral.'],
            'código postal' => ['codigoPostal', '7600', 'código postal', 'El código postal debe tener 5 dígitos, por ejemplo 76000.'],
            'teléfono' => ['telefono', '12345', 'teléfono', 'El teléfono debe tener 10 dígitos, por ejemplo 4421234567.'],
            'correo' => ['correo', 'a@b', 'correo electrónico', 'Ingresa un correo electrónico válido, por ejemplo: nombre@dominio.com.'],
            'nombre con dígitos' => ['nombrePersona', 'Juan 2', 'nombre completo', 'El campo nombre completo solo puede contener letras, espacios, puntos, apóstrofos y guiones.'],
            'fecha pasada en el futuro' => ['fechaPasada', '2999-01-01', 'fecha de nacimiento', 'La fecha de nacimiento no puede ser posterior a hoy.'],
            'fecha anterior a 1900' => ['fechaPasada', '1899-12-31', 'fecha de nacimiento', 'La fecha de nacimiento no puede ser anterior a 1900.'],
            'fecha con otro formato' => ['fecha', '31/12/2020', 'vigencia del registro', 'Ingresa una fecha válida en vigencia del registro.'],
            'fecha demasiado lejana' => ['fecha', '2150-01-01', 'vigencia del registro', 'La vigencia del registro no puede ser posterior a 2100.'],
        ];
    }

    public function test_los_opcionales_aceptan_vacio_y_null(): void
    {
        foreach (['curp', 'rfcPersonaFisica', 'rfcPersonaMoral', 'rfc', 'codigoPostal', 'telefono', 'correo', 'nombrePersona', 'fechaPasada', 'fecha'] as $regla) {
            $this->assertNull($this->primerError(ReglasCaptura::$regla(), null), $regla);
            $this->assertNull($this->primerError(ReglasCaptura::$regla(), ''), $regla);
        }
    }

    public function test_requerido_marca_obligatorio(): void
    {
        $this->assertSame('El campo CURP es obligatorio.', $this->primerError(ReglasCaptura::curp(requerido: true), '', 'campo', 'CURP'));
    }

    public function test_acepta_valores_validos(): void
    {
        Carbon::setTestNow('2026-09-30');

        $this->assertNull($this->primerError(ReglasCaptura::curp(), 'GOMA800101HQTRRL09'));
        $this->assertNull($this->primerError(ReglasCaptura::rfcPersonaFisica(), 'GOMA800101AB1'));
        $this->assertNull($this->primerError(ReglasCaptura::rfcPersonaMoral(), 'ABC800101AB1'));
        $this->assertNull($this->primerError(ReglasCaptura::rfc(), 'ABC800101AB1'));
        $this->assertNull($this->primerError(ReglasCaptura::codigoPostal(), '76000'));
        $this->assertNull($this->primerError(ReglasCaptura::telefono(), '4421234567'));
        $this->assertNull($this->primerError(ReglasCaptura::correo(), 'direccion@escuela.edu.mx'));
        $this->assertNull($this->primerError(ReglasCaptura::nombrePersona(), "María José O'Connor-Núñez Jr."));
        $this->assertNull($this->primerError(ReglasCaptura::fechaPasada(), '2026-09-30'));
        $this->assertNull($this->primerError(ReglasCaptura::fecha(), '2030-01-01'));
    }

    public function test_decimal_respeta_la_precision_de_la_columna(): void
    {
        // NUMERIC(10,2): 8 enteros, 2 decimales.
        $this->assertNull($this->primerError(ReglasCaptura::decimal(99999999.99), '99999999.99'));
        $this->assertSame('El campo superficie del predio no debe ser mayor que 99999999.99.', $this->primerError(ReglasCaptura::decimal(99999999.99), '100000000', 'metrosTotales'));
        $this->assertSame('El campo superficie del predio admite como máximo 2 decimales.', $this->primerError(ReglasCaptura::decimal(99999999.99), '10.555', 'metrosTotales'));
        $this->assertSame('El campo superficie del predio debe ser al menos 0.', $this->primerError(ReglasCaptura::decimal(99999999.99), '-1', 'metrosTotales'));
    }

    public function test_entero_respeta_el_rango_de_la_columna(): void
    {
        $this->assertNull($this->primerError(ReglasCaptura::entero(2147483647), '2147483647'));
        $this->assertSame('El campo número de títulos no debe ser mayor que 2147483647.', $this->primerError(ReglasCaptura::entero(2147483647), '2147483648', 'materialesBiblioteca.3.numeroTitulos'));
        $this->assertSame('El campo número de títulos debe ser un número entero, sin decimales.', $this->primerError(ReglasCaptura::entero(2147483647), '1.5', 'materialesBiblioteca.3.numeroTitulos'));
    }
}
