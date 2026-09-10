<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Paso3PlaceholderTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuelaNivelPara(Solicitante $solicitante): EscuelaNivel
    {
        (new CatalogoMinimoSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $nivel = NivelEducativo::where('clave', 'primaria')->first();
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        return EscuelaNivel::create(['escuela_id' => $escuela->id, 'nivel_educativo_id' => $nivel->id, 'estado_id' => $estadoId, 'tipo_tramite' => 'alta_nueva']);
    }

    public function test_el_dueno_puede_ver_el_placeholder(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuelaNivel = $this->crearEscuelaNivelPara($solicitante);

        $response = $this->actingAs($solicitante->user)->get(route('tramite.paso3-placeholder', ['escuelaNivel' => $escuelaNivel->id]));

        $response->assertOk();
    }

    public function test_un_no_dueno_recibe_403(): void
    {
        $dueno = Solicitante::factory()->create();
        $escuelaNivel = $this->crearEscuelaNivelPara($dueno);
        $otro = Solicitante::factory()->create();

        $response = $this->actingAs($otro->user)->get(route('tramite.paso3-placeholder', ['escuelaNivel' => $escuelaNivel->id]));

        $response->assertForbidden();
    }
}
