<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Livewire\Tramite\Paso3\DatosInmueble;
use App\Livewire\Tramite\Paso3\MobiliarioNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\MobiliarioConcepto;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use App\Models\TipoEspacio;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

class Paso3VistaTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    private Solicitante $solicitante;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->solicitante = Solicitante::factory()->create();
        $this->actingAs($this->solicitante->user);
    }

    private function escuelaNivel(string $clave, string ...$completados): EscuelaNivel
    {
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $this->solicitante->id]);
        $this->completarPaso2($escuela->id);
        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', $clave)->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
        foreach ($completados as $paso) {
            (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, $paso);
        }

        return $escuelaNivel;
    }

    public function test_inmueble_tiene_encabezado_nivel_y_etiquetas(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');

        $this->get(route('tramite.paso3-inmueble', ['escuelaNivel' => $escuelaNivel->id]))
            ->assertOk()
            ->assertSee('Primaria · Paso 5 de 6')
            ->assertSee('href="'.route('tramite.resumen', ['escuela' => $escuelaNivel->escuela_id]).'"', false)
            ->assertSee('for="metrosTotales"', false)
            ->assertSee('for="colindanciaNorte"', false)
            ->assertSee('aria-current="step"', false);
    }

    public function test_un_servicio_cercano_agregado_tiene_etiquetas(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria');

        Livewire::test(DatosInmueble::class, ['escuelaNivel' => $escuelaNivel])
            ->call('agregarServicio')
            ->assertSeeHtml('for="serviciosCercanos.0.nombre"')
            ->assertSeeHtml('id="serviciosCercanos.0.distanciaUnidad"')
            ->assertSee('Institución 1');
    }

    public function test_infraestructura_etiqueta_cada_campo_de_la_tabla(): void
    {
        $escuelaNivel = $this->escuelaNivel('primaria', 'inmueble');
        $bibliotecaId = TipoEspacio::where('nombre', 'Centro de documentación / Biblioteca')->value('id');

        $this->get(route('tramite.paso3-infraestructura', ['escuelaNivel' => $escuelaNivel->id]))
            ->assertOk()
            ->assertSee('Primaria · Paso 6 de 6')
            ->assertSee('for="espacios.'.$bibliotecaId.'.cantidad"', false)
            ->assertSee('id="espacios.'.$bibliotecaId.'.cantidad"', false)
            ->assertSee('Material de la biblioteca')
            ->assertSee('for="numeroAulas"', false);
    }

    public function test_mobiliario_usa_una_tabla_por_sala_y_enlaza_el_error(): void
    {
        $escuelaNivel = $this->escuelaNivel('inicial', 'inmueble', 'infraestructura');
        $conceptoId = MobiliarioConcepto::orderBy('id')->value('id');

        Livewire::test(MobiliarioNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->assertSee('Educación Inicial · Paso 7 de 7')
            ->assertSeeHtml('<caption')
            ->assertSeeHtml('for="cantidades.'.$conceptoId.'"')
            ->call('guardar')
            ->assertHasErrors('cantidades')
            ->assertSeeHtml('href="#cantidades"')
            ->assertSeeHtml('id="cantidades"');
    }
}
