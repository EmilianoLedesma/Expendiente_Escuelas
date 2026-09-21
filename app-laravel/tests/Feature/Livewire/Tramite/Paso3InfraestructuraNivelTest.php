<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Livewire\Tramite\Paso3\InfraestructuraNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Database\Seeders\TiposEspaciosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class Paso3InfraestructuraNivelTest extends TestCase
{
    use RefreshDatabase;

    private Solicitante $solicitante;

    private Plantel $plantel;

    private Escuela $escuela;

    protected function setUp(): void
    {
        parent::setUp();

        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();
        (new TiposEspaciosSeeder)->run();

        $this->solicitante = Solicitante::factory()->create();
        $this->plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuela = Escuela::create(['plantel_id' => $this->plantel->id, 'solicitante_id' => $this->solicitante->id]);
    }

    private function escuelaNivel(string $claveNivel): EscuelaNivel
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

    private function idTipo(string $clave): int
    {
        return (int) DB::table('tipos_espacios')->where('clave', $clave)->value('id');
    }

    public function test_muestra_solo_los_espacios_aplicables_al_nivel(): void
    {
        $primaria = $this->escuelaNivel('primaria');

        $response = $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso3-infraestructura', ['escuelaNivel' => $primaria->id]));

        $response->assertOk();
        $response->assertSee('Centro de documentación / Biblioteca', false);
        $response->assertDontSee('Filtro / Recepción', false);
    }

    public function test_inicial_ve_filtro_recepcion_y_no_biblioteca(): void
    {
        $inicial = $this->escuelaNivel('inicial');

        $response = $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso3-infraestructura', ['escuelaNivel' => $inicial->id]));

        $response->assertOk();
        $response->assertSee('Filtro / Recepción', false);
        $response->assertDontSee('Centro de documentación / Biblioteca', false);
    }

    public function test_inicial_ve_las_categorias_de_sanitarios_de_inicial(): void
    {
        $inicial = $this->escuelaNivel('inicial');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $inicial])
            ->assertSet('soloLectura', false)
            ->assertSee('Bacinicas');
    }

    public function test_guardar_escribe_espacios_aulas_y_redirige_a_mobiliario(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $direccionId = $this->idTipo('direccion');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$direccionId}.cantidad", 1)
            ->set("espacios.{$direccionId}.superficieM2", 18.5)
            ->set('sanitarios.alumnado_masculino.cantidadRetretes', 4)
            ->set('numeroAulas', 6)
            ->set('superficieAulasM2', 240)
            ->call('guardar')
            ->assertRedirect(route('tramite.paso3-mobiliario', ['escuelaNivel' => $primaria->id]));

        $this->assertDatabaseHas('instalaciones_espacios', [
            'plantel_id' => $this->plantel->id,
            'tipo_espacio_id' => $direccionId,
            'cantidad' => 1,
        ]);
        $this->assertDatabaseHas('aulas_nivel', ['escuela_nivel_id' => $primaria->id, 'numero_aulas' => 6]);
    }

    public function test_no_escribe_los_espacios_dejados_en_blanco(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $direccionId = $this->idTipo('direccion');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$direccionId}.cantidad", 1)
            ->set('numeroAulas', 6)
            ->call('guardar');

        $this->assertSame(1, DB::table('instalaciones_espacios')->where('plantel_id', $this->plantel->id)->count());
    }

    public function test_numero_de_aulas_es_obligatorio(): void
    {
        $primaria = $this->escuelaNivel('primaria');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set('numeroAulas', '')
            ->call('guardar')
            ->assertHasErrors('numeroAulas');

        $this->assertDatabaseCount('aulas_nivel', 0);
    }

    public function test_un_segundo_nivel_del_mismo_plantel_ve_la_infraestructura_en_solo_lectura(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $direccionId = $this->idTipo('direccion');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$direccionId}.cantidad", 1)
            ->set('numeroAulas', 6)
            ->call('guardar');

        $preescolar = $this->escuelaNivel('preescolar');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $preescolar])
            ->assertSet('soloLectura', true)
            ->set('numeroAulas', 3)
            ->call('guardar')
            ->assertRedirect(route('tramite.paso3-mobiliario', ['escuelaNivel' => $preescolar->id]));

        $this->assertSame(1, DB::table('instalaciones_espacios')->where('plantel_id', $this->plantel->id)->count());
        $this->assertDatabaseHas('aulas_nivel', ['escuela_nivel_id' => $preescolar->id, 'numero_aulas' => 3]);
    }

    public function test_un_no_dueno_recibe_403(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $otro = Solicitante::factory()->create();

        $response = $this->actingAs($otro->user)
            ->get(route('tramite.paso3-infraestructura', ['escuelaNivel' => $primaria->id]));

        $response->assertForbidden();
    }
}
