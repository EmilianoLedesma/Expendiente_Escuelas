<?php

namespace Tests\Feature\Seguridad;

use App\Application\Auth\CerrarOtrasSesiones;
use App\Application\Escuelas\VerificarAccesoResponsable;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Revision\InvitarRevisor;
use App\Application\Revision\RolesSedeq;
use App\Filament\Pages\Reportes;
use App\Filament\Pages\SaludSistema;
use App\Filament\Resources\Bitacora\BitacoraResource;
use App\Filament\Resources\Planteles\PlantelResource;
use App\Filament\Resources\TramitesArchivados\TramiteArchivadoResource;
use App\Filament\Resources\Usuarios\UsuarioResource;
use App\Infrastructure\Geografia\ApiInfoMexico;
use App\Livewire\Cuenta\Perfil;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\Revisor;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use ReflectionClass;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\TestCase;

/** Endurecimiento de la auditoría de seguridad del 2026-11-05 (docs/reports/2026-11-05-auditoria-de-seguridad.md). */
class EndurecimientoTest extends TestCase
{
    use ConNivelParaResponsables;
    use RefreshDatabase;

    public function test_el_seeder_no_crea_la_cuenta_de_prueba_en_produccion(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        app(DatabaseSeeder::class)->run(); // `db:seed` pediría confirmación en producción

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
        $this->assertTrue(Role::where('name', RolesSedeq::ADMIN)->exists(), 'el catálogo y los roles sí se siembran');
    }

    public function test_un_id_no_numerico_en_una_ruta_de_tramite_es_404_no_500(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/tramite/paso2/abc')->assertNotFound();
        $this->get('/tramite/paso3/xyz/matricula')->assertNotFound();
        $this->get('/tramite/99999999999999999999/plantel')->assertNotFound();
    }

