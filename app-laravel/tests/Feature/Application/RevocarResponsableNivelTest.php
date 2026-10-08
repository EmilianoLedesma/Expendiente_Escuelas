<?php

namespace Tests\Feature\Application;

use App\Application\ResponsablesNivel\RevocarResponsableNivel;
use App\Models\ResponsableNivel;
use App\Models\Solicitante;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\TestCase;

class RevocarResponsableNivelTest extends TestCase
{
    use ConNivelParaResponsables;
    use RefreshDatabase;

    public function test_ultimo_acceso_borra_la_cuenta_y_lo_capturado_permanece(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno);
        $usuario = $this->responsableDe($nivel);
        $acceso = ResponsableNivel::where('user_id', $usuario->id)->firstOrFail();

        app(RevocarResponsableNivel::class)->ejecutar($dueno->id, $acceso->id);

        $this->assertFalse(User::whereKey($usuario->id)->exists());
        $this->assertDatabaseHas('escuela_niveles', ['id' => $nivel->id]);
    }

    public function test_con_otro_nivel_conserva_la_cuenta_y_el_nivel_viejo_da_403(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivelA = $this->nivelDe($dueno, 'primaria');
        $nivelB = $this->nivelEn($nivelA->escuela, 'secundaria');
        $usuario = $this->responsableDe($nivelA, $nivelB);
        $acceso = ResponsableNivel::where('user_id', $usuario->id)->where('escuela_nivel_id', $nivelA->id)->firstOrFail();

        app(RevocarResponsableNivel::class)->ejecutar($dueno->id, $acceso->id);

        $this->assertTrue(User::whereKey($usuario->id)->exists());
        $this->assertFalse($usuario->fresh()->can('update', $nivelA));
        $this->assertTrue($usuario->fresh()->can('update', $nivelB));
        $this->actingAs($usuario)->get(route('tramite.paso2-nivel-documentos', ['escuelaNivel' => $nivelA->id]))->assertForbidden();
    }

    public function test_no_dueno_recibe_authorization_exception_y_no_se_borra_nada(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno);
        $usuario = $this->responsableDe($nivel);
        $acceso = ResponsableNivel::where('user_id', $usuario->id)->firstOrFail();
        $otro = Solicitante::factory()->create();

        try {
            app(RevocarResponsableNivel::class)->ejecutar($otro->id, $acceso->id);
            $this->fail('Se esperaba AuthorizationException.');
        } catch (AuthorizationException) {
            $this->assertTrue(ResponsableNivel::whereKey($acceso->id)->exists());
            $this->assertTrue(User::whereKey($usuario->id)->exists());
        }
    }

    public function test_un_id_inexistente_no_hace_nada(): void
    {
        app(RevocarResponsableNivel::class)->ejecutar(Solicitante::factory()->create()->id, 999999);

        $this->assertSame(0, ResponsableNivel::count());
    }
}
