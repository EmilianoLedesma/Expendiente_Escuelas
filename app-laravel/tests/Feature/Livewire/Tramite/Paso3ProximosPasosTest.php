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
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

class Paso3ProximosPasosTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    private function crearEscuelaNivelPara(Solicitante $solicitante): EscuelaNivel
    {
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();

        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $this->completarPaso2($escuela->id);
        $nivel = NivelEducativo::where('clave', 'primaria')->first();
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => $nivel->id,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
        // WS-1.3: el aterrizaje solo es alcanzable con los sub-pasos construidos completados.
        foreach (['inmueble', 'infraestructura', 'mobiliario'] as $paso) {
            (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, $paso);
        }

        return $escuelaNivel;
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

    public function test_muestra_enlace_a_un_segundo_nivel_de_la_misma_escuela_que_aun_no_termina(): void
    {
        $solicitante = Solicitante::factory()->create();
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();

        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $this->completarPaso2($escuela->id);
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        $primaria = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
        $inicial = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'inicial')->value('id'),
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);

        (new MarcarPasoCompletado)->ejecutar($primaria->id, 'inmueble');
        (new MarcarPasoCompletado)->ejecutar($primaria->id, 'infraestructura');
        (new MarcarPasoCompletado)->ejecutar($primaria->id, 'mobiliario');

        $response = $this->actingAs($solicitante->user)
            ->get(route('tramite.paso3-proximos-pasos', ['escuelaNivel' => $primaria->id]));

        $response->assertOk();
        $response->assertSee('href="'.route('tramite.paso3-inmueble', ['escuelaNivel' => $inicial->id]).'"', false);
    }

    public function test_un_segundo_nivel_ya_completo_no_muestra_enlace_de_captura(): void
    {
        $solicitante = Solicitante::factory()->create();
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();

        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $this->completarPaso2($escuela->id);
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        $primaria = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
        $inicial = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'inicial')->value('id'),
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);

        foreach (['inmueble', 'infraestructura', 'mobiliario'] as $paso) {
            (new MarcarPasoCompletado)->ejecutar($primaria->id, $paso);
            (new MarcarPasoCompletado)->ejecutar($inicial->id, $paso);
        }

        $response = $this->actingAs($solicitante->user)
            ->get(route('tramite.paso3-proximos-pasos', ['escuelaNivel' => $primaria->id]));

        $response->assertOk();
        $response->assertDontSee('href="'.route('tramite.paso3-inmueble', ['escuelaNivel' => $inicial->id]).'"', false);
    }
}
