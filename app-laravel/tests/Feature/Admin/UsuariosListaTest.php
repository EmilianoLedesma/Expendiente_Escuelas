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

        $this->lista()->callAction(TestAction::make('purgar')->table($u), ['motivo' => 'Baja', 'confirmacion' => 'PURGAR']);

        $this->assertNull(User::find($u->id));
    }

    public function test_confirmacion_incorrecta_notifica_y_conserva_al_usuario(): void
    {
        $u = $this->archivada();

        $this->lista()->callAction(TestAction::make('purgar')->table($u), ['motivo' => 'Baja', 'confirmacion' => 'purgar'])
            ->assertNotified(Notification::make()->danger()->title('Escribe PURGAR para confirmar la purga.'));

        $this->assertNotNull(User::find($u->id));
    }

    public function test_una_precondicion_incumplida_es_notificacion_no_500(): void
    {
        $this->lista()->callAction(TestAction::make('archivar')->table($this->admin), ['motivo' => 'x'])
            ->assertNotified(Notification::make()->danger()->title('No puedes archivar tu propia cuenta.'));

        $this->assertNull($this->admin->refresh()->archivado_at);
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
