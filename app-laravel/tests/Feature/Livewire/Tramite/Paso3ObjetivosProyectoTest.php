<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\DTO\DatosObjetivosProyecto;
use App\Application\ProyectoEducativo\GuardarObjetivosProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Livewire\Tramite\Paso3\ProyectoEducativoSeccion;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3ObjetivosProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private const SLUG = 'objetivos-institucionales';

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
            ->assertSee('Objetivo General (50 a 100 palabras)')
            ->assertSee('Objetivos Específicos (100 a 250 palabras)')
            ->assertSee('Redacte el objetivo principal que orienta el proyecto educativo de la institución...')
            ->assertSee('Desglose los objetivos puntuales derivados del objetivo general...')
            ->assertSee('Guardar')
            ->assertDontSee('Contenido por definir')
            ->assertDontSee('Misión (50 a 150 palabras)')
            ->assertDontSee('Paso 1 de 3');
    }

    public function test_abre_con_lo_guardado(): void
    {
        $nivel = $this->nivel();
        app(GuardarObjetivosProyecto::class)->ejecutar($nivel->id, new DatosObjetivosProyecto(objetivo_general: 'Mi objetivo'));

        $this->componente($nivel)->assertSet('objetivo_general', 'Mi objetivo')->assertSet('objetivos_especificos', '');
    }

    public function test_guardar_borrador_parcial_avisa_que_faltan_campos(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set('objetivo_general', $this->palabras(10))->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'info', titulo: 'Borrador guardado');

        $this->assertSame($this->palabras(10), DB::table('proyecto_educativo_objetivos')->value('objetivo_general'));
    }

    public function test_guardar_completo_avisa_que_la_seccion_esta_completa(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set(['objetivo_general' => $this->palabras(100), 'objetivos_especificos' => $this->palabras(250)])->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa');

        $this->assertSame(1, DB::table('proyecto_educativo_objetivos')->where('escuela_nivel_id', $nivel->id)->count());
    }

    public function test_pasarse_del_maximo_marca_el_campo_y_no_guarda(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set(['objetivo_general' => $this->palabras(101), 'objetivos_especificos' => $this->palabras(251)])->call('guardar')
            ->assertHasErrors(['objetivo_general', 'objetivos_especificos'])
            ->assertDispatched('toast', tipo: 'error');

        $this->assertSame(0, DB::table('proyecto_educativo_objetivos')->count());
    }

    public function test_tramite_enviado_no_guarda(): void
    {
        $nivel = $this->nivel();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->componente($nivel)->set('objetivo_general', $this->palabras(60))->call('guardar')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'El trámite ya se envió a revisión y no puede modificarse.');

        $this->assertSame(0, DB::table('proyecto_educativo_objetivos')->count());
    }

    public function test_las_otras_secciones_siguen_con_el_marcador(): void
    {
        $nivel = $this->nivel();
        $slugs = array_keys(SeccionesProyectoEducativo::todas());
        $this->assertCount(17, array_slice($slugs, 3));

        foreach ([$slugs[3], $slugs[19]] as $slug) {
            $this->get($this->ruta($nivel, $slug))->assertOk()->assertSee('Contenido por definir')->assertDontSee('Objetivo General (50 a 100 palabras)');
        }
    }

    public function test_otro_solicitante_recibe_403_y_primaria_404(): void
    {
        $nivel = $this->nivel();
        $this->actingAs(Solicitante::factory()->create()->user)->get($this->ruta($nivel))->assertForbidden();

        $primaria = $this->nivelListoPara('primaria', 'matricula');
        $this->actingAs($primaria->escuela->solicitante->user)->get($this->ruta($primaria))->assertNotFound();
    }

    public function test_indice_marca_completa_solo_la_seccion_tres_cuando_lo_esta(): void
    {
        $nivel = $this->nivel();
        $indice = route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $nivel->id]);
        $guardar = app(GuardarObjetivosProyecto::class);

        $this->assertSame(20, substr_count($this->get($indice)->assertOk()->getContent(), 'Pendiente'));

        $guardar->ejecutar($nivel->id, new DatosObjetivosProyecto(objetivo_general: $this->palabras(60)));
        $this->assertSame(20, substr_count($this->get($indice)->getContent(), 'Pendiente'));

        $guardar->ejecutar($nivel->id, new DatosObjetivosProyecto($this->palabras(50), $this->palabras(100)));
        $html = $this->get($indice)->getContent();
        $this->assertSame(19, substr_count($html, 'Pendiente'));
        $this->assertSame(1, substr_count($html, 'Completa'));
    }
}
