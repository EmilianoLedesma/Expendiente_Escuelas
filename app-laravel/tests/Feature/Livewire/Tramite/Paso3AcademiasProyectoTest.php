<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\DTO\DatosAcademiasProyecto;
use App\Application\ProyectoEducativo\GuardarAcademiasProyecto;
use App\Livewire\Tramite\Paso3\ProyectoEducativoSeccion;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3AcademiasProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private const SLUG = 'programa-de-academias-docentes';

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

    public function test_el_primer_bloque_muestra_las_seis_opciones_del_dueno(): void
    {
        $this->get($this->ruta($this->nivel()))->assertOk()
            ->assertSee('Marque la opción que trabajará en su proyecto educativo')
            ->assertSee('Academia transversal o integral')
            ->assertSee('Academias por recurso o área')
            ->assertSee('Academias por grados')
            ->assertSee('Academias por recursos o áreas afines')
            ->assertSee('Mixta')
            ->assertSee('Otros')
            ->assertSee('Siguiente')
            ->assertDontSee('Contenido por definir')
            ->assertDontSee('Explique las razones por las cuales se conforman las academias de esta manera...');
    }

    public function test_la_descripcion_solo_aparece_con_mixta_u_otros(): void
    {
        $componente = $this->componente($this->nivel());

        $componente->assertDontSee('Describa el tipo de academia seleccionada...');
        foreach (['transversal', 'por_grados'] as $tipo) {
            $componente->set('tipo_academia', $tipo)->assertDontSee('Describa el tipo de academia seleccionada...');
        }
        foreach (['mixta', 'otros'] as $tipo) {
            $componente->set('tipo_academia', $tipo)
                ->assertSee('Describa el tipo de academia seleccionada...')
                ->assertSee('Descripción (20 a 100 palabras)');
        }
    }

    public function test_siguiente_exige_el_tipo_y_se_queda_en_el_bloque(): void
    {
        $this->componente($this->nivel())->call('siguienteAcademias')
            ->assertHasErrors(['tipo_academia'])
            ->assertSet('bloque', 1)
            ->assertDispatched('toast', tipo: 'error');
    }

    public function test_siguiente_con_mixta_exige_la_descripcion(): void
    {
        $this->componente($this->nivel())->set('tipo_academia', 'mixta')->call('siguienteAcademias')
            ->assertHasErrors(['descripcion_academia'])
            ->assertSet('bloque', 1);
    }

    public function test_siguiente_valida_solo_el_bloque_y_pasa_a_la_justificacion_sin_escribir(): void
    {
        $componente = $this->componente($this->nivel())->set('tipo_academia', 'por_grados')->call('siguienteAcademias');

        $componente->assertHasNoErrors()->assertSet('bloque', 2)
            ->assertSee('Justificación de la composición de la(s) academia(s) docente(s) (100 a 250 palabras)')
            ->assertSee('Explique las razones por las cuales se conforman las academias de esta manera...')
            ->assertSee('Guardar')
            ->assertDontSee('Marque la opción que trabajará');
        $this->assertSame(0, DB::table('proyecto_educativo_academias')->count());
    }

    public function test_anterior_vuelve_sin_validar_ni_perder_lo_tecleado(): void
    {
        $this->componente($this->nivel())
            ->set('tipo_academia', 'transversal')->call('siguienteAcademias')
            ->set('justificacion_academia', 'borrador')
            ->call('anteriorAcademias')
            ->assertSet('bloque', 1)->assertHasNoErrors()
            ->assertSet('justificacion_academia', 'borrador')->assertSet('tipo_academia', 'transversal');
    }

    public function test_guardar_completo_avisa_y_escribe(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)
            ->set(['tipo_academia' => 'mixta', 'descripcion_academia' => $this->palabras(20)])->call('siguienteAcademias')
            ->set('justificacion_academia', $this->palabras(100))->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa');

        $fila = DB::table('proyecto_educativo_academias')->where('escuela_nivel_id', $nivel->id)->first();
        $this->assertSame('mixta', $fila->tipo_academia);
        $this->assertSame($this->palabras(20), $fila->descripcion_academia);
    }

    public function test_guardar_con_error_en_el_primer_bloque_muestra_ese_bloque_y_no_escribe(): void
    {
        $this->componente($this->nivel())
            ->set('tipo_academia', 'transversal')->call('siguienteAcademias')
            ->set(['tipo_academia' => '', 'justificacion_academia' => $this->palabras(100)])->call('guardar')
            ->assertHasErrors(['tipo_academia'])
            ->assertSet('bloque', 1)
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $this->assertSame(0, DB::table('proyecto_educativo_academias')->count());
    }

    public function test_abre_con_lo_guardado(): void
    {
        $nivel = $this->nivel();
        app(GuardarAcademiasProyecto::class)->ejecutar($nivel->id, new DatosAcademiasProyecto('otros', $this->palabras(25), $this->palabras(120)));

        $this->componente($nivel)
            ->assertSet('tipo_academia', 'otros')
            ->assertSet('descripcion_academia', $this->palabras(25))
            ->assertSet('justificacion_academia', $this->palabras(120));
    }

    public function test_tramite_enviado_no_guarda(): void
    {
        $nivel = $this->nivel();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->componente($nivel)
            ->set('tipo_academia', 'transversal')->call('siguienteAcademias')
            ->set('justificacion_academia', $this->palabras(100))->call('guardar')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'El trámite ya se envió a revisión y no puede modificarse.');

        $this->assertSame(0, DB::table('proyecto_educativo_academias')->count());
    }

    public function test_el_indice_marca_completa_la_seccion_doce(): void
    {
        $nivel = $this->nivel();
        $indice = route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $nivel->id]);

        $this->assertSame(20, substr_count($this->get($indice)->assertOk()->getContent(), 'Pendiente'));

        app(GuardarAcademiasProyecto::class)->ejecutar($nivel->id, new DatosAcademiasProyecto('transversal', null, $this->palabras(100)));
        $html = $this->get($indice)->getContent();
        $this->assertSame(19, substr_count($html, 'Pendiente'));
        $this->assertSame(1, substr_count($html, 'Completa'));
    }

    public function test_otro_solicitante_recibe_403_y_primaria_404(): void
    {
        $nivel = $this->nivel();
        $this->actingAs(Solicitante::factory()->create()->user)->get($this->ruta($nivel))->assertForbidden();

        $primaria = $this->nivelListoPara('primaria', 'matricula');
        $this->actingAs($primaria->escuela->solicitante->user)->get(route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $primaria->id, 'seccion' => self::SLUG]))->assertNotFound();
    }
}
