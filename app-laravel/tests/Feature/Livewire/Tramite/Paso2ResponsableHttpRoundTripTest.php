<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Paso2ResponsableHttpRoundTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_paso2_responde_200_por_http_real(): void
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);

        $response = $this->actingAs($solicitante->user)->get(route('tramite.paso2', ['escuela' => $escuela->id]));

        $response->assertOk();
        $response->assertSeeLivewire('tramite.paso2-responsable');
    }
}
