<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\DTO\DatosEnfoqueProyecto;
use App\Application\ProyectoEducativo\GuardarEnfoqueProyecto;
use App\Livewire\Tramite\Paso3\ProyectoEducativoSeccion;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3EnfoqueProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private const SLUG = 'enfoque-pedagogico';

    private function palabras(int $n): string
    {
        return trim(str_repeat('palabra ', $n));
    }

    private function nivel(): EscuelaNivel
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->actingAs($nivel->escuela->solicitante->user);

        return $nivel;
    }

    private function componente(EscuelaNivel $nivel)
    {
        return Livewire::test(ProyectoEducativoSeccion::class, ['escuelaNivel' => $nivel, 'seccion' => self::SLUG]);
    }

    private function ruta(EscuelaNivel $nivel, string $slug = self::SLUG): string
    {
        return route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $nivel->id, 'seccion' => $slug]);
    }

    /** Llena todo con lo mínimo válido (2 ejemplos por lista). */
    private function llenar($componente)
    {
        return $componente->set([
            'propuesta_global' => $this->palabras(100),
            'apropiacion_enfoque' => $this->palabras(450),
            'estrategias_centrales' => [$this->palabras(20), $this->palabras(20)],
            'actividades_sustantivas' => [$this->palabras(20), $this->palabras(20)],
        ]);
    }

    public function test_la_primera_pantalla_muestra_un_solo_campo_con_la_etiqueta_y_el_placeholder_del_dueno(): void
    {
        $nivel = $this->nivel();

        $html = $this->get($this->ruta($nivel))->assertOk()
            ->assertSee('Propuesta Global del Proyecto Educativo (100 a 200 palabras)')
            ->assertSee('Describa de manera integral la propuesta educativa del proyecto...')
            ->assertSee('Siguiente')
            ->assertDontSee('Contenido por definir')
            ->assertDontSee('Constructivista-Humanista (450 a 600 palabras)')
            ->assertDontSee('Estrategias centrales')
            ->getContent();

        $this->assertSame(1, substr_count($html, '<textarea'));
    }

    public function test_cada_pantalla_tiene_un_solo_textarea_con_su_etiqueta(): void
    {
        $c = $this->llenar($this->componente($this->nivel()));

        $c->call('siguienteEnfoque')->assertSet('pantalla', 2)
            ->assertSee('Apropiación del Enfoque Pedagógico-Didáctico Constructivista-Humanista (450 a 600 palabras)')
            ->assertSee('Explique cómo la institución se apropia del enfoque constructivista-humanista en su práctica educativa...');
        $this->assertSame(1, substr_count($c->html(), '<textarea'));

        $c->call('siguienteEnfoque')->assertSet('pantalla', 3)
            ->assertSee('Estrategias y Actividades Didácticas Principales (2 a 5 ejemplos)')
            ->assertSee('Estrategias centrales')
            ->assertSee('Describa las estrategias didácticas principales que se implementarán...')
            ->assertDontSee('Actividades sustantivas');
        $this->assertSame(1, substr_count($c->html(), '<textarea'));

        $c->call('siguienteEnfoque')->call('siguienteEnfoque')->assertSet('pantalla', 5)
            ->assertSee('Actividades sustantivas')
            ->assertSee('Describa las actividades sustantivas que darán soporte a las estrategias...')
            ->assertDontSee('Describa las estrategias didácticas principales');
        $this->assertSame(1, substr_count($c->html(), '<textarea'));
    }

    public function test_el_ultimo_campo_ofrece_guardar_y_los_demas_siguiente(): void
    {
        $c = $this->llenar($this->componente($this->nivel()));
        $c->assertSeeHtml('wire:submit="siguienteEnfoque"');

        foreach (range(1, 5) as $_) {
            $c->call('siguienteEnfoque');
        }

        $c->assertSet('pantalla', 6)->assertSeeHtml('wire:submit="guardar"');
        // En la última no se avanza más.
        $c->call('siguienteEnfoque')->assertSet('pantalla', 6);
    }

    public function test_siguiente_valida_solo_el_campo_actual_y_no_avanza_si_falla(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set('propuesta_global', $this->palabras(10))->call('siguienteEnfoque')
            ->assertSet('pantalla', 1)
            ->assertHasErrors(['propuesta_global'])
            ->assertHasNoErrors(['apropiacion_enfoque', 'estrategias_centrales', 'actividades_sustantivas'])
            ->set('propuesta_global', $this->palabras(100))->call('siguienteEnfoque')
            ->assertSet('pantalla', 2)
            ->assertHasNoErrors();

        $this->assertSame(0, DB::table('proyecto_educativo_enfoque')->count());
    }

    public function test_siguiente_en_un_ejemplo_valida_su_rango_de_palabras(): void
    {
        $c = $this->llenar($this->componente($this->nivel()))->call('siguienteEnfoque')->call('siguienteEnfoque');

        $c->set('estrategias_centrales.0', $this->palabras(19))->call('siguienteEnfoque')
            ->assertSet('pantalla', 3)->assertHasErrors(['estrategias_centrales.0'])
            ->set('estrategias_centrales.0', $this->palabras(20))->call('siguienteEnfoque')
            ->assertSet('pantalla', 4)->assertHasNoErrors();
    }

    public function test_anterior_vuelve_sin_validar_ni_perder_lo_tecleado(): void
    {
        $this->llenar($this->componente($this->nivel()))->call('siguienteEnfoque')->set('apropiacion_enfoque', 'corto')
            ->call('anteriorEnfoque')->assertSet('pantalla', 1)->assertHasNoErrors()->assertSet('apropiacion_enfoque', 'corto');
    }

    public function test_abre_con_lo_guardado_y_en_la_primera_pantalla(): void
    {
        $nivel = $this->nivel();
        app(GuardarEnfoqueProyecto::class)->ejecutar($nivel->id, new DatosEnfoqueProyecto(
            $this->palabras(100), $this->palabras(450), [$this->palabras(20), $this->palabras(30), $this->palabras(40)], [$this->palabras(20), $this->palabras(20)],
        ));

        $this->componente($nivel)->assertSet('pantalla', 1)->assertSet('propuesta_global', $this->palabras(100))
            ->assertSet('estrategias_centrales', [$this->palabras(20), $this->palabras(30), $this->palabras(40)])
            ->assertSet('actividades_sustantivas', [$this->palabras(20), $this->palabras(20)]);
    }

    public function test_sin_nada_guardado_cada_lista_arranca_con_dos_ejemplos_vacios(): void
    {
        $this->componente($this->nivel())->assertSet('estrategias_centrales', ['', ''])->assertSet('actividades_sustantivas', ['', '']);
    }

    public function test_agregar_suma_un_ejemplo_hasta_cinco_y_pasa_a_el(): void
    {
        $c = $this->llenar($this->componente($this->nivel()))->call('siguienteEnfoque')->call('siguienteEnfoque'); // pantalla 3: 1.ª estrategia

        $c->call('agregarEjemplo')->assertCount('estrategias_centrales', 3)->assertSet('pantalla', 5)->assertSet('estrategias_centrales.2', '');
        $c->call('agregarEjemplo')->assertCount('estrategias_centrales', 4)->assertSet('pantalla', 6);
        $c->call('agregarEjemplo')->assertCount('estrategias_centrales', 5)->assertSet('pantalla', 7);
        $c->call('agregarEjemplo')->assertCount('estrategias_centrales', 5)->assertSet('pantalla', 7); // tope de 5
        $c->assertCount('actividades_sustantivas', 2);
    }

    public function test_quitar_borra_el_ejemplo_actual_hasta_dejar_dos(): void
    {
        [$a, $b, $c3] = [$this->palabras(20), $this->palabras(21), $this->palabras(22)];
        $c = $this->llenar($this->componente($this->nivel()))->set('estrategias_centrales', [$a, $b, $c3])
            ->call('siguienteEnfoque')->call('siguienteEnfoque')->call('siguienteEnfoque'); // pantalla 4: 2.ª estrategia

        $c->call('quitarEjemplo')->assertSet('estrategias_centrales', [$a, $c3])->assertSet('pantalla', 4);
        $c->call('quitarEjemplo')->assertSet('estrategias_centrales', [$a, $c3])->assertSet('pantalla', 4); // mínimo de 2
    }

    public function test_agregar_y_quitar_no_hacen_nada_fuera_de_una_lista(): void
    {
        $this->componente($this->nivel())->call('agregarEjemplo')->call('quitarEjemplo')
            ->assertSet('pantalla', 1)->assertCount('estrategias_centrales', 2)->assertCount('actividades_sustantivas', 2);
    }

    public function test_guardar_completo_guarda_y_avisa_que_la_seccion_esta_completa(): void
    {
        $nivel = $this->nivel();

        $this->llenar($this->componente($nivel))->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa');

        $this->assertSame(1, DB::table('proyecto_educativo_enfoque')->where('escuela_nivel_id', $nivel->id)->count());
    }

    public function test_guardar_incompleto_no_escribe_y_lleva_al_primer_campo_con_error(): void
    {
        $nivel = $this->nivel();

        $this->llenar($this->componente($nivel))->set('estrategias_centrales.1', $this->palabras(5))->set('apropiacion_enfoque', '')
            ->call('guardar')
            ->assertHasErrors(['apropiacion_enfoque', 'estrategias_centrales.1'])
            ->assertSet('pantalla', 2)
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $this->assertSame(0, DB::table('proyecto_educativo_enfoque')->count());
    }

    public function test_pasarse_del_maximo_de_un_ejemplo_no_guarda(): void
    {
        $nivel = $this->nivel();

        $this->llenar($this->componente($nivel))->set('actividades_sustantivas.0', $this->palabras(81))->call('guardar')
            ->assertHasErrors(['actividades_sustantivas.0'])->assertSet('pantalla', 5);

        $this->assertSame(0, DB::table('proyecto_educativo_enfoque')->count());
    }

    public function test_tramite_enviado_no_guarda(): void
    {
        $nivel = $this->nivel();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->llenar($this->componente($nivel))->call('guardar')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'El trámite ya se envió a revisión y no puede modificarse.');

        $this->assertSame(0, DB::table('proyecto_educativo_enfoque')->count());
    }

    public function test_otro_solicitante_recibe_403_y_primaria_404(): void
    {
        $nivel = $this->nivel();
        $this->actingAs(Solicitante::factory()->create()->user)->get($this->ruta($nivel))->assertForbidden();

        $primaria = $this->nivelListoPara('primaria', 'matricula');
        $this->actingAs($primaria->escuela->solicitante->user)->get($this->ruta($primaria))->assertNotFound();
    }

    public function test_indice_marca_completa_la_seccion_cuatro_cuando_lo_esta(): void
    {
        $nivel = $this->nivel();
        $indice = route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $nivel->id]);

        $this->assertSame(20, substr_count($this->get($indice)->assertOk()->getContent(), 'Pendiente'));

        $this->llenar($this->componente($nivel))->call('guardar');
        $html = $this->get($indice)->getContent();
        $this->assertSame(19, substr_count($html, 'Pendiente'));
        $this->assertSame(1, substr_count($html, 'Completa'));
    }
}
