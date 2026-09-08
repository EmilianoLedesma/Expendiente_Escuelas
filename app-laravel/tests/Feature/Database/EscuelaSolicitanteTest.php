<?php

namespace Tests\Feature\Database;

use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EscuelaSolicitanteTest extends TestCase
{
    use RefreshDatabase;

    private function plantelDePrueba(): Plantel
    {
        return Plantel::create([
            'calle' => 'Calle 1', 'colonia' => 'Centro',
            'municipio' => 'Querétaro', 'codigo_postal' => '76000',
        ]);
    }

    public function test_una_escuela_pertenece_a_un_solicitante(): void
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = $this->plantelDePrueba();

        $escuela = Escuela::create([
            'plantel_id' => $plantel->id,
            'solicitante_id' => $solicitante->id,
        ]);

        $this->assertTrue($escuela->solicitante->is($solicitante));
        $this->assertTrue($solicitante->escuelas->first()->is($escuela));
    }

    public function test_solicitante_id_es_obligatorio(): void
    {
        $plantel = $this->plantelDePrueba();

        $this->expectException(\Illuminate\Database\QueryException::class);

        Escuela::create(['plantel_id' => $plantel->id]);
    }

    public function test_no_se_puede_eliminar_un_solicitante_con_escuelas(): void
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = $this->plantelDePrueba();
        Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $solicitante->delete();
    }
}
