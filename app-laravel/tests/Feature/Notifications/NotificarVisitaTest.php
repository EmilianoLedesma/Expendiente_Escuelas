<?php

namespace Tests\Feature\Notifications;

use App\Application\Tramite\ResumenTramite;
use App\Application\Visita\ProgramarVisita;
use App\Application\Visita\RegistrarActaVisita;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use App\Notifications\ResultadoDeVisita;
use App\Notifications\VisitaProgramadaAviso;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class NotificarVisitaTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private Solicitante $dueno;

    private EscuelaNivel $nivel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dueno = Solicitante::factory()->create();
        $this->nivel = $this->nivelDe($this->dueno, 'primaria', 'aprobado');
    }

    private function programar(): int
    {
        return app(ProgramarVisita::class)->ejecutar($this->supervisor(), $this->nivel->id, now()->addDays(3), 'Ana');
    }

    private function visitaProgramada(): int
    {
        return DB::table('visitas_inspeccion')->insertGetId([
            'escuela_nivel_id' => $this->nivel->id, 'numero' => 1, 'programada_para' => now()->subDays(3),
            'inspector' => 'Y', 'estado' => 'programada', 'programada_por' => $this->supervisor()->id,
        ]);
    }

    public function test_programar_avisa_solo_al_dueno(): void
    {
        Notification::fake();
        $responsable = $this->responsableDe($this->nivel);
        $revisor = $this->revisor();

        $this->programar();

        Notification::assertSentToTimes($this->dueno->user, VisitaProgramadaAviso::class, 1);
        Notification::assertNotSentTo([$responsable, $revisor], VisitaProgramadaAviso::class);
        Notification::assertSentTo($this->dueno->user, VisitaProgramadaAviso::class, fn ($n) => $n->domicilio === ResumenTramite::domicilio($this->nivel->escuela->plantel));
        Notification::assertCount(1);
    }

    public function test_acta_cumple_avisa_solo_al_dueno(): void
    {
        Notification::fake();
        $responsable = $this->responsableDe($this->nivel);
        $id = $this->visitaProgramada();
        DB::table('escuela_niveles')->where('id', $this->nivel->id)
            ->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'visita_programada')->value('id')]);

        app(RegistrarActaVisita::class)->ejecutar($this->supervisor(), $id, 'cumple', now()->subDay());

        Notification::assertSentToTimes($this->dueno->user, ResultadoDeVisita::class, 1);
        Notification::assertNotSentTo($responsable, ResultadoDeVisita::class);
        Notification::assertCount(1);
    }

    public function test_acta_con_observaciones_avisa_solo_al_dueno(): void
    {
        Notification::fake();
        $responsable = $this->responsableDe($this->nivel);
        $id = $this->visitaProgramada();
        DB::table('escuela_niveles')->where('id', $this->nivel->id)
            ->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'visita_programada')->value('id')]);

        app(RegistrarActaVisita::class)->ejecutar($this->supervisor(), $id, 'con_observaciones', new \DateTimeImmutable('2026-11-18'), ['Uno', 'Dos']);

        Notification::assertSentToTimes($this->dueno->user, ResultadoDeVisita::class, 1);
        Notification::assertNotSentTo($responsable, ResultadoDeVisita::class);
        Notification::assertCount(1);
    }

    public function test_ambas_notificaciones_son_encoladas(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new VisitaProgramadaAviso(1, null, 'Primaria', now(), 'Calle 1, C.P. 76000', 1));
        $this->assertInstanceOf(ShouldQueue::class, new ResultadoDeVisita(1, null, 'Primaria', 'cumple', 0, null, 1));
    }

    public function test_correo_programada_lleva_fecha_domicilio_y_avisos_sin_sedeq(): void
    {
        $n = new VisitaProgramadaAviso($this->nivel->escuela_id, 'Colegio Uno', 'Primaria', new \DateTimeImmutable('2026-12-03 10:30'), 'Calle Roble #5, Centro, Querétaro, C.P. 76000', $this->nivel->id);

        $correo = $n->toMail($this->dueno->user);
        $html = (string) $correo->render();

        $this->assertStringContainsString('visita de inspección', $correo->subject);
        $this->assertStringContainsString('03/12/2026 10:30', $html);
        $this->assertStringContainsString('domicilio de tu plantel', $html);
        $this->assertStringContainsString('Calle Roble #5, Centro, Querétaro, C.P. 76000', $html);
        $this->assertStringNotContainsString('Lugar:', $html);
        $this->assertStringContainsString('Primaria', $html);
        $this->assertStringContainsString('equipada', $html);
        $this->assertStringContainsString('originales', $html);
        $this->assertStringContainsString('acuerdo', $html);
        $this->assertStringContainsString('autoridad educativa', $html);
        $this->assertStringNotContainsString('SEDEQ', $html.$correo->subject);
        $this->assertStringNotContainsString('RVOE', $html);
    }

    public function test_correo_resultado_cumple(): void
    {
        $n = new ResultadoDeVisita($this->nivel->escuela_id, null, 'Primaria', 'cumple', 0, null, $this->nivel->id);

        $html = (string) $n->toMail($this->dueno->user)->render();

        $this->assertStringContainsString('sin observaciones', $html);
        $this->assertStringContainsString('resolución', $html);
        $this->assertStringContainsString('acuerdo', $html);
        $this->assertStringNotContainsString('SEDEQ', $html);
        $this->assertStringNotContainsString('RVOE', $html);
    }

    public function test_correo_resultado_con_observaciones_lleva_cantidad_y_plazo(): void
    {
        $n = new ResultadoDeVisita($this->nivel->escuela_id, null, 'Primaria', 'con_observaciones', 3, new \DateTimeImmutable('2026-11-25'), $this->nivel->id);

        $html = (string) $n->toMail($this->dueno->user)->render();

        $this->assertStringContainsString('3 observaciones', $html);
        $this->assertStringContainsString('25/11/2026', $html);
        $this->assertStringContainsString('5 días hábiles', $html);
        $this->assertStringNotContainsString('SEDEQ', $html);
        $this->assertStringNotContainsString('RVOE', $html);
    }

    public function test_los_correos_enlazan_a_la_pagina_de_la_visita(): void
    {
        $esperado = route('tramite.visita', ['escuelaNivel' => $this->nivel->id]);
        $programada = new VisitaProgramadaAviso($this->nivel->escuela_id, null, 'Primaria', now(), 'X', $this->nivel->id);
        $resultado = new ResultadoDeVisita($this->nivel->escuela_id, null, 'Primaria', 'cumple', 0, null, $this->nivel->id);

        $this->assertStringContainsString($esperado, (string) $programada->toMail($this->dueno->user)->render());
        $this->assertStringContainsString($esperado, (string) $resultado->toMail($this->dueno->user)->render());
    }
}
