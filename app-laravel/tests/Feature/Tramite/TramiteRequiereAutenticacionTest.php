<?php

namespace Tests\Feature\Tramite;

use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TramiteRequiereAutenticacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_anonimo_es_redirigido_a_login(): void
    {
        $response = $this->get('/tramite/preregistro');

        $response->assertRedirect('/login');
    }

    public function test_usuario_autenticado_puede_ver_el_formulario(): void
    {
        $solicitante = Solicitante::factory()->create();
        $this->actingAs($solicitante->user);

        $response = $this->get('/tramite/preregistro');

        $response->assertOk();
    }
}
