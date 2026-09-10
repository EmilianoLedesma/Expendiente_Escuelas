<?php

namespace Tests\Feature\Policies;

use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EscuelaPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuelaPara(Solicitante $solicitante): Escuela
    {
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    public function test_el_dueno_puede_ver_la_ruta_de_paso2(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);

        $response = $this->actingAs($solicitante->user)->get(route('tramite.paso2', ['escuela' => $escuela->id]));

        $response->assertOk();
    }

    public function test_un_no_dueno_recibe_403_en_la_ruta_de_paso2(): void
    {
        $dueno = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($dueno);
        $otro = Solicitante::factory()->create();

        $response = $this->actingAs($otro->user)->get(route('tramite.paso2', ['escuela' => $escuela->id]));

        $response->assertForbidden();
    }
}
