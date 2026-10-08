<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Tramite\ResponsablesNivel;
use App\Models\ResponsableNivel;
use App\Models\Solicitante;
use App\Models\User;
use App\Notifications\InvitacionResponsableNivel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\TestCase;

class ResponsablesNivelPaginaTest extends TestCase
{
    use ConNivelParaResponsables;
    use RefreshDatabase;

    public function test_el_selector_ofrece_solo_niveles_asignables_del_solicitante(): void
    {
        $dueno = Solicitante::factory()->create();
        $asignable = $this->nivelDe($dueno, 'primaria');
        $enRevision = $this->nivelEn($asignable->escuela, 'secundaria', 'en_revision');
        $ajeno = $this->nivelDe(Solicitante::factory()->create(), 'preescolar');

        Livewire::actingAs($dueno->user)->test(ResponsablesNivel::class)
            ->assertSeeHtml('<option value="'.$asignable->id.'">')
            ->assertDontSeeHtml('<option value="'.$enRevision->id.'">')
            ->assertDontSeeHtml('<option value="'.$ajeno->id.'">');
    }

    public function test_los_bucles_llevan_wire_key(): void
    {
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno);
        $responsable = $this->responsableDe($nivel);

        Livewire::actingAs($dueno->user)->test(ResponsablesNivel::class)
            ->assertSeeHtml('wire:key="nivel-'.$nivel->id.'"')
            ->assertSeeHtml('wire:key="acceso-'.$responsable->accesosNivel()->value('id').'"');
    }

    public function test_invitar_crea_el_acceso_y_envia_el_aviso(): void
    {
        Notification::fake();
        $dueno = Solicitante::factory()->create();
        $nivel = $this->nivelDe($dueno);

        Livewire::actingAs($dueno->user)->test(ResponsablesNivel::class)
            ->set('escuelaNivelId', (string) $nivel->id)
            ->set('nombre', 'Ana Ruiz')
            ->set('correo', 'ana@example.test')
            ->call('invitar')
            ->assertHasNoErrors()
            ->assertSet('correo', '');

        $usuario = User::where('email', 'ana@example.test')->firstOrFail();
        $this->assertTrue(ResponsableNivel::where('user_id', $usuario->id)->where('escuela_nivel_id', $nivel->id)->exists());
        Notification::assertSentTo($usuario, InvitacionResponsableNivel::class);
    }

    public function test_un_nivel_ajeno_manipulado_muestra_error_y_no_crea_nada(): void
    {
        $dueno = Solicitante::factory()->create();
        $ajeno = $this->nivelDe(Solicitante::factory()->create());

        Livewire::actingAs($dueno->user)->test(ResponsablesNivel::class)
            ->set('escuelaNivelId', (string) $ajeno->id)
            ->set('nombre', 'Ana Ruiz')
            ->set('correo', 'ana@example.test')
            ->call('invitar')
            ->assertHasErrors(['escuelaNivelId']);

        $this->assertSame(0, ResponsableNivel::count());
    }

    public function test_revocar_y_revocar_un_acceso_ajeno(): void
    {
        $dueno = Solicitante::factory()->create();
        $mio = $this->responsableDe($this->nivelDe($dueno));
        $otroDueno = Solicitante::factory()->create();
        $ajeno = $this->responsableDe($this->nivelDe($otroDueno));
        $accesoMio = ResponsableNivel::where('user_id', $mio->id)->firstOrFail();
        $accesoAjeno = ResponsableNivel::where('user_id', $ajeno->id)->firstOrFail();

        Livewire::actingAs($dueno->user)->test(ResponsablesNivel::class)
            ->call('revocar', $accesoAjeno->id)
            ->assertForbidden();
        $this->assertTrue(ResponsableNivel::whereKey($accesoAjeno->id)->exists());

        Livewire::actingAs($dueno->user)->test(ResponsablesNivel::class)->call('revocar', $accesoMio->id);
        $this->assertFalse(User::whereKey($mio->id)->exists());
    }

    public function test_los_avisos_no_se_filtran_a_la_siguiente_peticion(): void
    {
        $dueno = Solicitante::factory()->create();
        $mio = $this->responsableDe($this->nivelDe($dueno));
        $acceso = ResponsableNivel::where('user_id', $mio->id)->firstOrFail();

        Livewire::actingAs($dueno->user)->test(ResponsablesNivel::class)
            ->call('revocar', $acceso->id)
            ->assertSee('Acceso revocado');

        // now() lo marca como viejo: el guardado de sesión lo descarta y no llega a la siguiente petición.
        $this->assertNotContains('status', session()->get('_flash.new', []));
    }

    public function test_la_navbar_describe_el_destino_del_enlace(): void
    {
        $responsable = $this->responsableDe($this->nivelDe(Solicitante::factory()->create()));

        $this->actingAs(Solicitante::factory()->create()->user)->get(route('tramite.index'))
            ->assertSee('<span class="sr-only"> — Responsables por nivel</span>', false);
        $this->actingAs($responsable)->get(route('tramite.index'))
            ->assertDontSee('— Responsables por nivel', false);
    }

    public function test_la_ruta_es_solo_del_solicitante(): void
    {
        $responsable = $this->responsableDe($this->nivelDe(Solicitante::factory()->create()));

        $this->actingAs($responsable)->get(route('tramite.responsables'))->assertRedirect(route('tramite.index'));
        $this->actingAs(Solicitante::factory()->create()->user)->get(route('tramite.responsables'))->assertOk()->assertSee('Responsables por nivel');
    }

    public function test_la_navbar_enlaza_el_nombre_solo_para_el_solicitante(): void
    {
        $solicitante = Solicitante::factory()->create();
        $responsable = $this->responsableDe($this->nivelDe(Solicitante::factory()->create()));

        $this->actingAs($solicitante->user)->get(route('tramite.index'))
            ->assertSee('href="'.route('tramite.responsables').'"', false);
        $this->actingAs($responsable)->get(route('tramite.index'))
            ->assertDontSee('href="'.route('tramite.responsables').'"', false);
    }
}
