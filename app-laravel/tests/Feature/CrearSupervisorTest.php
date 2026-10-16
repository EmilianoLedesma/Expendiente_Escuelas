<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\RestablecerContrasena;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CrearSupervisorTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_supervisor_verificado_sin_solicitante_y_envia_enlace(): void
    {
        Notification::fake();

        $this->artisan('sedeq:crear-supervisor', ['correo' => 'sup@example.test', 'nombre' => 'Sup'])
            ->assertExitCode(0);

        $u = User::where('email', 'sup@example.test')->firstOrFail();
        $this->assertNotNull($u->email_verified_at);
        $this->assertSame(['supervisor'], $u->getRoleNames()->all());
        $this->assertNull($u->solicitante);
        Notification::assertSentTo($u, RestablecerContrasena::class);
    }

    public function test_correo_existente_falla_sin_cambios(): void
    {
        Notification::fake();
        $u = User::factory()->create(['email' => 'sup@example.test', 'name' => 'Original']);

        $this->artisan('sedeq:crear-supervisor', ['correo' => 'sup@example.test', 'nombre' => 'Sup'])
            ->assertExitCode(1);

        $this->assertSame(1, User::count());
        $this->assertSame('Original', $u->fresh()->name);
        $this->assertSame([], $u->fresh()->getRoleNames()->all());
        Notification::assertNothingSent();
    }
}
