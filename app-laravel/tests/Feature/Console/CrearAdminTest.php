<?php

namespace Tests\Feature\Console;

use App\Models\User;
use App\Notifications\RestablecerContrasena;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CrearAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_admin_verificado_con_correo_en_minusculas_y_envia_enlace(): void
    {
        Notification::fake();

        $this->artisan('sedeq:crear-admin', ['correo' => 'Admin@Example.test', 'nombre' => 'Adm'])
            ->assertExitCode(0);

        $u = User::where('email', 'admin@example.test')->firstOrFail();
        $this->assertNotNull($u->email_verified_at);
        $this->assertSame(['admin'], $u->getRoleNames()->all());
        Notification::assertSentTo($u, RestablecerContrasena::class);
    }

    public function test_correo_existente_falla_sin_cambios(): void
    {
        Notification::fake();
        $u = User::factory()->create(['email' => 'admin@example.test', 'name' => 'Original']);

        $this->artisan('sedeq:crear-admin', ['correo' => 'admin@example.test', 'nombre' => 'Adm'])
            ->assertExitCode(1);

        $this->assertSame(1, User::count());
        $this->assertSame('Original', $u->fresh()->name);
        $this->assertSame([], $u->fresh()->getRoleNames()->all());
        Notification::assertNothingSent();
    }
}
