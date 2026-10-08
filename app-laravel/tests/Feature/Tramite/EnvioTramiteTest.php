<?php

namespace Tests\Feature\Tramite;

use App\Application\ResponsablesNivel\ListarNivelesDelResponsable;
use App\Application\Tramite\EnviarTramite;
use App\Application\Tramite\ResumenTramite;
use App\Livewire\Tramite\ValidacionFinal;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\User;
use Database\Seeders\ReglasValidacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\TestCase;

/** WS-7a §6: the send button, its confirmation, and what the solicitante sees afterwards. */
class EnvioTramiteTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use ConNivelParaResponsables;
    use RefreshDatabase;

    private function estadoDe(EscuelaNivel $nivel): string
    {
        return (string) DB::table('estados_expediente')->where('id', $nivel->fresh()->estado_id)->value('clave');
    }

    /** @return array{0: string, 1: string} [csrf token, wire:snapshot] of the Validación final page rendered for $usuario. */
    private function paginaDeValidacion(Escuela $escuela, User $usuario): array
    {
        $html = $this->actingAs($usuario)->get(route('tramite.validacion', ['escuela' => $escuela->id]))->assertOk()->getContent();
        preg_match('/name="csrf-token" content="([^"]+)"/', $html, $csrf);
        preg_match('/wire:snapshot="([^"]*)"/', $html, $snapshot);
        $this->assertNotEmpty($csrf);
        $this->assertNotEmpty($snapshot);

        return [$csrf[1], html_entity_decode($snapshot[1])];
    }

    public function test_con_el_expediente_listo_ofrece_enviar_con_confirmacion(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(ValidacionFinal::class, ['escuela' => $escuela])
            ->assertSee('Enviar a SEDEQ')
            ->assertSee('<dialog', false)
            ->assertSee('class="modal', false)
            ->assertSee('.showModal()', false)
            ->assertSee('Una vez enviado, el trámite ya no podrá modificarse.')
            ->assertSee('Sí, enviar a SEDEQ')
            ->assertSee('wire:click="enviar"', false)
            ->assertSee('wire:loading.attr="disabled"', false)
            ->assertDontSee('El envío a SEDEQ se habilitará en una próxima versión.');
    }

    public function test_si_algo_bloquea_el_boton_esta_deshabilitado_con_los_motivos(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        (new ReglasValidacionSeeder)->run();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(ValidacionFinal::class, ['escuela' => $escuela])
            ->assertSee('Corrige lo que bloquea el envío para poder enviar:')
            ->assertSee('Capacidad instalada · Primaria: Superficie de aulas')
            ->assertDontSee('Sí, enviar a SEDEQ')
            ->assertDontSee('wire:click="enviar"', false);
    }

    public function test_enviar_lleva_al_resumen_con_aviso(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(ValidacionFinal::class, ['escuela' => $escuela])
            ->call('enviar')
            ->assertHasNoErrors()
            ->assertRedirect(route('tramite.resumen', ['escuela' => $escuela->id]));

        $this->assertSame('en_revision', $this->estadoDe($nivel));
        $this->assertSame('Trámite enviado a SEDEQ.', session('status'));
    }

    public function test_si_el_envio_se_rechaza_el_motivo_va_al_resumen_de_errores(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $this->actingAs($escuela->solicitante->user);
        $pagina = Livewire::test(ValidacionFinal::class, ['escuela' => $escuela]);

        // The page said "lista"; the rules changed afterwards: EnviarTramite re-evaluates and refuses.
        (new ReglasValidacionSeeder)->run();

        $pagina->call('enviar')
            ->assertHasErrors(['envio'])
            ->assertNoRedirect()
            ->assertSee('Revisa los siguientes datos')
            ->assertSee('href="#envio"', false);
        $this->assertStringContainsString('El trámite aún no puede enviarse.', $pagina->errors()->first('envio'));
        $this->assertStringEndsWith('Pulsa «Validar de nuevo» para actualizar el resultado.', $pagina->errors()->first('envio'));
        $this->assertSame('en_captura', $this->estadoDe($nivel));
    }

    /** Review Focus 4 (same page, second click after the first finished). */
    public function test_un_segundo_envio_desde_la_misma_pagina_muestra_el_error(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $this->actingAs($escuela->solicitante->user);
        $pagina = Livewire::test(ValidacionFinal::class, ['escuela' => $escuela]);

        $pagina->call('enviar');
        $pagina->call('enviar')->assertHasErrors(['envio']);

        $this->assertSame('El trámite ya fue enviado.', $pagina->errors()->first('envio'));
        $this->assertSame(1, DB::table('historial_estados_expediente')->count());
    }

    /** Review Focus 4 over the real /livewire/update transport (ADR-003): Livewire re-applies can:update. */
    public function test_un_doble_clic_por_http_real_recibe_403_en_el_segundo_envio(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $usuario = $escuela->solicitante->user;

        $pagina = $this->actingAs($usuario)->get(route('tramite.validacion', ['escuela' => $escuela->id]));
        $pagina->assertOk();
        $html = $pagina->getContent();
        preg_match('/name="csrf-token" content="([^"]+)"/', $html, $csrf);
        preg_match('/wire:snapshot="([^"]*)"/', $html, $snapshot);
        $this->assertNotEmpty($csrf);
        $this->assertNotEmpty($snapshot);
        $cookie = null;
        foreach ($pagina->headers->getCookies() as $galleta) {
            if ($galleta->getName() === config('session.cookie')) {
                $cookie = $galleta->getValue();
            }
        }
        $this->assertNotNull($cookie);

        $enviar = fn () => $this->withCookie(config('session.cookie'), $cookie)
            ->withHeaders(['X-Livewire' => 'true', 'X-CSRF-TOKEN' => $csrf[1], 'Accept' => 'application/json'])
            ->postJson('/livewire/update', ['components' => [[
                'snapshot' => html_entity_decode($snapshot[1]),
                'updates' => [],
                'calls' => [['path' => '', 'method' => 'enviar', 'params' => []]],
            ]]]);

        $enviar()->assertOk();
        $enviar()->assertForbidden();

        $this->assertSame(1, DB::table('historial_estados_expediente')->count());
    }

    /** ADR-015: a responsable del nivel never holds update on the escuela, so neither the page nor its action is reachable. */
    public function test_un_responsable_del_nivel_no_puede_enviar(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $responsable = $this->responsableDe($nivel);

        $this->actingAs($responsable)->get(route('tramite.validacion', ['escuela' => $escuela->id]))->assertForbidden();

        // Replaying the owner's snapshot under the responsable's session: Livewire re-applies can:update,escuela.
        [$csrf, $snapshot] = $this->paginaDeValidacion($escuela, $escuela->solicitante->user);
        $this->actingAs($responsable)
            ->withHeaders(['X-Livewire' => 'true', 'X-CSRF-TOKEN' => $csrf, 'Accept' => 'application/json'])
            ->postJson('/livewire/update', ['components' => [[
                'snapshot' => $snapshot,
                'updates' => [],
                'calls' => [['path' => '', 'method' => 'enviar', 'params' => []]],
            ]]])
            ->assertForbidden();

        $this->assertSame('en_captura', $this->estadoDe($nivel));
        $this->assertSame(0, DB::table('historial_estados_expediente')->count());
    }

    public function test_el_resumen_de_un_tramite_enviado_no_ofrece_ninguna_accion(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);

        // Non-vacuity: before the send at least one completed section offers "revisar".
        $antes = app(ResumenTramite::class)->paraEscuela($escuela->id);
        $this->assertContains('revisar', array_map(fn ($s) => $s->accion, $antes->niveles[0]->secciones));
        $this->assertFalse($antes->enviado);
        $this->assertNull($antes->fechaEnvio);
        $this->assertNull($antes->reporteEnviadoId);

        $validacion = app(EnviarTramite::class)->ejecutar($escuela->id, (int) $escuela->solicitante->user->id);
        $resumen = app(ResumenTramite::class)->paraEscuela($escuela->id);

        $this->assertTrue($resumen->enviado);
        $this->assertSame(now()->toDateString(), $resumen->fechaEnvio?->format('Y-m-d'));
        $this->assertSame($validacion->evaluacionId, $resumen->reporteEnviadoId);
        $this->assertFalse($resumen->puedeEliminar);
        $this->assertNull($resumen->siguiente());
        foreach ([...$resumen->generales, ...$resumen->niveles[0]->secciones] as $seccion) {
            $this->assertNull($seccion->accion, $seccion->clave);
            $this->assertNull($seccion->href, $seccion->clave);
        }
    }

    /** ADR-015: the restricted DTO a responsable gets carries the same send state and loses every action. */
    public function test_el_resumen_restringido_del_responsable_tambien_queda_enviado_y_sin_acciones(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $responsable = $this->responsableDe($nivel);

        $antes = app(ResumenTramite::class)->paraEscuela($escuela->id, $responsable->id);
        $this->assertSame([], $antes->generales);
        $this->assertContains('revisar', array_map(fn ($s) => $s->accion, $antes->niveles[0]->secciones));
        $this->assertFalse($antes->enviado);

        $validacion = app(EnviarTramite::class)->ejecutar($escuela->id, (int) $escuela->solicitante->user->id);
        $resumen = app(ResumenTramite::class)->paraEscuela($escuela->id, $responsable->id);

        $this->assertTrue($resumen->enviado);
        $this->assertSame(now()->toDateString(), $resumen->fechaEnvio?->format('Y-m-d'));
        $this->assertSame($validacion->evaluacionId, $resumen->reporteEnviadoId);
        foreach ($resumen->niveles[0]->secciones as $seccion) {
            $this->assertNull($seccion->accion, $seccion->clave);
            $this->assertNull($seccion->href, $seccion->clave);
        }
    }

    public function test_la_pagina_del_resumen_tras_el_envio_muestra_el_estado_y_ningun_enlace_al_asistente(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $usuario = $escuela->solicitante->user;
        $validacion = app(EnviarTramite::class)->ejecutar($escuela->id, (int) $usuario->id);

        $this->actingAs($usuario)->get(route('tramite.resumen', ['escuela' => $escuela->id]))
            ->assertOk()
            ->assertSee('Enviado el '.now()->format('d/m/Y').' — en revisión por SEDEQ')
            ->assertSee('Descargar el reporte de validación enviado')
            ->assertSee(route('tramite.validacion.reporte', ['escuela' => $escuela->id, 'evaluacion' => $validacion->evaluacionId]), false)
            ->assertDontSee('Ir a la validación final')
            ->assertDontSee('href="'.route('tramite.validacion', ['escuela' => $escuela->id]).'"', false)
            ->assertDontSee('href="'.route('tramite.paso2-documentos', ['escuela' => $escuela->id]).'"', false)
            ->assertDontSee('href="'.route('tramite.paso2-nivel-documentos', ['escuelaNivel' => $nivel->id]).'"', false)
            ->assertDontSee('href="'.route('tramite.paso3-plantilla', ['escuelaNivel' => $nivel->id]).'"', false)
            ->assertDontSee('Eliminar trámite');
    }

    /**
     * The stored report stays downloadable through `view` (owner only today, EscuelaPolicy).
     * A responsable sees the state but no link: `view` is not widened for them (403 as before).
     */
    public function test_el_reporte_enviado_se_descarga_por_view_y_el_responsable_no_lo_ve(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $responsable = $this->responsableDe($nivel);
        $validacion = app(EnviarTramite::class)->ejecutar($escuela->id, (int) $escuela->solicitante->user->id);
        $reporte = route('tramite.validacion.reporte', ['escuela' => $escuela->id, 'evaluacion' => $validacion->evaluacionId]);

        $this->actingAs($escuela->solicitante->user)->get($reporte)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($responsable)->get($reporte)->assertForbidden();
        $this->actingAs($responsable)->get(route('tramite.resumen', ['escuela' => $escuela->id]))
            ->assertOk()
            ->assertSee('Enviado el '.now()->format('d/m/Y').' — en revisión por SEDEQ')
            ->assertDontSee('Descargar el reporte de validación enviado')
            ->assertDontSee($reporte, false)
            ->assertDontSee('href="'.route('tramite.paso3-plantilla', ['escuelaNivel' => $nivel->id]).'"', false);
    }

    /** Review M1: a vigencia that lapses after the send must not reopen the sent trámite's sections. */
    public function test_un_dictamen_que_vence_despues_del_envio_no_regresa_las_secciones(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $usuario = $escuela->solicitante->user;
        app(EnviarTramite::class)->ejecutar($escuela->id, (int) $usuario->id);
        DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->update(['fecha_vigencia' => now()->subDay()->toDateString()]);

        $resumen = app(ResumenTramite::class)->paraEscuela($escuela->id);
        $this->assertTrue($resumen->completo);
        foreach ([...$resumen->generales, ...$resumen->niveles[0]->secciones] as $seccion) {
            $this->assertNull($seccion->motivoBloqueo, $seccion->clave);
            $this->assertContains($seccion->estado, ['completado', 'no_disponible', 'no_aplica'], $seccion->clave);
        }

        $this->actingAs($usuario)->get(route('tramite.resumen', ['escuela' => $escuela->id]))
            ->assertOk()
            ->assertDontSee('Completa primero');
        $this->actingAs($usuario)->get(route('tramite.index'))
            ->assertOk()
            ->assertSee('En revisión por SEDEQ')
            ->assertSee('Ver trámite')
            ->assertDontSee('Continuar<span', false);
    }

    public function test_mis_tramites_muestra_en_revision(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        app(EnviarTramite::class)->ejecutar($escuela->id, (int) $escuela->solicitante->user->id);

        $this->actingAs($escuela->solicitante->user)->get(route('tramite.index'))
            ->assertOk()
            ->assertSee('En revisión por SEDEQ')
            ->assertDontSee('Captura inicial completa');
    }

    public function test_mis_niveles_del_responsable_muestra_en_revision(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $responsable = $this->responsableDe($nivel);

        $this->assertFalse(app(ListarNivelesDelResponsable::class)->ejecutar($responsable->id)[0]['enviado']);
        $this->actingAs($responsable)->get(route('tramite.index'))->assertOk()->assertDontSee('En revisión por SEDEQ');

        app(EnviarTramite::class)->ejecutar($escuela->id, (int) $escuela->solicitante->user->id);

        $this->actingAs($responsable)->get(route('tramite.index'))
            ->assertOk()
            ->assertSee('Mis niveles asignados')
            ->assertSee('En revisión por SEDEQ');
    }
}
