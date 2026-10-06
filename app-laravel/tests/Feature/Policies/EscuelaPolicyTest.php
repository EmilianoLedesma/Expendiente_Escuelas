<?php

namespace Tests\Feature\Policies;

use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use App\Models\User;
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

    public function test_delete_solo_para_el_dueno_y_view_update_sin_cambios(): void
    {
        $dueno = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($dueno);
        $otro = Solicitante::factory()->create();
        $sinSolicitante = User::factory()->create();

        foreach (['view', 'update', 'delete'] as $habilidad) {
            $this->assertTrue($dueno->user->can($habilidad, $escuela), "dueño: {$habilidad}");
            $this->assertFalse($otro->user->can($habilidad, $escuela), "otro: {$habilidad}");
            $this->assertFalse($sinSolicitante->can($habilidad, $escuela), "sin solicitante: {$habilidad}");
        }
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
