<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Tramite\ResponsablesNivel;
use App\Models\Solicitante;
use App\Models\User;
use App\Notifications\VerificarCorreo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\TestCase;

/** Cada botón que dispara un correo usa x-ui.stateful-button y su acción devuelve true solo si el envío quedó en cola. */
class BotonesDeCorreoTest extends TestCase
{
    use ConNivelParaResponsables;
    use RefreshDatabase;

    public function test_reenviar_verificacion_devuelve_true_y_conserva_el_envio(): void
    {
        Notification::fake();
        $usuario = User::factory()->unverified()->create();

        Livewire::actingAs($usuario);
        Volt::test('pages.auth.verify-email')
            ->assertSeeHtml('wire:ignore')
            ->assertSeeHtml('sendVerification')
            ->call('sendVerification')
            ->assertReturned(true)
            ->assertDispatched('toast', tipo: 'success', titulo: 'Enlace enviado');

        Notification::assertSentTo($usuario, VerificarCorreo::class);
    }

    public function test_reenviar_verificacion_con_correo_ya_verificado_no_es_un_error(): void
    {
        Notification::fake();
        Livewire::actingAs(User::factory()->create());

        Volt::test('pages.auth.verify-email')
            ->call('sendVerification')
            ->assertReturned(true);

        Notification::assertNothingSent();
    }

    public function test_recuperar_contrasena_devuelve_true_solo_si_el_enlace_se_envio(): void
    {
        Notification::fake();
        $usuario = User::factory()->create();

        Volt::test('pages.auth.forgot-password')
            ->assertSeeHtml('wire:ignore')
            ->assertSeeHtml('sendPasswordResetLink')
            ->set('email', $usuario->email)
            ->call('sendPasswordResetLink')
            ->assertReturned(true);

        Volt::test('pages.auth.forgot-password')
            ->set('email', 'nadie@example.test')
            ->call('sendPasswordResetLink')
            ->assertReturned(false)
            ->assertHasErrors('email');
    }

    public function test_invitar_devuelve_true_si_se_creo_el_acceso_y_no_si_los_datos_son_invalidos(): void
    {
        Notification::fake();
        $dueno = Solicitante::factory()->create();
        $propio = $this->nivelDe($dueno);
        $ajeno = $this->nivelDe(Solicitante::factory()->create());

        $pagina = Livewire::actingAs($dueno->user)->test(ResponsablesNivel::class)
            ->assertSeeHtml('wire:ignore')
            ->assertSeeHtml('invitar');

        $pagina->set('escuelaNivelId', (string) $propio->id)
            ->set('nombre', 'Ana Ruiz')
            ->set('correo', 'ana@example.test')
            ->call('invitar')
            ->assertReturned(true);

        $pagina->set('escuelaNivelId', (string) $ajeno->id)
            ->set('nombre', 'Luis Mora')
            ->set('correo', 'luis@example.test')
            ->call('invitar')
            ->assertHasErrors('escuelaNivelId');
    }
}
