<?php

namespace Tests\Feature\Admin;

use App\Application\Archivo\ArchivarCuenta;
use App\Application\Revision\RolesSedeq;
use App\Filament\Resources\Usuarios\Pages\CrearUsuario;
use App\Filament\Resources\Usuarios\Pages\ListUsuarios;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Livewire\Livewire;
use Tests\TestCase;

class UsuariosRolesTest extends TestCase
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

    private function cambiar(User $u, ?string $rol)
    {
        return $this->lista()->callAction(TestAction::make('cambiar_rol')->table($u), ['rol' => $rol ?? 'ninguno']);
    }

    public function test_cambia_el_rol_y_la_fila_lo_muestra(): void
    {
        $u = User::factory()->create()->assignRole(RolesSedeq::SUPERVISOR);

        $this->cambiar($u, RolesSedeq::REVISOR)->assertNotified();

        $this->assertTrue($u->refresh()->hasRole(RolesSedeq::REVISOR));
        $this->assertFalse($u->hasRole(RolesSedeq::SUPERVISOR));
        $this->lista()->assertTableColumnStateSet('roles.name', json_encode([RolesSedeq::REVISOR]), $u);
    }

    public function test_sin_rol_de_sedeq_quita_el_rol(): void
    {
        $u = User::factory()->create()->assignRole(RolesSedeq::SUPERVISOR);

        $this->cambiar($u, null)->assertNotified();

        $this->assertSame([], $u->refresh()->getRoleNames()->all());
    }

    public function test_un_rechazo_de_la_accion_es_notificacion_peligro(): void
    {
        $u = User::factory()->create()->assignRole(RolesSedeq::SUPERVISOR);

        $this->cambiar($u, RolesSedeq::SUPERVISOR)
            ->assertNotified(Notification::make()->danger()->title('La cuenta ya tiene ese rol.'));
        $this->assertTrue($u->refresh()->hasRole(RolesSedeq::SUPERVISOR));
    }

    public function test_a_un_solicitante_no_se_le_da_rol(): void
    {
        $u = User::factory()->create();
        Solicitante::factory()->create(['user_id' => $u->id]);

        $this->cambiar($u, RolesSedeq::ADMIN)
            ->assertNotified(Notification::make()->danger()->title('Una cuenta de solicitante no puede tener rol de SEDEQ.'));
        $this->assertSame([], $u->refresh()->getRoleNames()->all());
    }

    public function test_cambiar_rol_oculto_en_fila_propia_y_en_archivadas(): void
    {
        $u = User::factory()->create()->assignRole(RolesSedeq::SUPERVISOR);
        $arch = User::factory()->create()->assignRole(RolesSedeq::SUPERVISOR);
        app(ArchivarCuenta::class)->ejecutar($arch->id, $this->admin->id, 'Baja');

        $this->lista()
            ->assertActionHidden(TestAction::make('cambiar_rol')->table($this->admin))
            ->assertActionHidden(TestAction::make('cambiar_rol')->table($arch->refresh()))
            ->assertActionVisible(TestAction::make('cambiar_rol')->table($u));
    }

    public function test_crea_cuenta_y_redirige_a_la_lista(): void
    {
        NotificationFacade::fake();

        Livewire::test(CrearUsuario::class)
            ->fillForm(['nombre' => 'Ana Pérez', 'correo' => 'Ana@Sedeq.test', 'rol' => RolesSedeq::SUPERVISOR])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin/usuarios');

        $nueva = User::where('email', 'ana@sedeq.test')->first();
        $this->assertNotNull($nueva);
        $this->assertTrue($nueva->hasRole(RolesSedeq::SUPERVISOR));
    }

    public function test_correo_duplicado_es_error_en_el_campo(): void
    {
        $existente = User::factory()->create();

        Livewire::test(CrearUsuario::class)
            ->fillForm(['nombre' => 'Otra', 'correo' => $existente->email, 'rol' => RolesSedeq::ADMIN])
            ->call('create')
            ->assertHasFormErrors(['correo']);

        $this->assertSame(1, User::where('email', $existente->email)->count());
    }

    public function test_correo_invalido_es_error_en_el_campo(): void
    {
        Livewire::test(CrearUsuario::class)
            ->fillForm(['nombre' => 'Otra', 'correo' => 'no-es-correo', 'rol' => RolesSedeq::ADMIN])
            ->call('create')
            ->assertHasFormErrors(['correo']);
    }

    public function test_el_rol_revisor_no_se_ofrece_al_crear(): void
    {
        Livewire::test(CrearUsuario::class)
            ->fillForm(['nombre' => 'X', 'correo' => 'x@sedeq.test', 'rol' => RolesSedeq::REVISOR])
            ->call('create')
            ->assertHasFormErrors(['rol']);

        $this->assertNull(User::where('email', 'x@sedeq.test')->first());
    }

    public function test_la_lista_ofrece_el_boton_de_crear(): void
    {
        $this->lista()->assertActionVisible('create')->assertSee('Crear cuenta de SEDEQ');
    }

    public function test_supervisor_recibe_403_en_crear(): void
    {
        $this->actingAs(User::factory()->create()->assignRole(RolesSedeq::SUPERVISOR))
            ->get('/admin/usuarios/create')->assertForbidden();
    }

    public function test_admin_ve_la_pagina_de_crear(): void
    {
        $this->get('/admin/usuarios/create')->assertOk()->assertSee('Crear cuenta de SEDEQ');
    }
}
