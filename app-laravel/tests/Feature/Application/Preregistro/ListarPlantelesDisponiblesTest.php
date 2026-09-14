<?php

namespace Tests\Feature\Application\Preregistro;

use App\Application\Preregistro\ListarPlantelesDisponibles;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListarPlantelesDisponiblesTest extends TestCase
{
    use RefreshDatabase;

    private function crearPlantel(string $calle): Plantel
    {
        return Plantel::create([
            'calle' => $calle,
            'colonia' => 'Centro',
            'municipio' => 'Querétaro',
            'codigo_postal' => '76000',
        ]);
    }

    public function test_solo_devuelve_planteles_con_al_menos_una_escuela_del_solicitante(): void
    {
        $solicitante = Solicitante::factory()->create();
        $otroSolicitante = Solicitante::factory()->create();

        $plantelPropio = $this->crearPlantel('Av. Propia 1');
        Escuela::create([
            'plantel_id' => $plantelPropio->id,
            'solicitante_id' => $solicitante->id,
        ]);

        $plantelAjeno = $this->crearPlantel('Av. Ajena 1');
        Escuela::create([
            'plantel_id' => $plantelAjeno->id,
            'solicitante_id' => $otroSolicitante->id,
        ]);

        $resultado = (new ListarPlantelesDisponibles)->ejecutar($solicitante->id);

        $this->assertCount(1, $resultado);
        $this->assertSame($plantelPropio->id, $resultado->first()['id']);
    }

    public function test_solicitante_sin_escuelas_recibe_lista_vacia_no_error(): void
    {
        $solicitante = Solicitante::factory()->create();

        $resultado = (new ListarPlantelesDisponibles)->ejecutar($solicitante->id);

        $this->assertCount(0, $resultado);
    }
}
