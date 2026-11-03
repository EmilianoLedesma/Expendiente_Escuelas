<?php

namespace Tests\Feature\Livewire;

use App\Application\Revision\RolesSedeq;
use App\Livewire\Cuenta\Perfil;
use App\Models\Escuela;
use App\Models\ResponsableNivel;
use App\Models\Revisor;
use App\Models\Solicitante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\TestCase;

class PerfilTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use RefreshDatabase;

    private function solicitante(): User
    {
        return Solicitante::factory()->create(['user_id' => User::factory()->create(['password' => 'secreto-viejo'])->id])->user;
    }

    private function delegado(): User
    {
        $usuario = User::factory()->create(['password' => 'secreto-viejo']);
        $usuario->assignRole(Role::findOrCreate('responsable_nivel'));

        return $usuario;
    }

    private function admin(): User
    {
        $usuario = User::factory()->create(['password' => 'secreto-viejo']);
        $usuario->assignRole(Role::findOrCreate('admin'));

        return $usuario;
    }

    public function test_un_invitado_va_al_login(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_el_solicitante_ve_el_menu_con_responsables_por_nivel(): void
    {
        $this->actingAs($this->solicitante())->get('/profile')
            ->assertOk()
            ->assertSee('Mi perfil')
            ->assertSee(route('tramite.responsables'), false)
            ->assertSee('Mis trámites')
            ->assertSee('Archivar cuenta');
    }

    public function test_el_responsable_por_nivel_no_ve_responsables_por_nivel(): void
    {
        $this->actingAs($this->delegado())->get('/profile')
            ->assertOk()
            ->assertSee('Mis niveles')
            ->assertDontSee(route('tramite.responsables'), false)
            ->assertSee('Archivar cuenta');
    }

    public function test_el_admin_ve_el_panel_y_no_puede_archivar_su_cuenta(): void
    {
        $this->actingAs($this->admin())->get('/profile')
            ->assertOk()
            ->assertSee('Panel de administración')
            ->assertDontSee('Archivar cuenta');
    }

    /** @return array<string, array{string}> */
    public static function rolesSedeq(): array
    {
        return array_combine(RolesSedeq::TODOS, array_map(fn ($r) => [$r], RolesSedeq::TODOS));
    }

    #[DataProvider('rolesSedeq')]
    public function test_los_roles_de_sedeq_no_pueden_archivar_su_cuenta(string $rol): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(Role::findOrCreate($rol));

        $this->actingAs($usuario);
        Livewire::test(Perfil::class)
            ->assertDontSee('Archivar cuenta')
            ->call('archivarCuenta')
            ->assertHasErrors('cuenta');

        $this->assertNull($usuario->fresh()->archivado_at);
    }

    public function test_un_revisor_revocado_no_puede_archivar_su_cuenta(): void
    {
        $usuario = User::factory()->create();
        Revisor::create(['user_id' => $usuario->id, 'revocado_at' => now()]);

        $this->actingAs($usuario);
        Livewire::test(Perfil::class)
            ->assertDontSee('Archivar cuenta')
            ->call('archivarCuenta')
            ->assertHasErrors('cuenta');

        $this->assertNull($usuario->fresh()->archivado_at);
    }

    public function test_archivar_cuenta_exige_mantener_presionado_y_no_se_envia_con_enter(): void
    {
        $this->actingAs($this->solicitante())->get('/profile')
            ->assertSee('Mantén presionado para archivar')
            ->assertDontSee('Sí, archivar mi cuenta')
            ->assertDontSee('<dialog', false)
            ->assertDontSee('Contraseña para confirmar')
            ->assertDontSee('wire:submit="archivarCuenta"', false);
    }

    public function test_tras_mantener_presionado_se_ofrece_deshacer_y_la_cuenta_se_archiva_al_expirar(): void
    {
        $this->actingAs($this->solicitante())->get('/profile')
            ->assertSee('Deshacer')
            ->assertSee('Se archivará tu cuenta')
            ->assertSee("etapa = 'deshacer'")
            ->assertSee('$wire.archivarCuenta()', false);
    }

    public function test_muestra_el_correo_sin_permitir_editarlo(): void
    {
        $usuario = $this->solicitante();

        Livewire::actingAs($usuario)->test(Perfil::class)
            ->assertSee($usuario->email)
            ->assertSet('nombre', $usuario->name);
    }

    public function test_guarda_el_nombre_y_avisa(): void
    {
        $usuario = $this->solicitante();

        Livewire::actingAs($usuario)->test(Perfil::class)
            ->set('nombre', 'María Pérez')
            ->call('guardarNombre')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertDispatched('profile-updated', name: 'María Pérez');

        $this->assertSame('María Pérez', $usuario->fresh()->name);
    }

    public function test_el_nombre_es_obligatorio(): void
    {
        $usuario = $this->solicitante();

        Livewire::actingAs($usuario)->test(Perfil::class)
            ->set('nombre', '')
            ->call('guardarNombre')
            ->assertHasErrors(['nombre' => 'required'])
            ->assertNotDispatched('toast');
    }

    public function test_cambia_la_contrasena(): void
    {
        $usuario = $this->solicitante();

        Livewire::actingAs($usuario)->test(Perfil::class)
            ->set('contrasenaActual', 'secreto-viejo')
            ->set('contrasenaNueva', 'otra-clave-larga-2026')
            ->set('contrasenaNueva_confirmation', 'otra-clave-larga-2026')
            ->assertSeeHtml('wire:ignore')
            ->assertSeeHtml('cambiarContrasena')
            ->call('cambiarContrasena')
            ->assertReturned(true)
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', titulo: 'Contraseña actualizada')
            ->assertSet('contrasenaActual', '');

        $this->assertTrue(password_verify('otra-clave-larga-2026', $usuario->fresh()->password));
    }

    public function test_rechaza_la_contrasena_actual_incorrecta(): void
    {
        $usuario = $this->solicitante();

        Livewire::actingAs($usuario)->test(Perfil::class)
            ->set('contrasenaActual', 'equivocada')
            ->set('contrasenaNueva', 'otra-clave-larga-2026')
            ->set('contrasenaNueva_confirmation', 'otra-clave-larga-2026')
            ->call('cambiarContrasena')
            ->assertHasErrors(['contrasenaActual'])
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $this->assertTrue(password_verify('secreto-viejo', $usuario->fresh()->password));
    }

    public function test_rechaza_una_confirmacion_distinta(): void
    {
        Livewire::actingAs($this->solicitante())->test(Perfil::class)
            ->set('contrasenaActual', 'secreto-viejo')
            ->set('contrasenaNueva', 'otra-clave-larga-2026')
            ->set('contrasenaNueva_confirmation', 'distinta')
            ->call('cambiarContrasena')
            ->assertHasErrors(['contrasenaNueva' => 'confirmed'])
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');
    }

    public function test_archiva_la_cuenta_cierra_sesion_y_avisa_en_el_login(): void
    {
        $usuario = $this->solicitante();

        Livewire::actingAs($usuario)->test(Perfil::class)
            ->call('archivarCuenta')
            ->assertRedirect(route('login'));

        $this->assertNotNull($usuario->fresh()->archivado_at, 'se archiva, no se borra');
        $this->assertGuest();
        $this->get(route('login'))->assertSee('Tu cuenta se archivó. Para recuperarla, contacta a SEDEQ.');
    }

    public function test_no_archiva_si_ya_envio_un_tramite(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);
        $usuario = Solicitante::findOrFail($escuela->solicitante_id)->user;
        $usuario->update(['password' => 'secreto-viejo']);

        Livewire::actingAs($usuario)->test(Perfil::class)
            ->call('archivarCuenta')
            ->assertHasErrors(['cuenta'])
            ->assertDispatched('toast');

        $this->assertNull($usuario->fresh()->archivado_at);
        $this->assertNotNull(Escuela::find($escuela->id));
    }

    public function test_el_responsable_por_nivel_archiva_su_cuenta(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $delegado = $this->delegado();
        ResponsableNivel::create(['user_id' => $delegado->id, 'escuela_nivel_id' => $nivel->id, 'invitado_por_solicitante_id' => $escuela->solicitante_id]);

        Livewire::actingAs($delegado)->test(Perfil::class)
            ->call('archivarCuenta')
            ->assertRedirect(route('login'));

        $this->assertNotNull($delegado->fresh()->archivado_at);
        $this->assertNotNull(Escuela::find($escuela->id), 'el trámite del solicitante no se toca');
    }

    public function test_el_nombre_del_encabezado_enlaza_al_perfil_para_todos(): void
    {
        foreach ([$this->solicitante(), $this->delegado(), $this->admin()] as $usuario) {
            Auth::logout();
            $this->actingAs($usuario)->get('/profile')->assertSee('href="'.route('profile').'"', false);
        }
    }
}
