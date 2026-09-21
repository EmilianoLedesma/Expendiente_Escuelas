<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Paso3ProximosPasosTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuelaNivelPara(Solicitante $solicitante): EscuelaNivel
    {
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();

        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $nivel = NivelEducativo::where('clave', 'primaria')->first();
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        return EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => $nivel->id,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
    }

    public function test_el_dueno_ve_el_aterrizaje(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuelaNivel = $this->crearEscuelaNivelPara($solicitante);

        $response = $this->actingAs($solicitante->user)
            ->get(route('tramite.paso3-proximos-pasos', ['escuelaNivel' => $escuelaNivel->id]));

        $response->assertOk();
        $response->assertSee('el resto de la captura está pendiente', false);
    }

    public function test_un_no_dueno_recibe_403(): void
    {
        $dueno = Solicitante::factory()->create();
        $escuelaNivel = $this->crearEscuelaNivelPara($dueno);
        $otro = Solicitante::factory()->create();

        $response = $this->actingAs($otro->user)
            ->get(route('tramite.paso3-proximos-pasos', ['escuelaNivel' => $escuelaNivel->id]));

        $response->assertForbidden();
    }

    /**
     * Defecto vivo que este trabajo cierra: ningún componente pasaba
     * escuelaNivelId al layout, así que el widget de progreso mostraba los 6
     * sub-pasos como "pendiente" incondicionalmente.
     */
    public function test_el_widget_de_progreso_refleja_los_pasos_completados(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuelaNivel = $this->crearEscuelaNivelPara($solicitante);

        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'inmueble');
        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'infraestructura');
        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'mobiliario');

        $response = $this->actingAs($solicitante->user)
            ->get(route('tramite.paso3-proximos-pasos', ['escuelaNivel' => $escuelaNivel->id]));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertSame(3, substr_count($html, 'data-estado="completado"'));
        $this->assertSame(3, substr_count($html, 'data-estado="pendiente"'));
    }
}
