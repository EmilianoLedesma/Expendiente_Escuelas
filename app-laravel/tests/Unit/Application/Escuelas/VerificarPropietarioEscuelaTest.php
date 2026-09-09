<?php

namespace Tests\Unit\Application\Escuelas;

use App\Application\Escuelas\VerificarPropietarioEscuela;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificarPropietarioEscuelaTest extends TestCase
{
    use RefreshDatabase;

    public function test_devuelve_true_cuando_el_solicitante_es_el_dueno(): void
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);

        $resultado = (new VerificarPropietarioEscuela)->ejecutar($escuela->id, $solicitante->id);

        $this->assertTrue($resultado);
    }

    public function test_devuelve_false_cuando_el_solicitante_no_es_el_dueno(): void
    {
        $dueno = Solicitante::factory()->create();
        $otro = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $dueno->id]);

        $resultado = (new VerificarPropietarioEscuela)->ejecutar($escuela->id, $otro->id);

        $this->assertFalse($resultado);
    }
}
