<?php

namespace Tests\Feature\Revision;

use App\Application\Archivo\RegistrarAccion;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\CrearCuentaSedeq;
use App\Application\Revision\RolesSedeq;
use App\Models\Solicitante;
use App\Models\User;
use App\Notifications\RestablecerContrasena;
use Database\Seeders\RolesSeeder;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class CrearCuentaSedeqTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        (new RolesSeeder)->run();

        return User::factory()->create()->assignRole(RolesSedeq::ADMIN);
    }

    private function crear(User $actor, string $nombre, string $correo, string $rol): User
    {
        return app(CrearCuentaSedeq::class)->ejecutar($actor->id, $nombre, $correo, $rol);
    }

    private function sinBitacora(): void
    {
        $this->assertSame(0, DB::table('bitacora_acciones')->where('accion', RegistrarAccion::CREAR_CUENTA_SEDEQ)->count());
    }

    public function test_crea_cuenta_verificada_con_rol_aviso_y_bitacora(): void
    {
        Notification::fake();
        $admin = $this->admin();

        foreach ([RolesSedeq::SUPERVISOR, RolesSedeq::ADMIN] as $i => $rol) {
            $u = $this->crear($admin, ' Ana ', " Ana{$i}@Example.COM ", $rol);

            $this->assertSame("ana{$i}@example.com", $u->email);
            $this->assertSame('Ana', $u->name);
            $this->assertNotNull($u->fresh()->email_verified_at);
            $this->assertTrue($u->fresh()->hasRole($rol));
            Notification::assertSentTo($u, RestablecerContrasena::class);
            $fila = DB::table('bitacora_acciones')->where('accion', RegistrarAccion::CREAR_CUENTA_SEDEQ)->where('objeto_id', $u->id)->first();
            $this->assertSame($admin->id, (int) $fila->actor_id);
            $this->assertSame('user', $fila->objeto_tipo);
            $this->assertSame(['rol' => $rol], json_decode((string) $fila->detalle, true));
            $this->assertStringNotContainsString('Ana', (string) $fila->detalle);
            $this->assertStringNotContainsString('example.com', (string) $fila->detalle);
        }
    }

    public function test_rechaza_actor_no_admin_antes_de_validar(): void
    {
        (new RolesSeeder)->run();
        $actores = [
            User::factory()->create()->assignRole(RolesSedeq::SUPERVISOR),
            User::factory()->create()->assignRole(RolesSedeq::REVISOR),
            Solicitante::factory()->create()->user,
        ];

        foreach ($actores as $actor) {
            foreach ([['Ana', 'ana@example.com'], ['', 'no-es-correo']] as [$nombre, $correo]) {
                try {
                    $this->crear($actor, $nombre, $correo, RolesSedeq::ADMIN);
                    $this->fail('Se esperaba PrecondicionIncumplida.');
                } catch (PrecondicionIncumplida $e) {
                    $this->assertSame('rol', $e->etapaFaltante);
                }
            }
        }
        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
        $this->sinBitacora();
    }

    public function test_un_aviso_fallido_no_deshace_la_cuenta(): void
    {
        $admin = $this->admin();
        $this->mock(Dispatcher::class, fn ($m) => $m->shouldReceive('send')->andThrow(new RuntimeException('smtp caido')));

        $u = $this->crear($admin, 'Ana', 'ana@example.com', RolesSedeq::SUPERVISOR);

        $this->assertTrue($u->fresh()->hasRole(RolesSedeq::SUPERVISOR));
        $this->assertSame(1, DB::table('bitacora_acciones')->where('accion', RegistrarAccion::CREAR_CUENTA_SEDEQ)->where('objeto_id', $u->id)->count());
    }

    public function test_rechaza_correo_existente_con_otra_capitalizacion_guardada(): void
    {
        $admin = $this->admin();
        DB::table('users')->where('id', User::factory()->create(['email' => 'x@example.com'])->id)->update(['email' => 'Mixto@Example.com']);

        try {
            $this->crear($admin, 'Ana', 'mixto@example.com', RolesSedeq::SUPERVISOR);
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('correo', $e->errores);
        }
        $this->sinBitacora();
    }

    public function test_rechaza_datos_invalidos(): void
    {
        $admin = $this->admin();
        $casos = [
            ['correo', ['Ana', 'no-es-correo', RolesSedeq::ADMIN]],
            ['nombre', ['  ', 'ana@example.com', RolesSedeq::ADMIN]],
            ['rol', ['Ana', 'ana@example.com', RolesSedeq::REVISOR]],
            ['rol', ['Ana', 'ana@example.com', 'otro']],
        ];
        foreach ($casos as [$campo, $args]) {
            try {
                $this->crear($admin, ...$args);
                $this->fail('Se esperaba DatosInvalidos.');
            } catch (DatosInvalidos $e) {
                $this->assertArrayHasKey($campo, $e->errores);
            }
        }
        $this->assertSame(1, User::count());
        $this->sinBitacora();
    }

    public function test_rechaza_correo_existente_incluso_archivado(): void
    {
        $admin = $this->admin();
        User::factory()->create(['email' => 'viva@example.com']);
        User::factory()->create(['email' => 'vieja@example.com', 'archivado_at' => now()]);

        foreach (['viva@example.com', 'VIEJA@example.com'] as $correo) {
            try {
                $this->crear($admin, 'Ana', $correo, RolesSedeq::SUPERVISOR);
                $this->fail('Se esperaba DatosInvalidos.');
            } catch (DatosInvalidos $e) {
                $this->assertArrayHasKey('correo', $e->errores);
            }
        }
        $this->sinBitacora();
    }
}
