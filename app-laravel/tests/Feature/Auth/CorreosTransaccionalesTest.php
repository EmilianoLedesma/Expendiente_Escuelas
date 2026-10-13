<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\InvitacionResponsableNivel;
use App\Notifications\RestablecerContrasena;
use App\Notifications\VerificarCorreo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CorreosTransaccionalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_restablecer_contrasena_va_en_espanol_y_por_cola(): void
    {
        $usuario = User::factory()->create();
        $aviso = new RestablecerContrasena('tok-123');

        $this->assertInstanceOf(ShouldQueue::class, $aviso);
        $correo = $aviso->toMail($usuario);

        $this->assertSame('Restablece tu contraseña', $correo->subject);
        $this->assertSame('Restablecer contraseña', $correo->actionText);
        $this->assertStringContainsString('tok-123', $correo->actionUrl);
        $this->assertStringContainsString(rawurlencode($usuario->email), $correo->actionUrl);
    }

    public function test_verificar_correo_va_en_espanol_y_por_cola(): void
    {
        $usuario = User::factory()->unverified()->create();
        $aviso = new VerificarCorreo;

        $this->assertInstanceOf(ShouldQueue::class, $aviso);
        $correo = $aviso->toMail($usuario);

        $this->assertSame('Verifica tu correo electrónico', $correo->subject);
        $this->assertSame('Verificar correo', $correo->actionText);
        $this->assertStringContainsString('/verify-email/', $correo->actionUrl);
    }

    public function test_el_usuario_envia_sus_avisos_con_las_clases_en_espanol(): void
    {
        Notification::fake();
        $usuario = User::factory()->unverified()->create();

        $usuario->sendPasswordResetNotification('tok');
        $usuario->sendEmailVerificationNotification();

        Notification::assertSentTo($usuario, RestablecerContrasena::class);
        Notification::assertSentTo($usuario, VerificarCorreo::class);
    }

    public function test_la_invitacion_va_por_cola(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new InvitacionResponsableNivel('Ana', 'Primaria', null));
    }

    public function test_mailtrap_esta_definido_como_mailer(): void
    {
        $this->assertSame(['transport' => 'mailtrap-sdk'], config('mail.mailers.mailtrap-sdk'));
    }
}
