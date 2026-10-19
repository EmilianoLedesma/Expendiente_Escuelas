<?php

namespace Tests\Unit\Models;

use App\Models\EntidadFederativa;
use App\Models\Municipio;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogoGeograficoTest extends TestCase
{
    use RefreshDatabase;

    public function test_entidad_has_municipios_and_municipio_belongs_to_entidad(): void
    {
        $queretaro = EntidadFederativa::create(['clave' => 22, 'nombre' => 'QUERÉTARO', 'abreviatura' => 'QT']);
        $municipio = Municipio::create(['entidad_federativa_id' => $queretaro->id, 'clave' => 14, 'nombre' => 'QUERÉTARO']);

        $this->assertTrue($queretaro->municipios->contains($municipio));
        $this->assertSame($queretaro->id, $municipio->entidadFederativa->id);
    }

    public function test_entidad_clave_is_unique(): void
    {
        EntidadFederativa::create(['clave' => 22, 'nombre' => 'QUERÉTARO']);

        $this->expectException(QueryException::class);
        EntidadFederativa::create(['clave' => 22, 'nombre' => 'OTRA']);
    }

    public function test_municipio_clave_is_unique_within_an_entidad_but_repeatable_across_entidades(): void
    {
        $a = EntidadFederativa::create(['clave' => 1, 'nombre' => 'AGUASCALIENTES']);
        $b = EntidadFederativa::create(['clave' => 2, 'nombre' => 'BAJA CALIFORNIA']);
        Municipio::create(['entidad_federativa_id' => $a->id, 'clave' => 1, 'nombre' => 'AGUASCALIENTES']);
        Municipio::create(['entidad_federativa_id' => $b->id, 'clave' => 1, 'nombre' => 'ENSENADA']);

        $this->assertDatabaseCount('municipios', 2);

        $this->expectException(QueryException::class);
        Municipio::create(['entidad_federativa_id' => $a->id, 'clave' => 1, 'nombre' => 'DUPLICADO']);
    }

    public function test_deleting_an_entidad_deletes_its_municipios(): void
    {
        $entidad = EntidadFederativa::create(['clave' => 22, 'nombre' => 'QUERÉTARO']);
        Municipio::create(['entidad_federativa_id' => $entidad->id, 'clave' => 14, 'nombre' => 'QUERÉTARO']);

        $entidad->delete();

        $this->assertDatabaseCount('municipios', 0);
    }
}
