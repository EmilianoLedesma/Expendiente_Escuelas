<?php

namespace Tests\Feature\Revision;

use App\Application\Archivo\RegistrarAccion;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\CrearCuentaSedeq;
use App\Application\Revision\RolesSedeq;
use App\Models\User;
use App\Notifications\RestablecerContrasena;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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
            $this->assertStringNotContainsString('example.com', (string) $fila->detalle);
        }
    }

    public function test_rechaza_actor_no_admin(): void
    {
        (new RolesSeeder)->run();
        $sup = User::factory()->create()->assignRole(RolesSedeq::SUPERVISOR);

        try {
            $this->crear($sup, 'Ana', 'ana@example.com', RolesSedeq::ADMIN);
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame('rol', $e->etapaFaltante);
        }
        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
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
