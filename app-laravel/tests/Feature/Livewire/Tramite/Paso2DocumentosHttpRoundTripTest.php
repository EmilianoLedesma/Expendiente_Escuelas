<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Paso2DocumentosHttpRoundTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_paso2_documentos_responde_200_por_http_real(): void
    {
        (new TiposDocumentosSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));

        $response = $this->actingAs($solicitante->user)->get(route('tramite.paso2-documentos', ['escuela' => $escuela->id]));

        $response->assertOk();
        $response->assertSeeLivewire('tramite.paso2-documentos');
    }

    public function test_sin_responsable_redirige_a_paso2_en_vez_de_404(): void
    {
        (new TiposDocumentosSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);

        $response = $this->actingAs($solicitante->user)->get(route('tramite.paso2-documentos', ['escuela' => $escuela->id]));

        $response->assertRedirect(route('tramite.paso2', ['escuela' => $escuela->id]));
    }
}
