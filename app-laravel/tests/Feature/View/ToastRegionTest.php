<?php

namespace Tests\Feature\View;

use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToastRegionTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_region_es_un_aviso_accesible_y_escucha_el_evento_toast(): void
    {
        $this->blade('<x-ui.toast-region />')
            ->assertSee('data-toast-region', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('aria-label="Notificaciones"', false)
            ->assertSee('x-on:toast.window', false);
    }

    public function test_muestra_el_toast_flasheado_en_la_carga(): void
    {
        $this->withSession(['toast' => ['tipo' => 'success', 'titulo' => 'Guardado', 'descripcion' => 'Todo listo']])
            ->blade('<x-ui.toast-region />')
            ->assertSee('Guardado')
            ->assertSee('Todo listo');
    }

    public function test_admite_varios_toasts_flasheados(): void
    {
        $this->withSession(['toast' => [
            ['tipo' => 'success', 'titulo' => 'Primero'],
            ['tipo' => 'error', 'titulo' => 'Segundo'],
        ]])
            ->blade('<x-ui.toast-region />')
            ->assertSee('Primero')
            ->assertSee('Segundo');
    }

    public function test_un_status_heredado_se_muestra_como_toast_de_exito(): void
    {
        $this->withSession(['status' => 'El trámite ya no existe.'])
            ->blade('<x-ui.toast-region />')
            ->assertSee('El tr')
            ->assertSee('success', false);
    }

    public function test_el_status_tecnico_de_verificacion_se_traduce_a_un_aviso_en_espanol(): void
    {
        $this->withSession(['status' => 'verification-link-sent'])
            ->blade('<x-ui.toast-region />')
            ->assertSee('Enlace enviado')
            ->assertDontSee('verification-link-sent');
    }

    public function test_sin_flash_no_hay_toasts_iniciales(): void
    {
        $this->blade('<x-ui.toast-region />')->assertSee('[]', false);
    }

    public function test_los_tres_layouts_montan_la_region(): void
    {
        $this->get('/login')->assertSee('data-toast-region', false);

        $this->actingAs(Solicitante::factory()->create()->user)
            ->get(route('tramite.index'))
            ->assertSee('data-toast-region', false);

        $this->blade('<x-app-layout>contenido</x-app-layout>')->assertSee('data-toast-region', false);
    }
}
