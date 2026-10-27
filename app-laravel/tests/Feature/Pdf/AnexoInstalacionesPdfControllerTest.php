<?php

namespace Tests\Feature\Pdf;

use App\Http\Controllers\Tramite\AnexoInstalacionesPdfController;
use App\Models\EscuelaNivel;
use App\Models\ResponsableNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\TestCase;

/** Anexo 2 Instalaciones: descarga para el dueno y el responsable de ESTE nivel, siempre (borrador), tambien tras el envio. */
class AnexoInstalacionesPdfControllerTest extends TestCase
{
    use ConNivelParaResponsables;
    use RefreshDatabase;

    private function url(EscuelaNivel $nivel): string
    {
        return route('tramite.paso3.infraestructura.anexo-instalaciones', ['escuelaNivel' => $nivel->id]);
    }

    public function test_el_dueno_descarga_el_pdf(): void
    {
        $dueno = Solicitante::factory()->create();

        $this->actingAs($dueno->user)->get($this->url($this->nivelDe($dueno)))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_la_url_es_la_acordada(): void
    {
        $this->assertSame('/tramite/paso3/7/infraestructura/anexo-instalaciones.pdf', route('tramite.paso3.infraestructura.anexo-instalaciones', 7, false));
        $this->assertSame(AnexoInstalacionesPdfController::class, Route::getRoutes()->getByName('tramite.paso3.infraestructura.anexo-instalaciones')->getActionName());
    }

    public function test_otro_solicitante_recibe_403(): void
    {
        $nivel = $this->nivelDe(Solicitante::factory()->create());

        $this->actingAs(Solicitante::factory()->create()->user)->get($this->url($nivel))->assertForbidden();
    }

    public function test_el_responsable_de_ese_nivel_descarga(): void
    {
        $nivel = $this->nivelDe(Solicitante::factory()->create());

        $this->actingAs($this->responsableDe($nivel))->get($this->url($nivel))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_el_responsable_de_otro_nivel_recibe_403(): void
    {
        $nivelA = $this->nivelDe(Solicitante::factory()->create());
        $nivelB = $this->nivelEn($nivelA->escuela, 'secundaria');

        $this->actingAs($this->responsableDe($nivelB))->get($this->url($nivelA))->assertForbidden();
    }

    public function test_un_invitado_va_al_login(): void
    {
        $nivel = $this->nivelDe(Solicitante::factory()->create());

        $this->get($this->url($nivel))->assertRedirect(route('login'));
    }

    public function test_un_correo_sin_verificar_va_a_verificacion(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno);
        $dueno->user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($dueno->user)->get($this->url($nivel))->assertRedirect(route('verification.notice'));
    }

    public function test_el_tramite_enviado_sigue_descargando(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno, 'primaria', 'en_revision');

        $this->actingAs($dueno->user)->get($this->url($nivel))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_un_responsable_revocado_recibe_403(): void
    {
        $nivel = $this->nivelDe(Solicitante::factory()->create());
        $responsable = $this->responsableDe($nivel);
        ResponsableNivel::where('escuela_nivel_id', $nivel->id)->delete();

        $this->actingAs($responsable)->get($this->url($nivel))->assertForbidden();
    }

    public function test_un_id_no_numerico_da_404(): void
    {
        $this->actingAs(Solicitante::factory()->create()->user)
            ->get('/tramite/paso3/abc/infraestructura/anexo-instalaciones.pdf')
            ->assertNotFound();
    }
}
