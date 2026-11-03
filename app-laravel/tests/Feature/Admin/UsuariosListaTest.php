<?php

namespace Tests\Feature\Admin;

use App\Application\Archivo\ArchivarCuenta;
use App\Application\Revision\RolesSedeq;
use App\Filament\Resources\Usuarios\Pages\ListUsuarios;
use App\Filament\Resources\Usuarios\UsuarioResource;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class UsuariosListaTest extends TestCase
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
        return Livewire::test(ListUsuarios::class);
    }

    private function solicitante(): User
    {
        $u = User::factory()->create();
        Solicitante::factory()->create(['user_id' => $u->id]);

        return $u;
    }

    private function plantel(string $calle): Plantel
    {
        return Plantel::create(['calle' => $calle, 'numero_ext' => '12', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
    }

    private function escuelaDe(User $u, Plantel $p): Escuela
    {
        return Escuela::create(['plantel_id' => $p->id, 'solicitante_id' => $u->solicitante->id]);
    }

    private function archivada(): User
    {
        $u = $this->solicitante();
        app(ArchivarCuenta::class)->ejecutar($u->id, $this->admin->id, 'Prueba');

        return $u->refresh();
    }

    public function test_lista_activos_y_archivados(): void
    {
        $a = $this->solicitante();
        $b = $this->archivada();

        $this->lista()->assertCanSeeTableRecords([$a, $b]);
    }

    public function test_filtra_por_estado_y_rol_y_busca_por_correo(): void
    {
        $a = $this->solicitante();
        $b = $this->archivada();
        $rev = User::factory()->create()->assignRole(RolesSedeq::REVISOR);

        $this->lista()->filterTable('estado', 'archivada')
            ->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a, $rev]);
        $this->lista()->filterTable('rol', 'revisor')
            ->assertCanSeeTableRecords([$rev])->assertCanNotSeeTableRecords([$a, $b]);
        $this->lista()->searchTable($a->email)
            ->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b, $rev]);
    }

    public function test_archivar_con_motivo_y_luego_restaurar(): void
    {
        $u = $this->solicitante();

        $this->lista()
            ->callAction(TestAction::make('archivar')->table($u), ['motivo' => 'Duplicada'])
            ->assertNotified();
        $this->assertNotNull($u->refresh()->archivado_at);

        $this->lista()
            ->assertActionVisible(TestAction::make('restaurar')->table($u))
            ->assertActionHidden(TestAction::make('archivar')->table($u))
            ->callAction(TestAction::make('restaurar')->table($u));
        $this->assertNull($u->refresh()->archivado_at);
    }

    public function test_purgar_oculto_en_fila_activa(): void
    {
        $u = $this->solicitante();

        $this->lista()->assertActionHidden(TestAction::make('purgar')->table($u));
    }

    public function test_purgar_archivada_con_confirmacion(): void
    {
        $u = $this->archivada();

        $this->lista()->callAction(TestAction::make('purgar')->table($u), ['motivo' => 'Baja', 'confirmacion' => 'ELIMINAR']);

        $this->assertNull(User::find($u->id));
    }

    public function test_confirmacion_incorrecta_notifica_y_conserva_al_usuario(): void
    {
        $u = $this->archivada();

        $this->lista()->callAction(TestAction::make('purgar')->table($u), ['motivo' => 'Baja', 'confirmacion' => 'purgar'])
            ->assertNotified(Notification::make()->danger()->title('Escribe ELIMINAR para confirmar la eliminación.'));

        $this->assertNotNull(User::find($u->id));
    }

    public function test_una_precondicion_incumplida_es_notificacion_no_500(): void
    {
        // Una cuenta de SEDEQ archivada no se puede purgar: la acción lanza PrecondicionIncumplida.
        $sup = User::factory()->create()->assignRole(RolesSedeq::SUPERVISOR);
        app(ArchivarCuenta::class)->ejecutar($sup->id, $this->admin->id, 'Baja');

        $this->lista()->callAction(TestAction::make('purgar')->table($sup), ['motivo' => 'x', 'confirmacion' => 'ELIMINAR'])
            ->assertNotified();

        $this->assertNotNull(User::find($sup->id));
    }

    public function test_archivar_oculto_en_la_fila_propia_y_restaurar_oculto_en_activa(): void
    {
        $u = $this->solicitante();

        $this->lista()
            ->assertActionHidden(TestAction::make('archivar')->table($this->admin))
            ->assertActionVisible(TestAction::make('archivar')->table($u))
            ->assertActionHidden(TestAction::make('restaurar')->table($u));
    }

    public function test_archivar_exige_motivo(): void
    {
        $u = $this->solicitante();

        $this->lista()->callAction(TestAction::make('archivar')->table($u), ['motivo' => ''])
            ->assertHasActionErrors(['motivo' => 'required']);

        $this->assertNull($u->refresh()->archivado_at);
    }

    public function test_el_modal_de_purga_advierte_que_no_queda_dato_personal(): void
    {
        $u = $this->archivada();

        $this->lista()->mountAction(TestAction::make('purgar')->table($u))
            ->assertMountedActionModalSee('Borrado definitivo e irreversible')
            ->assertMountedActionModalSee('el motivo se conserva en la bitácora')
            ->assertMountedActionModalSee('No escribas datos personales (nombres, correos, CURP) en el motivo')
            ->assertMountedActionModalSee('identificadores y el motivo');
    }

    public function test_el_modal_de_archivar_advierte_sobre_datos_personales_en_el_motivo(): void
    {
        $u = $this->solicitante();

        $this->lista()->mountAction(TestAction::make('archivar')->table($u))
            ->assertMountedActionModalSee('No escribas datos personales (nombres, correos, CURP) en el motivo')
            ->assertMountedActionModalSee('el motivo se conserva en la bitácora');
    }

    private function claveDeGrupo(string $key): array
    {
        $grupo = $this->lista()->instance()->getTable()->getGroup('plantel_principal_id');

        return $grupo->scopeQueryByKey(UsuarioResource::getEloquentQuery(), $key)->pluck('users.id')->all();
    }

    public function test_scope_de_grupo_por_clave_de_plantel_y_clave_vacia(): void
    {
        $p1 = $this->plantel('Uno');
        $p2 = $this->plantel('Dos');
        $a = $this->solicitante();
        $b = $this->solicitante();
        $this->escuelaDe($a, $p1);
        $this->escuelaDe($b, $p2);

        $this->assertSame([$a->id], $this->claveDeGrupo((string) $p1->id));
        $this->assertSame([$b->id], $this->claveDeGrupo((string) $p2->id));
        $this->assertSame([$this->admin->id], $this->claveDeGrupo(''));
    }

    public function test_staff_cae_en_el_grupo_sin_plantel_y_no_en_uno_de_plantel(): void
    {
        $p = $this->plantel('Hidalgo');
        $a = $this->solicitante();
        $this->escuelaDe($a, $p);

        $this->assertNotContains($this->admin->id, $this->claveDeGrupo((string) $p->id));
        $this->assertContains($this->admin->id, $this->claveDeGrupo(''));
        $this->assertNotContains($a->id, $this->claveDeGrupo(''));
    }

    public function test_filtro_de_plantel_encuentra_por_escuela_archivada(): void
    {
        $p = $this->plantel('Solo archivada');
        $a = $this->solicitante();
        $b = $this->solicitante();
        $this->escuelaDe($a, $p)->forceFill(['archivado_at' => now()])->save();

        $this->lista()->filterTable('plantel', $p->id)
            ->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);
    }

    public function test_agrupa_solicitantes_por_plantel_y_staff_en_sin_plantel(): void
    {
        $p = $this->plantel('Hidalgo');
        $a = $this->solicitante();
        $b = $this->solicitante();
        $this->escuelaDe($a, $p);
        $this->escuelaDe($b, $p);

        $this->lista()->assertSee('Hidalgo 12, Centro, Querétaro')->assertSee('Sin plantel');

        $q = fn (User $u) => UsuarioResource::getEloquentQuery()->find($u->id)->plantel_principal_id;
        $this->assertSame($p->id, $q($a));
        $this->assertSame($p->id, $q($b));
        $this->assertNull($q($this->admin));
    }

    public function test_multi_plantel_aparece_una_vez_bajo_el_menor_id_con_archivadas_incluidas(): void
    {
        $bajo = $this->plantel('Bajo');
        $alto = $this->plantel('Alto');
        $u = $this->solicitante();
        $this->escuelaDe($u, $alto);
        $archivada = $this->escuelaDe($u, $bajo);
        $archivada->forceFill(['archivado_at' => now()])->save();
        $this->assertNull(Escuela::find($archivada->id));

        $this->assertSame($bajo->id, UsuarioResource::getEloquentQuery()->find($u->id)->plantel_principal_id);
        $this->lista()->assertCanSeeTableRecords([$u])
            ->assertSee('Bajo 12, Centro, Querétaro')->assertSee('Alto 12, Centro, Querétaro');
        $this->assertSame(1, UsuarioResource::getEloquentQuery()->where('users.id', $u->id)->count());
    }

    public function test_el_titulo_del_grupo_no_consulta_el_plantel_por_registro(): void
    {
        foreach (['Uno', 'Dos', 'Tres'] as $calle) {
            $this->escuelaDe($this->solicitante(), $this->plantel($calle));
        }

        DB::enableQueryLog();
        $this->lista()->assertSee('Uno 12, Centro, Querétaro')->assertSee('Tres 12, Centro, Querétaro');
        $porId = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "planteles" where "planteles"."id" = ?'));

        $this->assertCount(0, $porId);
    }

    public function test_filtra_por_plantel(): void
    {
        $p = $this->plantel('Hidalgo');
        $a = $this->solicitante();
        $b = $this->solicitante();
        $this->escuelaDe($a, $p);

        $this->lista()->filterTable('plantel', $p->id)
            ->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);
    }

    public function test_supervisor_y_solicitante_reciben_403(): void
    {
        $url = '/admin/usuarios';
        $this->actingAs(User::factory()->create()->assignRole(RolesSedeq::SUPERVISOR))->get($url)->assertForbidden();
        $this->actingAs($this->solicitante())->get($url)->assertForbidden();
    }

    public function test_admin_ve_el_indice(): void
    {
        $this->get('/admin/usuarios')->assertOk();
    }
}
