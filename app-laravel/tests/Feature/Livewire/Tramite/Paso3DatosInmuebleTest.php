<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Livewire\Tramite\Paso3\DatosInmueble;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use App\Models\TernaNombre;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

class Paso3DatosInmuebleTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    private Solicitante $solicitante;

    private Plantel $plantel;

    private Escuela $escuela;

    protected function setUp(): void
    {
        parent::setUp();

        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();

        $this->solicitante = Solicitante::factory()->create();
        $this->plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuela = Escuela::create(['plantel_id' => $this->plantel->id, 'solicitante_id' => $this->solicitante->id]);
        $this->completarPaso2($this->escuela->id);
    }

    private function escuelaNivel(string $claveNivel = 'primaria'): EscuelaNivel
    {
        $nivel = NivelEducativo::where('clave', $claveNivel)->first();
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        return EscuelaNivel::create([
            'escuela_id' => $this->escuela->id,
            'nivel_educativo_id' => $nivel->id,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
    }

    public function test_la_ruta_de_paso3_ahora_muestra_el_sub_paso_1(): void
    {
        $escuelaNivel = $this->escuelaNivel();

        $response = $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso3-inmueble', ['escuelaNivel' => $escuelaNivel->id]));

        $response->assertOk();
        $response->assertSee('Datos del inmueble', false);
    }

    public function test_muestra_la_terna_de_nombres_ya_capturada_en_solo_lectura(): void
    {
        $escuelaNivel = $this->escuelaNivel();
        TernaNombre::create(['escuela_id' => $this->escuela->id, 'numero_propuesta' => 1, 'nombre_propuesto' => 'Colegio Alfa']);

        $response = $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso3-inmueble', ['escuelaNivel' => $escuelaNivel->id]));

        $response->assertSee('Colegio Alfa', false);
    }

    public function test_guardar_persiste_y_redirige_a_infraestructura(): void
    {
        $escuelaNivel = $this->escuelaNivel();

        Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $escuelaNivel])
            ->set('metrosTotales', 1200.5)
            ->set('metrosConstruidos', 640)
            ->set('tieneAstaBandera', true)
            ->call('guardar')
            ->assertRedirect(route('tramite.paso3-infraestructura', ['escuelaNivel' => $escuelaNivel->id]));

        $this->assertEquals(1200.5, (float) $this->plantel->fresh()->metros_totales);
    }

    public function test_metros_totales_es_obligatorio(): void
    {
        $escuelaNivel = $this->escuelaNivel();

        Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $escuelaNivel])
            ->set('metrosTotales', '')
            ->call('guardar')
            ->assertHasErrors('metrosTotales');

        $this->assertNull($this->plantel->fresh()->metros_totales);
    }

    public function test_guarda_los_servicios_cercanos_agregados(): void
    {
        $escuelaNivel = $this->escuelaNivel();

        Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $escuelaNivel])
            ->set('metrosTotales', 900)
            ->call('agregarServicio')
            ->set('serviciosCercanos.0.nombre', 'Cruz Roja')
            ->set('serviciosCercanos.0.tipo', 'emergencia')
            ->set('serviciosCercanos.0.distanciaValor', 1.5)
            ->set('serviciosCercanos.0.distanciaUnidad', 'km')
            ->call('guardar');

        $this->assertDatabaseHas('servicios_cercanos', [
            'plantel_id' => $this->plantel->id,
            'nombre' => 'Cruz Roja',
            'tipo' => 'emergencia',
        ]);
    }

    public function test_guarda_un_estudio_actual_con_la_variante_otro(): void
    {
        $escuelaNivel = $this->escuelaNivel();

        Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $escuelaNivel])
            ->set('metrosTotales', 900)
            ->call('agregarEstudio')
            ->set('estudiosActuales.0.nivelEducativoId', '')
            ->set('estudiosActuales.0.otroNivelTexto', 'Academia de inglés')
            ->set('estudiosActuales.0.numeroAlumnos', 30)
            ->call('guardar');

        $this->assertDatabaseHas('inmueble_estudios_actuales', [
            'plantel_id' => $this->plantel->id,
            'nivel_educativo_id' => null,
            'otro_nivel_texto' => 'Academia de inglés',
            'numero_alumnos' => 30,
        ]);
    }

    public function test_un_segundo_nivel_del_mismo_plantel_se_auto_completa_y_avanza(): void
    {
        $primaria = $this->escuelaNivel('primaria');

        Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $primaria])
            ->set('metrosTotales', 900)
            ->call('guardar');

        $preescolar = $this->escuelaNivel('preescolar');

        $response = $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso3-inmueble', ['escuelaNivel' => $preescolar->id]));

        $response->assertRedirect(route('tramite.paso3-infraestructura', ['escuelaNivel' => $preescolar->id]));

        $pasoId = DB::table('pasos_captura')->where('clave', 'inmueble')->value('id');
        $this->assertDatabaseHas('escuela_nivel_pasos', [
            'escuela_nivel_id' => $preescolar->id,
            'paso_captura_id' => $pasoId,
            'estado' => 'completado',
        ]);
    }

    public function test_un_no_dueno_recibe_403(): void
    {
        $escuelaNivel = $this->escuelaNivel();
        $otro = Solicitante::factory()->create();

        $response = $this->actingAs($otro->user)
            ->get(route('tramite.paso3-inmueble', ['escuelaNivel' => $escuelaNivel->id]));

        $response->assertForbidden();
    }
}
