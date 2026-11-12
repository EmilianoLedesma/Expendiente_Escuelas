<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\DTO\DatosFilosofiaProyecto;
use App\Application\ProyectoEducativo\GuardarFilosofiaProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Livewire\Tramite\Paso3\ProyectoEducativoSeccion;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3FilosofiaProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private const SLUG = 'filosofia-institucional';

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

    public function test_muestra_una_sola_pagina_con_las_etiquetas_y_placeholders_del_dueno(): void
    {
        $nivel = $this->nivel();

        $this->get($this->ruta($nivel))->assertOk()
            ->assertSee('Misión (50 a 150 palabras)')
            ->assertSee('Visión (50 a 150 palabras)')
            ->assertSee('Valores (50 a 250 palabras)')
            ->assertSee('Describa la razón de ser de la institución y su compromiso con la comunidad educativa...')
            ->assertSee('Describa la proyección a futuro de la institución y las metas que aspira alcanzar...')
            ->assertSee('Enumere y describa los valores fundamentales que guían el quehacer institucional...')
            ->assertSee('Guardar')
            ->assertDontSee('Contenido por definir')
            ->assertDontSee('Datos Generales')
            ->assertDontSee('Paso 1 de 3');
    }

    public function test_abre_con_lo_guardado(): void
    {
        $nivel = $this->nivel();
        app(GuardarFilosofiaProyecto::class)->ejecutar($nivel->id, new DatosFilosofiaProyecto($this->palabras(50), $this->palabras(51), $this->palabras(52)));

        $this->componente($nivel)->assertSet('mision', $this->palabras(50))->assertSet('valores', $this->palabras(52));
    }

    public function test_un_guardado_parcial_muestra_todos_los_errores_y_no_escribe(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set('mision', $this->palabras(10))->call('guardar')
            ->assertHasErrors(['mision', 'vision', 'valores'])
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó')
            ->assertNotDispatched('toast', titulo: 'Borrador guardado');

        $this->assertSame(0, DB::table('proyecto_educativo_filosofia')->count());
    }

    public function test_los_campos_llevan_las_restricciones_del_lado_del_cliente(): void
    {
        $html = $this->componente($this->nivel())->html();

        foreach (['mision', 'vision', 'valores'] as $campo) {
            $this->assertStringContainsString('$wire.entangle(\''.$campo.'\')', $html);
        }
        $this->assertSame(3, substr_count($html, '<textarea'));
        $this->assertStringContainsString('x-on:input="recortar()"', $html);
        $this->assertStringContainsString('required="required"', $html);
        $this->assertStringContainsString('max: 150', $html);
        $this->assertStringContainsString('max: 250', $html);
        $this->assertStringContainsString('min: 50', $html);
        $this->assertStringContainsString('fuera()', $html);
    }

    public function test_guardar_completo_avisa_que_la_seccion_esta_completa(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set(['mision' => $this->palabras(50), 'vision' => $this->palabras(150), 'valores' => $this->palabras(250)])->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa');

        $this->assertSame(1, DB::table('proyecto_educativo_filosofia')->where('escuela_nivel_id', $nivel->id)->count());
    }

    public function test_pasarse_del_maximo_marca_el_campo_y_no_guarda(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set(['mision' => $this->palabras(151), 'vision' => $this->palabras(151), 'valores' => $this->palabras(251)])->call('guardar')
            ->assertHasErrors(['mision', 'vision', 'valores'])
            ->assertDispatched('toast', tipo: 'error');

        $this->assertSame(0, DB::table('proyecto_educativo_filosofia')->count());
    }

    public function test_tramite_enviado_no_guarda(): void
    {
        $nivel = $this->nivel();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->componente($nivel)->set(['mision' => $this->palabras(50), 'vision' => $this->palabras(50), 'valores' => $this->palabras(50)])->call('guardar')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'El trámite ya se envió a revisión y no puede modificarse.');

        $this->assertSame(0, DB::table('proyecto_educativo_filosofia')->count());
    }

    public function test_las_otras_secciones_siguen_con_el_marcador(): void
    {
        $nivel = $this->nivel();
        $slugs = array_keys(SeccionesProyectoEducativo::todas());
        $this->assertCount(8, array_slice($slugs, 12));

        foreach ([$slugs[12], $slugs[19]] as $slug) {
            $this->get($this->ruta($nivel, $slug))->assertOk()->assertSee('Contenido por definir')->assertDontSee('Misión (50 a 150 palabras)');
        }
    }

    public function test_otro_solicitante_recibe_403_y_primaria_404(): void
    {
        $nivel = $this->nivel();
        $this->actingAs(Solicitante::factory()->create()->user)->get($this->ruta($nivel))->assertForbidden();

        $primaria = $this->nivelListoPara('primaria', 'matricula');
        $this->actingAs($primaria->escuela->solicitante->user)->get($this->ruta($primaria))->assertNotFound();
    }

    public function test_indice_marca_completa_solo_la_seccion_dos_cuando_lo_esta(): void
    {
        $nivel = $this->nivel();
        $indice = route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $nivel->id]);
        $guardar = app(GuardarFilosofiaProyecto::class);

        $this->assertSame(20, substr_count($this->get($indice)->assertOk()->getContent(), 'Pendiente'));

        $guardar->ejecutar($nivel->id, new DatosFilosofiaProyecto($this->palabras(50), $this->palabras(50), $this->palabras(50)));
        $html = $this->get($indice)->getContent();
        $this->assertSame(19, substr_count($html, 'Pendiente'));
        $this->assertSame(1, substr_count($html, 'Completa'));
    }
}
