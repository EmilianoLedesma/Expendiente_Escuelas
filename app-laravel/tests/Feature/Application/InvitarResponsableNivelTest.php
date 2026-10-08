<?php

namespace Tests\Feature\Application;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\ResponsablesNivel\InvitarResponsableNivel;
use App\Models\ResponsableNivel;
use App\Models\Solicitante;
use App\Models\User;
use App\Notifications\InvitacionResponsableNivel;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\TestCase;

class InvitarResponsableNivelTest extends TestCase
{
    use ConNivelParaResponsables;
    use RefreshDatabase;

    private function invitar(Solicitante $solicitante, int $nivelId, string $correo = 'ana@example.test', string $nombre = 'Ana Ruiz'): ResponsableNivel
    {
        return app(InvitarResponsableNivel::class)->ejecutar($solicitante->id, $nivelId, $nombre, $correo);
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

    public function test_correo_nuevo_crea_cuenta_verificada_con_rol_acceso_y_enlace(): void
    {
        Notification::fake();
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno);
        (new RolesSeeder)->run();

        $acceso = $this->invitar($dueno, $nivel->id, '  Ana@Example.TEST ');

        $usuario = User::where('email', 'ana@example.test')->firstOrFail();
        $this->assertSame($usuario->id, $acceso->user_id);
        $this->assertSame($nivel->id, $acceso->escuela_nivel_id);
        $this->assertSame($dueno->id, $acceso->invitado_por_solicitante_id);
        $this->assertTrue($usuario->hasRole('responsable_nivel'));
        $this->assertNotNull($usuario->email_verified_at);
        $this->assertNull($usuario->solicitante);
        Notification::assertSentTo($usuario, InvitacionResponsableNivel::class);
    }

    public function test_responsable_existente_recibe_el_nuevo_nivel_en_la_misma_cuenta(): void
    {
        Notification::fake();
        $dueno = Solicitante::factory()->create();
        $nivelA = $this->nivelDe($dueno, 'primaria');
        $nivelB = $this->nivelEn($nivelA->escuela, 'secundaria');
        $this->invitar($dueno, $nivelA->id);

        $this->invitar($dueno, $nivelB->id, 'ANA@example.test');

        $this->assertSame(1, User::where('email', 'ana@example.test')->count());
        $this->assertSame(2, ResponsableNivel::count());
    }

    public function test_rechaza_nivel_ajeno_inexistente_o_fuera_de_captura(): void
    {
        $dueno = Solicitante::factory()->create();
        $ajeno = Solicitante::factory()->create();
        $nivelAjeno = $this->nivelDe($ajeno);
        $enRevision = $this->nivelDe($dueno, 'primaria', 'en_revision');

        $this->assertArrayHasKey('escuelaNivelId', $this->errores(fn () => $this->invitar($dueno, $nivelAjeno->id)));
        $this->assertArrayHasKey('escuelaNivelId', $this->errores(fn () => $this->invitar($dueno, 999999)));
        $this->assertArrayHasKey('escuelaNivelId', $this->errores(fn () => $this->invitar($dueno, $enRevision->id)));
        $this->assertSame(0, ResponsableNivel::count());
    }

    public function test_rechaza_correos_de_solicitantes_sedeq_o_acceso_duplicado(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno);
        $otroSolicitante = Solicitante::factory()->create();
        (new RolesSeeder)->run();
        $sedeq = User::factory()->create();
        $sedeq->assignRole('sedeq');
        $this->invitar($dueno, $nivel->id, 'ya@example.test');

        $this->assertArrayHasKey('correo', $this->errores(fn () => $this->invitar($dueno, $nivel->id, $otroSolicitante->user->email)));
        $this->assertArrayHasKey('correo', $this->errores(fn () => $this->invitar($dueno, $nivel->id, $sedeq->email)));
        $this->assertArrayHasKey('correo', $this->errores(fn () => $this->invitar($dueno, $nivel->id, 'YA@example.test')));
    }

    public function test_si_el_aviso_falla_la_invitacion_queda_guardada(): void
    {
        Notification::shouldReceive('send')->andThrow(new RuntimeException('SMTP caído'));
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno);
        (new RolesSeeder)->run();

        $acceso = $this->invitar($dueno, $nivel->id);

        $this->assertTrue(ResponsableNivel::whereKey($acceso->id)->exists());
    }

    public function test_correo_nuevo_creado_en_paralelo_se_rechaza_como_correo_ocupado(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno);
        (new RolesSeeder)->run();
        // Simula la otra invitación concurrente: inserta el mismo correo justo antes del INSERT.
        User::creating(function (User $u) {
            if ($u->email === 'ana@example.test') {
                User::withoutEvents(fn () => User::factory()->create(['email' => 'ana@example.test']));
            }
        });

        $this->assertSame(
            ['correo' => 'Este correo ya pertenece a otra cuenta del sistema.'],
            $this->errores(fn () => $this->invitar($dueno, $nivel->id)),
        );
    }

    public function test_rechaza_nombre_vacio_y_correo_mal_formado(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno);

        $errores = $this->errores(fn () => $this->invitar($dueno, $nivel->id, 'no-es-correo', '   '));

        $this->assertArrayHasKey('nombre', $errores);
        $this->assertArrayHasKey('correo', $errores);
    }
}
