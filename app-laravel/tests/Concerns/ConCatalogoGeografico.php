<?php

namespace Tests\Concerns;

use App\Models\EntidadFederativa;
use App\Models\Municipio;

/** Fixture: un catálogo geográfico local mínimo (ADR-017) con Querétaro (clave 22) y otra entidad. */
trait ConCatalogoGeografico
{
    protected EntidadFederativa $queretaro;

    protected EntidadFederativa $guanajuato;

    protected function sembrarCatalogoGeografico(): void
    {
        $this->queretaro = EntidadFederativa::create(['clave' => 22, 'nombre' => 'QUERÉTARO', 'abreviatura' => 'QT']);
        $this->guanajuato = EntidadFederativa::create(['clave' => 11, 'nombre' => 'GUANAJUATO', 'abreviatura' => 'GT']);
        Municipio::create(['entidad_federativa_id' => $this->queretaro->id, 'clave' => 14, 'nombre' => 'QUERÉTARO']);
        Municipio::create(['entidad_federativa_id' => $this->queretaro->id, 'clave' => 6, 'nombre' => 'CORREGIDORA']);
        Municipio::create(['entidad_federativa_id' => $this->queretaro->id, 'clave' => 16, 'nombre' => 'SAN JUAN DEL RÍO']);
        Municipio::create(['entidad_federativa_id' => $this->guanajuato->id, 'clave' => 7, 'nombre' => 'CELAYA']);
        // Mismo nombre en otra entidad: un nombre solo no basta para vincular fuera de Querétaro.
        Municipio::create(['entidad_federativa_id' => $this->guanajuato->id, 'clave' => 99, 'nombre' => 'CORREGIDORA']);
    }

    protected function municipio(string $nombre, ?EntidadFederativa $entidad = null): Municipio
    {
        return Municipio::where('entidad_federativa_id', ($entidad ?? $this->queretaro)->id)->where('nombre', $nombre)->firstOrFail();
    }
}