    public function test_el_registro_se_limita_por_ip(): void
    {
        RateLimiter::clear('registro:127.0.0.1');
        foreach (range(1, 10) as $_) {
            RateLimiter::hit('registro:127.0.0.1', 3600);
        }

        Volt::test('pages.auth.register')
            ->set('name', 'Persona Nueva')->set('email', 'nueva@example.com')
            ->set('password', 'password')->set('password_confirmation', 'password')
            ->call('register')
            ->assertHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'nueva@example.com']);
    }

    public function test_recuperar_contrasena_se_limita_por_ip(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'existe@example.com']);
        RateLimiter::clear('recuperar:127.0.0.1');
        foreach (range(1, 5) as $_) {
            RateLimiter::hit('recuperar:127.0.0.1', 60);
        }

        Volt::test('pages.auth.forgot-password')
            ->set('email', 'existe@example.com')
            ->call('sendPasswordResetLink')
            ->assertHasErrors('email');

        Notification::assertNothingSent();
    }

    private function sesion(string $id, int $userId): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'ip_address' => '10.0.0.1', 'user_agent' => 'prueba', 'payload' => '', 'last_activity' => time()]);
    }

    public function test_cerrar_otras_sesiones_conserva_la_actual_y_no_toca_a_otros_usuarios(): void
    {
        $usuario = User::factory()->create();
        $otro = User::factory()->create();
        $this->sesion('actual', $usuario->id);
        $this->sesion('vieja', $usuario->id);
        $this->sesion('ajena', $otro->id);

        app(CerrarOtrasSesiones::class)->ejecutar($usuario->id, 'actual');

        $this->assertDatabaseHas('sessions', ['id' => 'actual']);
        $this->assertDatabaseMissing('sessions', ['id' => 'vieja']);
        $this->assertDatabaseHas('sessions', ['id' => 'ajena']);

        app(CerrarOtrasSesiones::class)->ejecutar($usuario->id);
        $this->assertDatabaseMissing('sessions', ['id' => 'actual']);
    }

    public function test_cambiar_la_contrasena_cierra_las_otras_sesiones_y_rota_el_recuerdo(): void
    {
        $usuario = User::factory()->create();
        $this->sesion('en-otro-equipo', $usuario->id);
        $recuerdo = $usuario->remember_token;
        $this->actingAs($usuario);

        Livewire::test(Perfil::class)
            ->set('contrasenaActual', 'password')
            ->set('contrasenaNueva', 'NuevaClave#2026x')
            ->set('contrasenaNueva_confirmation', 'NuevaClave#2026x')
            ->call('cambiarContrasena');

        $this->assertDatabaseMissing('sessions', ['id' => 'en-otro-equipo']);
        $this->assertNotSame($recuerdo, $usuario->fresh()->remember_token);
    }

    public function test_los_modelos_publicos_de_los_componentes_del_tramite_estan_bloqueados(): void
    {
        $sinBloquear = [];
        $base = dirname(__DIR__, 3).'/app/Livewire/Tramite';
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)) as $archivo) {
            $clase = 'App\\Livewire\\Tramite\\'.str_replace(['/', '\\', '.php'], ['\\', '\\', ''], substr($archivo->getPathname(), strlen($base) + 1));
            if (! class_exists($clase)) {
                continue;
            }
            foreach ((new ReflectionClass($clase))->getProperties(\ReflectionProperty::IS_PUBLIC) as $propiedad) {
                $tipo = $propiedad->getType();
                $nombre = $tipo instanceof \ReflectionNamedType ? $tipo->getName() : '';
                if (in_array($nombre, [Escuela::class, EscuelaNivel::class], true) && $propiedad->getAttributes(Locked::class) === []) {
                    $sinBloquear[] = $clase.'::$'.$propiedad->getName();
                }
            }
        }

        $this->assertSame([], $sinBloquear, 'Escuela/EscuelaNivel públicos sin #[Locked]');
    }

    public function test_no_se_reactiva_a_un_revisor_archivado(): void
    {
        foreach (['admin', 'supervisor', 'revisor', 'responsable_nivel', 'solicitante'] as $rol) {
            Role::findOrCreate($rol, 'web');
        }
        $supervisor = User::factory()->create()->assignRole(RolesSedeq::SUPERVISOR);
        $viejo = User::factory()->create(['email' => 'viejo@example.test']);
        Revisor::create(['user_id' => $viejo->id, 'revocado_at' => now()]);
        $viejo->forceFill(['archivado_at' => now()])->save();

        try {
            app(InvitarRevisor::class)->ejecutar($supervisor->id, 'Viejo Rev', 'viejo@example.test');
            $this->fail('debió rechazar la cuenta archivada');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('correo', $e->errores);
        }
        $this->assertNotNull(Revisor::findOrFail($viejo->id)->revocado_at);
    }

    public function test_un_responsable_archivado_no_tiene_acceso(): void
    {
        $nivel = $this->nivelDe(Solicitante::factory()->create());
        $responsable = $this->responsableDe($nivel);
        $acceso = app(VerificarAccesoResponsable::class);

        $this->assertTrue($acceso->alNivel($responsable->id, $nivel->id));
        $this->assertTrue($acceso->aLaEscuela($responsable->id, $nivel->escuela_id));

        $responsable->forceFill(['archivado_at' => now()])->save();

        $this->assertFalse($acceso->alNivel($responsable->id, $nivel->id));
        $this->assertFalse($acceso->aLaEscuela($responsable->id, $nivel->escuela_id));
    }

    public function test_reportes_tolera_un_por_pagina_invalido(): void
    {
        (new RolesSeeder)->run();
        $this->actingAs(User::factory()->create()->assignRole(RolesSedeq::ADMIN));

        Livewire::test(Reportes::class)->set('porPagina', 0)->assertOk()->assertSet('porPagina', 10);
        Livewire::test(Reportes::class)->set('porPagina', -5)->assertOk()->assertSet('porPagina', 10);
        Livewire::test(Reportes::class)->set('porPagina', 25)->assertSet('porPagina', 25);
    }

    public function test_la_fuente_de_geografia_exige_https(): void
    {
        Http::fake();

        $this->expectException(RuntimeException::class);

        (new ApiInfoMexico('http://api.test'))->entidades();
    }

    public function test_los_recursos_de_filament_solo_se_abren_al_admin(): void
    {
        (new RolesSeeder)->run();
        $clases = [UsuarioResource::class, PlantelResource::class, TramiteArchivadoResource::class, BitacoraResource::class, Reportes::class, SaludSistema::class];

        $this->actingAs(User::factory()->create()->assignRole(RolesSedeq::SUPERVISOR));
        foreach ($clases as $clase) {
            $this->assertFalse($clase::canAccess(), "$clase debe negar a un supervisor");
        }

        $this->actingAs(User::factory()->create()->assignRole(RolesSedeq::ADMIN));
        foreach ($clases as $clase) {
            $this->assertTrue($clase::canAccess(), "$clase debe abrirse al admin");
        }
    }
}
