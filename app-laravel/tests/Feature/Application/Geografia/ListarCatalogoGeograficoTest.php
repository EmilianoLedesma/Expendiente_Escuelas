<?php

namespace Tests\Feature\Application\Geografia;

use App\Application\Geografia\ListarCatalogoGeografico;
use App\Models\EntidadFederativa;
use App\Models\Municipio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListarCatalogoGeograficoTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_entidades_ordered_by_name_with_id_and_name_only(): void
    {
        $queretaro = EntidadFederativa::create(['clave' => 22, 'nombre' => 'QUERÉTARO', 'abreviatura' => 'QT']);
        $aguascalientes = EntidadFederativa::create(['clave' => 1, 'nombre' => 'AGUASCALIENTES', 'abreviatura' => 'AS']);

        $this->assertSame(
            [['id' => $aguascalientes->id, 'nombre' => 'AGUASCALIENTES'], ['id' => $queretaro->id, 'nombre' => 'QUERÉTARO']],
            (new ListarCatalogoGeografico)->entidades(),
        );
    }

    public function test_lists_only_the_municipios_of_the_given_entidad_ordered_by_name(): void
    {
        $queretaro = EntidadFederativa::create(['clave' => 22, 'nombre' => 'QUERÉTARO']);
        $otra = EntidadFederativa::create(['clave' => 1, 'nombre' => 'AGUASCALIENTES']);
        $corregidora = Municipio::create(['entidad_federativa_id' => $queretaro->id, 'clave' => 6, 'nombre' => 'CORREGIDORA']);
        $capital = Municipio::create(['entidad_federativa_id' => $queretaro->id, 'clave' => 14, 'nombre' => 'QUERÉTARO']);
        Municipio::create(['entidad_federativa_id' => $otra->id, 'clave' => 1, 'nombre' => 'AGUASCALIENTES']);

        $this->assertSame(
            [['id' => $corregidora->id, 'nombre' => 'CORREGIDORA'], ['id' => $capital->id, 'nombre' => 'QUERÉTARO']],
            (new ListarCatalogoGeografico)->municipios($queretaro->id),
        );
    }

    public function test_no_entidad_or_unknown_entidad_has_no_municipios(): void
    {
        $this->assertSame([], (new ListarCatalogoGeografico)->municipios(null));
        $this->assertSame([], (new ListarCatalogoGeografico)->municipios(999999));
    }
}
