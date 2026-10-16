<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\InvitarRevisor;
use App\Application\Revision\RolesSedeq;
use App\Models\Revisor;
use App\Models\Solicitante;
use App\Models\User;
use App\Notifications\InvitacionRevisor;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvitarRevisorTest extends TestCase
{
    use RefreshDatabase;

    private User $sup;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['sedeq', 'supervisor', 'revisor', 'responsable_nivel', 'solicitante'] as $rol) {
            Role::findOrCreate($rol, 'web');
        }
        $this->sup = User::factory()->create()->assignRole(RolesSedeq::SUPERVISOR);
    }

    private function invitar(string $correo = 'rev@example.test', string $nombre = 'Rosa Rev', ?int $actorId = null): User
    {
        return app(InvitarRevisor::class)->ejecutar($actorId ?? $this->sup->id, $nombre, $correo);
    }

    private function errores(callable $accion): array
    {
        try {
            $accion();
        } catch (DatosInvalidos $e) {
            return $e->errores;
        }
        $this->fail('Se esperaba DatosInvalidos.');
    }

    public function test_correo_nuevo_crea_revisor_verificado_con_aviso_con_enlace_y_sin_registered(): void
    {
        Notification::fake();
        Event::fake([Registered::class]);

        $u = $this->invitar('  Rev@Example.TEST ');

        $this->assertSame('rev@example.test', $u->email);
        $this->assertNotNull($u->email_verified_at);
        $this->assertSame(['revisor'], $u->getRoleNames()->all());
        $this->assertNull($u->solicitante);
        $this->assertNull(Revisor::findOrFail($u->id)->revocado_at);
        Notification::assertSentTo($u, InvitacionRevisor::class, fn ($n) => $n->token !== null);
        Event::assertNotDispatched(Registered::class);
    }

    public function test_rechaza_correos_de_otras_cuentas(): void
    {
        $activo = User::factory()->create()->assignRole('revisor');
        Revisor::create(['user_id' => $activo->id]);
        $cuentas = [
            Solicitante::factory()->create()->user,
            User::factory()->create()->assignRole('responsable_nivel'),
            User::factory()->create()->assignRole('sedeq'),
            $this->sup,
            $activo,
            User::factory()->create(),
        ];

        foreach ($cuentas as $cuenta) {
            $this->assertArrayHasKey('correo', $this->errores(fn () => $this->invitar(strtoupper($cuenta->email))));
            $revisorAntes = $cuenta->is($activo);
            $this->assertSame($revisorAntes, $cuenta->fresh()->hasRole('revisor'));
            $this->assertSame($revisorAntes, Revisor::whereKey($cuenta->id)->exists());
        }
        $this->assertNull(Revisor::findOrFail($activo->id)->revocado_at);
    }

    public function test_revisor_revocado_se_reinvita_sin_enlace(): void
    {
        $viejo = User::factory()->create();
        Revisor::create(['user_id' => $viejo->id, 'revocado_at' => now()]);
        Notification::fake();

        $u = $this->invitar($viejo->email);

        $this->assertSame($viejo->id, $u->id);
        $this->assertTrue($u->hasRole('revisor'));
        $this->assertNull(Revisor::findOrFail($u->id)->revocado_at);
        Notification::assertSentTo($u, InvitacionRevisor::class, fn ($n) => $n->token === null);
    }

    public function test_revisor_revocado_con_otro_rol_o_fila_de_solicitante_no_se_reinvita(): void
    {
        $conRol = User::factory()->create()->assignRole('sedeq');
        $conSolicitante = Solicitante::factory()->create()->user;
        foreach ([$conRol, $conSolicitante] as $u) {
            Revisor::create(['user_id' => $u->id, 'revocado_at' => now()]);
        }

        foreach ([$conRol, $conSolicitante] as $u) {
            $this->assertArrayHasKey('correo', $this->errores(fn () => $this->invitar($u->email)));
            $this->assertFalse($u->fresh()->hasRole('revisor'));
            $this->assertNotNull(Revisor::findOrFail($u->id)->revocado_at);
        }
    }

    public function test_solo_un_supervisor_invita(): void
    {
        $revisor = User::factory()->create()->assignRole('revisor');

        $this->expectException(PrecondicionIncumplida::class);
        $this->invitar('x@example.test', 'X', $revisor->id);
    }

    public function test_rechaza_nombre_vacio_y_correo_mal_formado(): void
    {
        $errores = $this->errores(fn () => $this->invitar('no-es-correo', '   '));

        $this->assertArrayHasKey('nombre', $errores);
        $this->assertArrayHasKey('correo', $errores);
    }
}
