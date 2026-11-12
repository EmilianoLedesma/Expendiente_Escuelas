<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\DTO\DatosPerfilDocenteProyecto;
use App\Application\ProyectoEducativo\GuardarPerfilDocenteProyecto;
use App\Livewire\Tramite\Paso3\ProyectoEducativoSeccion;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3PerfilDocenteProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private const SLUG = 'perfil-docente';

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

    public function test_muestra_el_campo_con_la_etiqueta_y_el_placeholder_del_dueno(): void
    {
        $html = $this->get($this->ruta($this->nivel()))->assertOk()
            ->assertSee('Habilidades y Virtudes Docentes (200 a 400 palabras)')
            ->assertSee('Describa las habilidades profesionales, pedagógicas y personales que deben poseer los docentes de la institución...')
            ->assertSee('Guardar')
            ->assertDontSee('Contenido por definir')
            ->getContent();

        $this->assertSame(1, substr_count($html, '<textarea'));
    }

    public function test_abre_con_lo_guardado(): void
    {
        $nivel = $this->nivel();
        app(GuardarPerfilDocenteProyecto::class)->ejecutar($nivel->id, new DatosPerfilDocenteProyecto($this->palabras(250)));

        $this->componente($nivel)->assertSet('habilidades_virtudes', $this->palabras(250));
    }

    public function test_vacio_marca_el_error_y_no_escribe(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->call('guardar')
            ->assertHasErrors(['habilidades_virtudes'])
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $this->assertSame(0, DB::table('proyecto_educativo_perfil_docente')->count());
    }

    public function test_los_campos_llevan_las_restricciones_del_lado_del_cliente(): void
    {
        $html = $this->componente($this->nivel())->html();

        $this->assertStringContainsString('$wire.entangle(\'habilidades_virtudes\')', $html);
        $this->assertStringContainsString('x-on:input="recortar()"', $html);
        $this->assertStringContainsString('max: 400', $html);
        $this->assertStringContainsString('min: 200', $html);
    }

    public function test_guardar_completo_avisa_que_la_seccion_esta_completa(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set('habilidades_virtudes', $this->palabras(400))->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa');

        $this->assertSame(1, DB::table('proyecto_educativo_perfil_docente')->where('escuela_nivel_id', $nivel->id)->count());
    }

    public function test_pasarse_del_maximo_marca_el_campo_y_no_guarda(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set('habilidades_virtudes', $this->palabras(401))->call('guardar')
            ->assertHasErrors(['habilidades_virtudes']);

        $this->assertSame(0, DB::table('proyecto_educativo_perfil_docente')->count());
    }

    public function test_tramite_enviado_no_guarda(): void
    {
        $nivel = $this->nivel();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->componente($nivel)->set('habilidades_virtudes', $this->palabras(200))->call('guardar')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'El trámite ya se envió a revisión y no puede modificarse.');

        $this->assertSame(0, DB::table('proyecto_educativo_perfil_docente')->count());
    }

    public function test_otro_solicitante_recibe_403_y_primaria_404(): void
    {
        $nivel = $this->nivel();
        $this->actingAs(Solicitante::factory()->create()->user)->get($this->ruta($nivel))->assertForbidden();

        $primaria = $this->nivelListoPara('primaria', 'matricula');
        $this->actingAs($primaria->escuela->solicitante->user)->get(route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $primaria->id, 'seccion' => self::SLUG]))->assertNotFound();
    }

    public function test_indice_marca_completa_la_seccion_diez_cuando_lo_esta(): void
    {
        $nivel = $this->nivel();
        $indice = route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $nivel->id]);

        $this->assertSame(20, substr_count($this->get($indice)->assertOk()->getContent(), 'Pendiente'));

        $this->componente($nivel)->set('habilidades_virtudes', $this->palabras(200))->call('guardar');
        $html = $this->get($indice)->getContent();
        $this->assertSame(19, substr_count($html, 'Pendiente'));
        $this->assertSame(1, substr_count($html, 'Completa'));
    }
}
