<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Livewire\Tramite\Paso3\MobiliarioNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\MobiliarioConceptosSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

class Paso3MobiliarioNivelTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    private function crearEscuelaNivel(Solicitante $solicitante, string $claveNivel): EscuelaNivel
    {
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();
        (new MobiliarioConceptosSeeder)->run();

        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $this->completarPaso2($escuela->id);
        $nivel = NivelEducativo::where('clave', $claveNivel)->first();
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        return EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => $nivel->id,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
    }

    public function test_inicial_ve_las_cinco_salas_y_el_grupo_de_usos_multiples(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuelaNivel = $this->crearEscuelaNivel($solicitante, 'inicial');

        $response = $this->actingAs($solicitante->user)
            ->get(route('tramite.paso3-mobiliario', ['escuelaNivel' => $escuelaNivel->id]));

        $response->assertOk();
        foreach (['Lactantes A', 'Lactantes B', 'Lactantes C', 'Maternal A', 'Maternal B', 'Sala de Usos Múltiples'] as $grupo) {
            $response->assertSee($grupo, false);
        }
    }

    public function test_un_nivel_distinto_de_inicial_se_salta_y_queda_completado(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuelaNivel = $this->crearEscuelaNivel($solicitante, 'preescolar');

        $response = $this->actingAs($solicitante->user)
            ->get(route('tramite.paso3-mobiliario', ['escuelaNivel' => $escuelaNivel->id]));

        $response->assertRedirect(route('tramite.paso3-proximos-pasos', ['escuelaNivel' => $escuelaNivel->id]));

        $pasoId = DB::table('pasos_captura')->where('clave', 'mobiliario')->value('id');
        $this->assertDatabaseHas('escuela_nivel_pasos', [
            'escuela_nivel_id' => $escuelaNivel->id,
            'paso_captura_id' => $pasoId,
            'estado' => 'completado',
        ]);
    }

    public function test_guardar_persiste_las_cantidades_y_redirige(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuelaNivel = $this->crearEscuelaNivel($solicitante, 'inicial');
        $conceptoId = (int) DB::table('mobiliario_conceptos')->orderBy('id')->value('id');

        Livewire::actingAs($solicitante->user)
            ->test(MobiliarioNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->set("cantidades.{$conceptoId}", 5)
            ->call('guardar')
            ->assertRedirect(route('tramite.paso3-proximos-pasos', ['escuelaNivel' => $escuelaNivel->id]));

        $this->assertDatabaseHas('mobiliario_nivel', [
            'escuela_nivel_id' => $escuelaNivel->id,
            'concepto_id' => $conceptoId,
            'cantidad_declarada' => 5,
        ]);
    }

    public function test_rechaza_una_cantidad_negativa_sin_escribir(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuelaNivel = $this->crearEscuelaNivel($solicitante, 'inicial');
        $conceptoId = (int) DB::table('mobiliario_conceptos')->orderBy('id')->value('id');

        Livewire::actingAs($solicitante->user)
            ->test(MobiliarioNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->set("cantidades.{$conceptoId}", -3)
            ->call('guardar')
            ->assertHasErrors("cantidades.{$conceptoId}");

        $this->assertDatabaseCount('mobiliario_nivel', 0);
    }

    public function test_precarga_las_cantidades_ya_capturadas(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuelaNivel = $this->crearEscuelaNivel($solicitante, 'inicial');
        $conceptoId = (int) DB::table('mobiliario_conceptos')->orderBy('id')->value('id');
        DB::table('mobiliario_nivel')->insert([
            'escuela_nivel_id' => $escuelaNivel->id,
            'concepto_id' => $conceptoId,
            'cantidad_declarada' => 12,
        ]);

        Livewire::actingAs($solicitante->user)
            ->test(MobiliarioNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->assertSet("cantidades.{$conceptoId}", 12);
    }

    public function test_un_no_dueno_recibe_403(): void
    {
        $dueno = Solicitante::factory()->create();
        $escuelaNivel = $this->crearEscuelaNivel($dueno, 'inicial');
        $otro = Solicitante::factory()->create();

        $response = $this->actingAs($otro->user)
            ->get(route('tramite.paso3-mobiliario', ['escuelaNivel' => $escuelaNivel->id]));

        $response->assertForbidden();
    }
}
