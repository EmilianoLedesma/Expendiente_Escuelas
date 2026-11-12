<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\DTO\DatosIngresoProyecto;
use App\Application\ProyectoEducativo\GuardarIngresoProyecto;
use App\Livewire\Tramite\Paso3\ProyectoEducativoSeccion;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3IngresoProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private const SLUG = 'perfil-de-ingreso';

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

    private function ruta(EscuelaNivel $nivel): string
    {
        return route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $nivel->id, 'seccion' => self::SLUG]);
    }

    private function llenar($componente)
    {
        return $componente->set([
            'perfil_ingreso' => $this->palabras(50),
            'mecanismo_ingreso' => $this->palabras(100),
            'costo_usuario' => $this->palabras(30),
            'instrumentos_evaluacion' => $this->palabras(100),
        ]);
    }

    public function test_el_primer_bloque_muestra_solo_perfil_y_mecanismo_con_etiquetas_y_placeholders_del_dueno(): void
    {
        $html = $this->get($this->ruta($this->nivel()))->assertOk()
            ->assertSee('Perfil de Ingreso (50 a 150 palabras)')
            ->assertSee('Mecanismo de Ingreso (100 a 250 palabras)')
            ->assertSee('Describa las características, conocimientos y habilidades que debe poseer el aspirante...')
            ->assertSee('Describa el proceso y los pasos que debe seguir el aspirante para ingresar a la institución...')
            ->assertSee('Siguiente')
            ->assertDontSee('Contenido por definir')
            ->assertDontSee('Costo para el Usuario')
            ->assertDontSee('Instrumentos de Evaluación para Nuevo Ingreso')
            ->getContent();

        $this->assertSame(2, substr_count($html, '<textarea'));
    }

    public function test_el_segundo_bloque_muestra_solo_costo_e_instrumentos(): void
    {
        $c = $this->llenar($this->componente($this->nivel()))->call('siguienteIngreso');

        $c->assertSet('bloque', 2)
            ->assertSee('Costo para el Usuario (30 a 100 palabras)')
            ->assertSee('Indique los costos asociados al proceso de ingreso...')
            ->assertSee('Instrumentos de Evaluación para Nuevo Ingreso (100 a 250 palabras)')
            ->assertSee('Describa los instrumentos o exámenes que se aplicarán a los aspirantes...')
            ->assertDontSee('Perfil de Ingreso (50 a 150 palabras)')
            ->assertDontSee('Mecanismo de Ingreso (100 a 250 palabras)');
        $this->assertSame(2, substr_count($c->html(), '<textarea'));
    }

    public function test_solo_el_ultimo_bloque_ofrece_guardar(): void
    {
        $c = $this->llenar($this->componente($this->nivel()));
        $c->assertSeeHtml('wire:submit="siguienteIngreso"')->call('siguienteIngreso')->assertSet('bloque', 2)->assertSeeHtml('wire:submit="guardar"');
        $c->call('siguienteIngreso')->assertSet('bloque', 2);
    }

    public function test_siguiente_valida_solo_los_campos_del_bloque_y_no_avanza_si_fallan(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set('perfil_ingreso', $this->palabras(10))->call('siguienteIngreso')
            ->assertSet('bloque', 1)
            ->assertHasErrors(['perfil_ingreso', 'mecanismo_ingreso'])
            ->assertHasNoErrors(['costo_usuario', 'instrumentos_evaluacion'])
            ->set(['perfil_ingreso' => $this->palabras(50), 'mecanismo_ingreso' => $this->palabras(100)])->call('siguienteIngreso')
            ->assertSet('bloque', 2)->assertHasNoErrors();

        $this->assertSame(0, DB::table('proyecto_educativo_ingreso')->count());
    }

    public function test_anterior_vuelve_sin_validar_ni_perder_lo_tecleado(): void
    {
        $this->llenar($this->componente($this->nivel()))->call('siguienteIngreso')->set('costo_usuario', 'corto')
            ->call('anteriorIngreso')->assertSet('bloque', 1)->assertHasNoErrors()->assertSet('costo_usuario', 'corto');
    }

    public function test_abre_con_lo_guardado(): void
    {
        $nivel = $this->nivel();
        app(GuardarIngresoProyecto::class)->ejecutar($nivel->id, new DatosIngresoProyecto($this->palabras(60), $this->palabras(110), $this->palabras(40), $this->palabras(120)));

        $this->componente($nivel)->assertSet('perfil_ingreso', $this->palabras(60))->assertSet('instrumentos_evaluacion', $this->palabras(120));
    }

    public function test_un_guardado_parcial_muestra_todos_los_errores_y_no_escribe(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set('perfil_ingreso', $this->palabras(10))->call('guardar')
            ->assertHasErrors(['perfil_ingreso', 'mecanismo_ingreso', 'costo_usuario', 'instrumentos_evaluacion'])
            ->assertSet('bloque', 1)
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $this->assertSame(0, DB::table('proyecto_educativo_ingreso')->count());
    }

    public function test_los_campos_llevan_las_restricciones_del_lado_del_cliente(): void
    {
        $c = $this->llenar($this->componente($this->nivel()));
        $html = $c->html();
        foreach (['perfil_ingreso', 'mecanismo_ingreso'] as $campo) {
            $this->assertStringContainsString("\$wire.entangle('{$campo}')", $html);
        }
        $this->assertStringContainsString('x-on:input="recortar()"', $html);
        $this->assertStringContainsString('max: 150', $html);
        $this->assertStringContainsString('min: 50', $html);

        $html = $c->call('siguienteIngreso')->html();
        foreach (['costo_usuario', 'instrumentos_evaluacion'] as $campo) {
            $this->assertStringContainsString("\$wire.entangle('{$campo}')", $html);
        }
        $this->assertStringContainsString('max: 100', $html);
        $this->assertStringContainsString('min: 30', $html);
    }

    public function test_guardar_incompleto_lleva_al_primer_bloque_con_error(): void
    {
        $this->llenar($this->componente($this->nivel()))->call('siguienteIngreso')->set('perfil_ingreso', '')
            ->call('guardar')->assertHasErrors(['perfil_ingreso'])->assertSet('bloque', 1);
    }

    public function test_guardar_completo_avisa_que_la_seccion_esta_completa(): void
    {
        $nivel = $this->nivel();

        $this->llenar($this->componente($nivel))->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa');

        $this->assertSame(1, DB::table('proyecto_educativo_ingreso')->where('escuela_nivel_id', $nivel->id)->count());
    }

    public function test_pasarse_del_maximo_marca_el_campo_y_no_guarda(): void
    {
        $nivel = $this->nivel();

        $this->llenar($this->componente($nivel))->set('costo_usuario', $this->palabras(101))->call('guardar')
            ->assertHasErrors(['costo_usuario'])
            ->assertDispatched('toast', tipo: 'error');

        $this->assertSame(0, DB::table('proyecto_educativo_ingreso')->count());
    }

    public function test_tramite_enviado_no_guarda(): void
    {
        $nivel = $this->nivel();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->llenar($this->componente($nivel))->call('guardar')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'El trámite ya se envió a revisión y no puede modificarse.');

        $this->assertSame(0, DB::table('proyecto_educativo_ingreso')->count());
    }

    public function test_otro_solicitante_recibe_403_y_primaria_404(): void
    {
        $nivel = $this->nivel();
        $this->actingAs(Solicitante::factory()->create()->user)->get($this->ruta($nivel))->assertForbidden();

        $primaria = $this->nivelListoPara('primaria', 'matricula');
        $this->actingAs($primaria->escuela->solicitante->user)->get(route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $primaria->id, 'seccion' => self::SLUG]))->assertNotFound();
    }

    public function test_indice_marca_completa_la_seccion_ocho_cuando_lo_esta(): void
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
