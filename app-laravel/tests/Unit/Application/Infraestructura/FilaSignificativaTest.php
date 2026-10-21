<?php

namespace Tests\Unit\Application\Infraestructura;

use App\Application\Infraestructura\FilaSignificativa;
use PHPUnit\Framework\TestCase;

class FilaSignificativaTest extends TestCase
{
    /** @return array<string, mixed> */
    private function espacioVacio(array $cambios = []): array
    {
        return array_merge([
            'cantidad' => null,
            'superficieM2' => null,
            'capacidadPromedio' => null,
            'ventilacionNatural' => null,
            'iluminacionNatural' => null,
            'destinadoA' => null,
            'campoFutbol' => null,
            'materialesBiblioteca' => [],
        ], $cambios);
    }

    /** @return array<string, mixed> */
    private function sanitarioVacio(array $cambios = []): array
    {
        return array_merge([
            'cantidadRetretes' => null,
            'cantidadMingitorios' => null,
            'cantidadLavabos' => null,
            'superficieM2' => null,
            'ventilacionNatural' => null,
            'iluminacionNatural' => null,
            'cantidadBacinicas' => null,
        ], $cambios);
    }

    public function test_espacio_todo_null_o_false_no_es_significativo(): void
    {
        $this->assertFalse(FilaSignificativa::espacio($this->espacioVacio()));
        $this->assertFalse(FilaSignificativa::espacio($this->espacioVacio(['ventilacionNatural' => false, 'iluminacionNatural' => false])));
    }

    public function test_espacio_con_cantidad_cero_o_un_true_es_significativo(): void
    {
        $this->assertTrue(FilaSignificativa::espacio($this->espacioVacio(['cantidad' => 0])));
        $this->assertTrue(FilaSignificativa::espacio($this->espacioVacio(['ventilacionNatural' => true])));
        $this->assertTrue(FilaSignificativa::espacio($this->espacioVacio(['destinadoA' => 'limpieza'])));
    }

    public function test_campo_futbol_solo_cuenta_con_tipo_superficie_o_formato(): void
    {
        $this->assertFalse(FilaSignificativa::espacio($this->espacioVacio(['campoFutbol' => ['tipoSuperficie' => null, 'formato' => null]])));
        $this->assertTrue(FilaSignificativa::espacio($this->espacioVacio(['campoFutbol' => ['tipoSuperficie' => 'pasto', 'formato' => null]])));
        $this->assertTrue(FilaSignificativa::espacio($this->espacioVacio(['campoFutbol' => ['tipoSuperficie' => null, 'formato' => '7']])));
    }

    public function test_materiales_de_biblioteca_hacen_significativo_el_espacio(): void
    {
        $this->assertTrue(FilaSignificativa::espacio($this->espacioVacio(['materialesBiblioteca' => [['tipoMaterialId' => 1]]])));
    }

    public function test_sanitario_todo_null_o_false_no_es_significativo(): void
    {
        $this->assertFalse(FilaSignificativa::sanitario($this->sanitarioVacio()));
        $this->assertFalse(FilaSignificativa::sanitario($this->sanitarioVacio(['ventilacionNatural' => false, 'iluminacionNatural' => false])));
    }

    public function test_sanitario_con_algun_dato_es_significativo(): void
    {
        $this->assertTrue(FilaSignificativa::sanitario($this->sanitarioVacio(['cantidadRetretes' => 0])));
        $this->assertTrue(FilaSignificativa::sanitario($this->sanitarioVacio(['cantidadBacinicas' => 2])));
        $this->assertTrue(FilaSignificativa::sanitario($this->sanitarioVacio(['iluminacionNatural' => true])));
    }
}
