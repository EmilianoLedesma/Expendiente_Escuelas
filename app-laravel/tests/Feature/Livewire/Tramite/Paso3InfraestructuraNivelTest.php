<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
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
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

class Paso3InfraestructuraNivelTest extends TestCase
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
        (new TiposEspaciosSeeder)->run();

        $this->solicitante = Solicitante::factory()->create();
        $this->plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuela = Escuela::create(['plantel_id' => $this->plantel->id, 'solicitante_id' => $this->solicitante->id]);
        $this->completarPaso2($this->escuela->id);
    }

    private function escuelaNivel(string $claveNivel): EscuelaNivel
    {
        $nivel = NivelEducativo::where('clave', $claveNivel)->first();
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $this->escuela->id,
            'nivel_educativo_id' => $nivel->id,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
        // WS-1.3: Infraestructura solo es alcanzable con Inmueble completado.
        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'inmueble');

        return $escuelaNivel;
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

    public function test_un_segundo_nivel_no_vuelve_a_ofrecer_lo_que_el_plantel_ya_capturo(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $direccionId = $this->idTipo('direccion');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$direccionId}.cantidad", 1)
            ->set('sanitarios.alumnado_masculino.cantidadRetretes', 4)
            ->set('numeroAulas', 6)
            ->call('guardar');

        $preescolar = $this->escuelaNivel('preescolar');

        $testable = Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $preescolar]);

        $this->assertNotContains($direccionId, $testable->instance()->tiposAplicables()->pluck('id')->all());
        $this->assertNotContains('alumnado_masculino', $testable->instance()->categoriasSanitarios());

        $testable->set('numeroAulas', 3)
            ->call('guardar')
            ->assertRedirect(route('tramite.paso3-mobiliario', ['escuelaNivel' => $preescolar->id]));

        $this->assertSame(1, DB::table('instalaciones_espacios')->where('plantel_id', $this->plantel->id)->where('tipo_espacio_id', $direccionId)->count());
        $this->assertDatabaseHas('aulas_nivel', ['escuela_nivel_id' => $preescolar->id, 'numero_aulas' => 3]);
    }

    public function test_un_segundo_nivel_captura_sus_propios_campos_exclusivos_de_inicial(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $direccionId = $this->idTipo('direccion');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$direccionId}.cantidad", 1)
            ->set('sanitarios.alumnado_masculino.cantidadRetretes', 4)
            ->set('numeroAulas', 6)
            ->call('guardar');

        $inicial = $this->escuelaNivel('inicial');
        $filtroId = $this->idTipo('filtro_recepcion');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $inicial])
            ->assertSee('Filtro / Recepción')
            ->set("espacios.{$filtroId}.cantidad", 1)
            ->set('sanitarios.alumnado_maternal.cantidadRetretes', 2)
            ->set('sanitarios.alumnado_maternal.cantidadBacinicas', 3)
            ->set('numeroAulas', 2)
            ->call('guardar');

        $this->assertDatabaseHas('instalaciones_espacios', [
            'plantel_id' => $this->plantel->id,
            'tipo_espacio_id' => $filtroId,
        ]);
        $this->assertSame(2, DB::table('instalaciones_espacios')->where('plantel_id', $this->plantel->id)->count());
        $this->assertDatabaseHas('sanitarios', [
            'plantel_id' => $this->plantel->id,
            'categoria' => 'alumnado_maternal',
            'cantidad_retretes' => 2,
        ]);
        $this->assertSame(2, DB::table('sanitarios')->where('plantel_id', $this->plantel->id)->count());
    }

    public function test_bodega_destinado_a_limpieza_se_guarda(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $bodegaId = $this->idTipo('bodega');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$bodegaId}.cantidad", 1)
            ->set("espacios.{$bodegaId}.destinadoA", 'limpieza')
            ->set('numeroAulas', 6)
            ->call('guardar');

        $this->assertDatabaseHas('instalaciones_espacios', [
            'plantel_id' => $this->plantel->id,
            'tipo_espacio_id' => $bodegaId,
            'destinado_a' => 'limpieza',
        ]);
    }

    public function test_bodega_destinado_a_invalido_no_escribe_nada(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $bodegaId = $this->idTipo('bodega');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$bodegaId}.cantidad", 1)
            ->set("espacios.{$bodegaId}.destinadoA", 'otra-cosa-no-valida')
            ->set('numeroAulas', 6)
            ->call('guardar')
            ->assertHasErrors("espacios.{$bodegaId}.destinadoA");

        $this->assertDatabaseCount('instalaciones_espacios', 0);
    }

    public function test_material_biblioteca_con_id_inexistente_no_causa_error_500_ni_escribe_nada(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $bibliotecaId = $this->idTipo('biblioteca');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$bibliotecaId}.cantidad", 1)
            ->set('materialesBiblioteca.9999.numeroTitulos', 1)
            ->set('numeroAulas', 6)
            ->call('guardar');

        $this->assertDatabaseCount('biblioteca_materiales', 0);
    }

    // WS-2.1 — materials entered without a biblioteca cantidad must not vanish:
    // a biblioteca_materiales row requires an instalaciones_espacios row (FK NOT NULL),
    // so materials present implies the biblioteca espacio row is created.
    public function test_material_biblioteca_sin_cantidad_se_guarda_con_fila_de_biblioteca(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $bibliotecaId = $this->idTipo('biblioteca');
        $librosId = (int) DB::table('tipos_material_biblioteca')->where('clave', 'libros')->value('id');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("materialesBiblioteca.$librosId.numeroTitulos", 300)
            ->set('numeroAulas', 6)
            ->call('guardar');

        $bibliotecaEspacioId = DB::table('instalaciones_espacios')
            ->where('plantel_id', $this->plantel->id)
            ->where('tipo_espacio_id', $bibliotecaId)
            ->value('id');
        $this->assertNotNull($bibliotecaEspacioId, 'Los materiales de biblioteca se descartaron en silencio.');
        $this->assertDatabaseHas('biblioteca_materiales', [
            'instalacion_espacio_id' => $bibliotecaEspacioId,
            'numero_titulos' => 300,
        ]);
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
