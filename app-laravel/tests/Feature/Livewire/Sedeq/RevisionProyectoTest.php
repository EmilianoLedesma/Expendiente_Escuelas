<?php

namespace Tests\Feature\Livewire\Sedeq;

use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Revision\DecidirSeccionProyecto;
use App\Application\Revision\RolesSedeq;
use App\Livewire\Sedeq\ColaProyecto;
use App\Livewire\Sedeq\RevisionProyecto;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

/** Pantallas del evaluador del proyecto educativo: su cola y la revisión sección por sección (el supervisor solo lee). */
class RevisionProyectoTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private User $evaluador;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolesSeeder)->run();
        $this->evaluador = User::factory()->create()->assignRole(RolesSedeq::EVALUADOR_PROYECTO);
    }

    private function media(string $estado = 'en_revision'): EscuelaNivel
    {
        return $this->nivelDe(Solicitante::factory()->create(), 'media_superior', $estado);
    }

    private function ruta(EscuelaNivel $nivel): string
    {
        return route('sedeq.proyecto-revision', ['escuelaNivel' => $nivel->id]);
    }

    private function revision(EscuelaNivel $nivel, ?User $actor = null)
    {
        return Livewire::actingAs($actor ?? $this->evaluador)->test(RevisionProyecto::class, ['escuelaNivel' => $nivel->id]);
    }

    // --- Cola

    public function test_la_cola_lista_los_proyectos_en_revision_con_su_avance_y_enlace(): void
    {
        $nivel = $this->media();
        $this->media('en_captura');
        app(DecidirSeccionProyecto::class)->ejecutar($this->evaluador->id, $nivel->id, 'pe_01', 'aceptado');

        $this->actingAs($this->evaluador)->get('/sedeq/proyecto')->assertOk()
            ->assertSee('1 de 20')
            ->assertSee($this->ruta($nivel), false);
    }

    public function test_la_cola_vacia_lo_dice(): void
    {
        Livewire::actingAs($this->evaluador)->test(ColaProyecto::class)->assertSee('Todavía no hay proyectos educativos por revisar');
    }

    // --- Revisión

    public function test_muestra_las_veinte_secciones_y_abre_la_primera_pendiente(): void
    {
        $nivel = $this->media();
        app(DecidirSeccionProyecto::class)->ejecutar($this->evaluador->id, $nivel->id, 'pe_01', 'aceptado');

        $c = $this->revision($nivel);

        foreach (SeccionesProyectoEducativo::todas() as $nombre) {
            $c->assertSee($nombre);
        }
        $c->assertSet('seccionAbierta', SeccionesProyectoEducativo::filosofia())->assertSee('Avance: 1 de 20');
    }

    public function test_abrir_una_seccion_muestra_su_contenido(): void
    {
        $nivel = $this->media();
        DB::table('proyecto_educativo_objetivos')->insert(['escuela_nivel_id' => $nivel->id, 'objetivo_general' => 'Formar bachilleres críticos.', 'objetivos_especificos' => 'Uno.']);

        $this->revision($nivel)->call('abrir', SeccionesProyectoEducativo::objetivos())
            ->assertSet('seccionAbierta', SeccionesProyectoEducativo::objetivos())
            ->assertSee('Formar bachilleres críticos.');
    }

    public function test_el_texto_largo_se_lee_en_bloque_con_saltos_y_escapado(): void
    {
        $nivel = $this->media();
        DB::table('proyecto_educativo_objetivos')->insert(['escuela_nivel_id' => $nivel->id, 'objetivo_general' => 'Uno. Dos <script>alert(1)</script>', 'objetivos_especificos' => 'Tres.']);

        $this->revision($nivel)->call('abrir', SeccionesProyectoEducativo::objetivos())
            ->assertSeeHtml('whitespace-pre-line')
            ->assertSeeHtml('max-w-prose')
            ->assertDontSeeHtml('<script>alert(1)</script>')
            ->assertSee('Dos');
    }

    public function test_abrir_una_clave_ajena_se_ignora(): void
    {
        $nivel = $this->media();

        $this->revision($nivel)->call('abrir', 'no-existe')->assertSet('seccionAbierta', SeccionesProyectoEducativo::presentacion());
    }

    public function test_aceptar_registra_la_decision_y_avisa(): void
    {
        $nivel = $this->media();

        $this->revision($nivel)->call('aceptar')
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección aceptada');

        $this->assertSame('aceptado', DB::table('decisiones_revision')->where('escuela_nivel_id', $nivel->id)->where('paso_clave', 'pe_01')->value('estado'));
    }

    public function test_rechazar_sin_motivo_marca_el_error_y_no_escribe(): void
    {
        $nivel = $this->media();

        $this->revision($nivel)->call('rechazar')
            ->assertHasErrors(['motivo'])
            ->assertDispatched('toast', tipo: 'error');

        $this->assertSame(0, DB::table('decisiones_revision')->count());
    }

    public function test_rechazar_con_motivo_lo_guarda_y_limpia_el_campo(): void
    {
        $nivel = $this->media();

        $this->revision($nivel)->set('motivo', 'Falta la filosofía institucional.')->call('rechazar')
            ->assertHasNoErrors()
            ->assertSet('motivo', '')
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección rechazada');

        $fila = DB::table('decisiones_revision')->where('escuela_nivel_id', $nivel->id)->first();
        $this->assertSame('rechazado', $fila->estado);
        $this->assertSame('Falta la filosofía institucional.', $fila->motivo);
    }

    public function test_el_nivel_con_el_solicitante_no_ofrece_decidir(): void
    {
        $nivel = $this->media('con_observaciones');

        $this->revision($nivel)->assertDontSee('Aceptar sección')->assertSee('podrás decidir cuando lo reenvíe');
    }

    public function test_el_supervisor_lee_sin_botones_y_no_puede_decidir(): void
    {
        $nivel = $this->media();
        $supervisor = $this->supervisor();

        $c = $this->revision($nivel, $supervisor)->assertSee(SeccionesProyectoEducativo::nombre(SeccionesProyectoEducativo::presentacion()))
            ->assertDontSee('Aceptar sección')->assertDontSee('Rechazar sección');
        $c->call('aceptar')->assertDispatched('toast', tipo: 'error');

        $this->assertSame(0, DB::table('decisiones_revision')->count());
    }

    public function test_el_revisor_y_el_solicitante_reciben_403_en_la_ruta(): void
    {
        $nivel = $this->media();

        $this->actingAs($this->revisor())->get($this->ruta($nivel))->assertForbidden();
        $this->actingAs($nivel->escuela->solicitante->user)->get($this->ruta($nivel))->assertForbidden();
        $this->actingAs($this->evaluador)->get($this->ruta($nivel))->assertOk();
        $this->actingAs($this->supervisor())->get($this->ruta($nivel))->assertOk();
    }

    public function test_un_nivel_en_captura_o_de_otro_nivel_da_403(): void
    {
        $this->actingAs($this->evaluador)->get($this->ruta($this->media('en_captura')))->assertForbidden();
        $this->actingAs($this->evaluador)->get($this->ruta($this->nivelEnRevision()))->assertForbidden();
    }
}
