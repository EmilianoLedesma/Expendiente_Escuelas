<?php

namespace Tests\Feature\Admin;

use App\Application\Archivo\ArchivarCuenta;
use App\Application\Archivo\ArchivarTramite;
use App\Application\Revision\RolesSedeq;
use App\Filament\Resources\TramitesArchivados\Pages\ListTramitesArchivados;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Scopes\EscuelaNoArchivada;
use App\Models\Solicitante;
use App\Models\TernaNombre;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TramitesArchivadosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolesSeeder)->run();
        $this->admin = User::factory()->create()->assignRole(RolesSedeq::ADMIN);
        $this->actingAs($this->admin);
    }

    private function lista()
    {
        return Livewire::test(ListTramitesArchivados::class);
    }

    private function escuela(?User $u = null): Escuela
    {
        $u ??= User::factory()->create();
        $s = Solicitante::factory()->create(['user_id' => $u->id]);
        $p = Plantel::create(['calle' => 'Hidalgo', 'numero_ext' => '12', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $p->id, 'solicitante_id' => $s->id, 'nombre_aprobado' => 'Colegio Prueba']);
    }

    private function sinScope(int $id): ?Escuela
    {
        return Escuela::withoutGlobalScope(EscuelaNoArchivada::class)->find($id);
    }

    private function archivada(string $motivo = 'Duplicado'): Escuela
    {
        $e = $this->escuela();
        app(ArchivarTramite::class)->ejecutar($e->id, $this->admin->id, $motivo);

        return $this->sinScope($e->id);
    }

    public function test_lista_todos_por_defecto_y_muestra_motivo(): void
    {
        $activa = $this->escuela();
        $arch = $this->archivada('Motivo visible');

        $this->lista()->assertCanSeeTableRecords([$arch, $activa])
            ->assertSee('Motivo visible')
            ->assertSee('Colegio Prueba')
            ->assertSee($this->admin->name);
    }

    public function test_sin_nombre_aprobado_muestra_el_primer_nombre_de_la_terna(): void
    {
        $e = $this->escuela();
        $e->update(['nombre_aprobado' => null]);
        TernaNombre::create(['escuela_id' => $e->id, 'numero_propuesta' => 2, 'nombre_propuesto' => 'Colegio Segundo']);
        TernaNombre::create(['escuela_id' => $e->id, 'numero_propuesta' => 1, 'nombre_propuesto' => 'Colegio Primero']);

        $this->lista()->assertSee('Colegio Primero')->assertDontSee('Colegio Segundo')->assertDontSee('Sin nombre aprobado');
    }

    public function test_filtro_estado_separa_archivados_y_activos(): void
    {
        $activa = $this->escuela();
        $arch = $this->archivada();

        $this->lista()->filterTable('archivado', true)
            ->assertCanSeeTableRecords([$arch])->assertCanNotSeeTableRecords([$activa]);
        $this->lista()->filterTable('archivado', false)
            ->assertCanSeeTableRecords([$activa])->assertCanNotSeeTableRecords([$arch]);
    }

    public function test_acciones_visibles_segun_estado(): void
    {
        $activa = $this->escuela();
        $arch = $this->archivada();

        $this->lista()
            ->assertActionVisible(TestAction::make('archivar')->table($activa))
            ->assertActionHidden(TestAction::make('restaurar')->table($activa))
            ->assertActionHidden(TestAction::make('purgar')->table($activa))
            ->assertActionHidden(TestAction::make('archivar')->table($arch))
            ->assertActionVisible(TestAction::make('restaurar')->table($arch))
            ->assertActionVisible(TestAction::make('purgar')->table($arch));
    }

    public function test_archivar_activo_con_motivo(): void
    {
        $e = $this->escuela();

        $this->lista()->callAction(TestAction::make('archivar')->table($e), ['motivo' => 'Duplicado']);

        $this->assertNotNull($this->sinScope($e->id)->archivado_at);
        $this->assertNull(Escuela::find($e->id));
    }

    public function test_archivar_sin_motivo_funciona(): void
    {
        $e = $this->escuela();

        $this->lista()->callAction(TestAction::make('archivar')->table($e), ['motivo' => null])
            ->assertHasNoActionErrors();

        $this->assertNull(Escuela::find($e->id));
        $this->assertNull($this->sinScope($e->id)->archivado_motivo);
    }

    public function test_restaurar_devuelve_la_escuela_y_sale_de_archivados(): void
    {
        $e = $this->archivada();

        $this->lista()->callAction(TestAction::make('restaurar')->table($e))
            ->filterTable('archivado', true)->assertCanNotSeeTableRecords([$e]);

        $this->assertNotNull(Escuela::find($e->id));
    }

    public function test_restaurar_refusado_notifica(): void
    {
        $u = User::factory()->create();
        $e = $this->escuela($u);
        app(ArchivarCuenta::class)->ejecutar($u->id, $this->admin->id, 'Baja');
        $e = $this->sinScope($e->id);

        $this->lista()->callAction(TestAction::make('restaurar')->table($e))
            ->assertNotified(Notification::make()->danger()->title('La cuenta del solicitante está archivada: restáurala primero.'));

        $this->assertNull(Escuela::find($e->id));
    }

    public function test_purgar_con_confirmacion_y_motivo(): void
    {
        $e = $this->archivada();

        $this->lista()->callAction(TestAction::make('purgar')->table($e), ['motivo' => 'Baja', 'confirmacion' => 'ELIMINAR']);

        $this->assertNull($this->sinScope($e->id));
    }

    public function test_confirmacion_incorrecta_notifica_y_conserva(): void
    {
        $e = $this->archivada();

        $this->lista()->callAction(TestAction::make('purgar')->table($e), ['motivo' => 'Baja', 'confirmacion' => 'purgar'])
            ->assertNotified(Notification::make()->danger()->title('Escribe ELIMINAR para confirmar la eliminación.'));

        $this->assertNotNull($this->sinScope($e->id));
    }

    public function test_el_modal_de_purga_advierte_que_no_queda_dato_personal(): void
    {
        $e = $this->archivada();

        $this->lista()->mountAction(TestAction::make('purgar')->table($e))
            ->assertMountedActionModalSee('Borrado definitivo e irreversible')
            ->assertMountedActionModalSee('el motivo se conserva en la bitácora')
            ->assertMountedActionModalSee('No escribas datos personales (nombres, correos, CURP) en el motivo')
            ->assertMountedActionModalSee('identificadores y el motivo');
    }

    public function test_purga_refusada_por_la_accion_notifica(): void
    {
        $e = $this->archivada();
        // Admin degradado a mitad de sesión: la acción de Application lo rechaza.
        $this->admin->removeRole(RolesSedeq::ADMIN);

        $this->lista()->callAction(TestAction::make('purgar')->table($e), ['motivo' => 'Baja', 'confirmacion' => 'ELIMINAR'])
            ->assertNotified(Notification::make()->danger()->title('No tienes permiso para esta acción.'));

        $this->assertNotNull($this->sinScope($e->id));
    }

    public function test_supervisor_y_solicitante_reciben_403_y_admin_ve_el_indice(): void
    {
        $url = '/admin/tramites';
        $this->get($url)->assertOk();
        $this->actingAs(User::factory()->create()->assignRole(RolesSedeq::SUPERVISOR))->get($url)->assertForbidden();
        $sol = User::factory()->create();
        Solicitante::factory()->create(['user_id' => $sol->id]);
        $this->actingAs($sol)->get($url)->assertForbidden();
    }
}
