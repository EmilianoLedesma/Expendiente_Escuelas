<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\AsignarTramite;
use App\Application\Revision\DecidirPaso;
use App\Application\Revision\PasosRevision;
use App\Application\Revision\RondaCerrada;
use App\Application\Validaciones\DTO\ValidacionFinal;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class DecidirPasoTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private User $sup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = $this->supervisor();
    }

    private function decidir(int $actorId, EscuelaNivel $nivel, string $paso, string $decision = 'aceptado', ?string $motivo = null): void
    {
        app(DecidirPaso::class)->ejecutar($actorId, $nivel->id, $paso, $decision, $motivo);
    }

    private function claveEstado(EscuelaNivel $nivel): string
    {
        return (string) DB::table('estados_expediente')->where('id', DB::table('escuela_niveles')->where('id', $nivel->id)->value('estado_id'))->value('clave');
    }

    /** @param  list<string>  $pasos */
    private function aceptar(EscuelaNivel $nivel, array $pasos): void
    {
        foreach ($pasos as $paso) {
            $this->decidir($this->sup->id, $nivel, $paso);
        }
    }

    private function filasEstado(EscuelaNivel $nivel): int
    {
        return DB::table('historial_estados_expediente')->where('escuela_nivel_id', $nivel->id)->count();
    }

    private function nadaEscrito(): void
    {
        $this->assertSame(0, DB::table('decisiones_revision')->count());
        $this->assertSame(0, DB::table('historial_decisiones_revision')->count());
        $this->assertSame(0, DB::table('historial_estados_expediente')->count());
    }

    public function test_acepta_y_registra_actual_historial_y_progreso(): void
    {
        $nivel = $this->nivelEnRevision();

        $this->decidir($this->sup->id, $nivel, 'plantel');

        $this->assertSame('aceptado', DB::table('decisiones_revision')->where('escuela_nivel_id', $nivel->id)->where('paso_clave', 'plantel')->value('estado'));
        $this->assertSame(1, DB::table('historial_decisiones_revision')->where('escuela_nivel_id', $nivel->id)->count());
        $p = DB::table('progreso_revision')->where('escuela_nivel_id', $nivel->id)->first();
        $this->assertSame(1, (int) $p->secciones_revisadas);
        $this->assertSame(10, (int) $p->secciones_total);
        $this->assertSame('en_revision', $this->claveEstado($nivel));
    }

    public function test_rechazo_sin_motivo_o_con_espacios_lanza_datos_invalidos_y_no_escribe(): void
    {
        $nivel = $this->nivelEnRevision();

        foreach ([null, '', '   '] as $motivo) {
            try {
                $this->decidir($this->sup->id, $nivel, 'plantel', 'rechazado', $motivo);
                $this->fail('Debió lanzar DatosInvalidos');
            } catch (DatosInvalidos $e) {
                $this->assertSame(['motivo' => 'Escribe el motivo del rechazo.'], $e->errores);
            }
        }
        $this->assertSame(0, DB::table('decisiones_revision')->count());
        $this->assertSame(0, DB::table('historial_decisiones_revision')->count());
    }

    public function test_paso_o_decision_invalidos_son_datos_invalidos(): void
    {
        $nivel = $this->nivelEnRevision();

        foreach ([['inexistente', 'aceptado'], ['plantel', 'quizas']] as [$paso, $decision]) {
            try {
                $this->decidir($this->sup->id, $nivel, $paso, $decision);
                $this->fail('Debió lanzar DatosInvalidos');
            } catch (DatosInvalidos $e) {
                $this->assertArrayHasKey('pasoClave', $e->errores);
            }
        }
    }

    public function test_revisor_no_asignado_no_puede_decidir(): void
    {
        $nivel = $this->nivelEnRevision();
        $revisor = $this->revisor();

        try {
            $this->decidir($revisor->id, $nivel, 'plantel');
            $this->fail('Debió lanzar AuthorizationException');
        } catch (AuthorizationException) {
            $this->nadaEscrito();
        }
    }

    public function test_admin_sedeq_sin_rol_operativo_es_rechazado(): void
    {
        $nivel = $this->nivelEnRevision();
        $admin = User::factory()->create()->assignRole('sedeq');

        try {
            $this->decidir($admin->id, $nivel, 'plantel');
            $this->fail('Debió lanzar AuthorizationException');
        } catch (AuthorizationException) {
            $this->nadaEscrito();
        }
    }

    public function test_revisor_asignado_decide(): void
    {
        $nivel = $this->nivelEnRevision();
        $revisor = $this->revisor();
        app(AsignarTramite::class)->ejecutar($this->sup->id, $nivel->id, $revisor->id);

        $this->decidir($revisor->id, $nivel, 'plantel');

        $this->assertSame($revisor->id, (int) DB::table('decisiones_revision')->where('escuela_nivel_id', $nivel->id)->value('decidido_por'));
    }

    public function test_supervisor_decide_en_cualquier_nivel_en_revision(): void
    {
        $nivel = $this->nivelEnRevision();

        $this->decidir($this->sup->id, $nivel, 'inmueble');

        $this->assertSame(1, DB::table('decisiones_revision')->where('escuela_nivel_id', $nivel->id)->count());
    }

    public function test_nivel_en_captura_es_invisible_y_lanza_autorizacion(): void
    {
        $nivel = $this->nivelEnRevision('en_captura');

        try {
            $this->decidir($this->sup->id, $nivel, 'plantel');
            $this->fail('Debió lanzar AuthorizationException');
        } catch (AuthorizationException) {
            $this->nadaEscrito();
        }
    }

    public function test_nivel_aprobado_rechaza_la_decision(): void
    {
        $nivel = $this->nivelEnRevision('aprobado');

        try {
            $this->decidir($this->sup->id, $nivel, 'plantel');
            $this->fail('Debió lanzar PrecondicionIncumplida');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame('en_revision', $e->etapaFaltante);
        }
        $this->assertSame(0, DB::table('decisiones_revision')->count());
    }

    public function test_mobiliario_en_un_nivel_que_no_es_inicial_es_dato_invalido(): void
    {
        $nivel = $this->nivelEnRevision();

        try {
            $this->decidir($this->sup->id, $nivel, 'mobiliario');
            $this->fail('Debió lanzar DatosInvalidos');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('pasoClave', $e->errores);
        }
    }

    public function test_ultimo_paso_aceptado_aprueba_el_nivel(): void
    {
        $nivel = $this->nivelEnRevision();
        $pasos = PasosRevision::paraNivel('primaria');

        $this->aceptar($nivel, array_slice($pasos, 0, 9));
        $this->assertSame('en_revision', $this->claveEstado($nivel));
        $this->decidir($this->sup->id, $nivel, $pasos[9]);

        $this->assertSame('aprobado', $this->claveEstado($nivel));
        $this->assertSame(1, $this->filasEstado($nivel));
        $this->assertSame('Todos los pasos aceptados', DB::table('historial_estados_expediente')->where('escuela_nivel_id', $nivel->id)->value('comentario'));
    }

    public function test_todos_decididos_con_un_rechazo_pasa_a_con_observaciones(): void
    {
        $nivel = $this->nivelEnRevision();
        $pasos = PasosRevision::paraNivel('primaria');

        $this->aceptar($nivel, array_slice($pasos, 0, 9));
        $this->decidir($this->sup->id, $nivel, $pasos[9], 'rechazado', 'Falta documento');

        $this->assertSame('con_observaciones', $this->claveEstado($nivel));
        $this->assertSame(1, $this->filasEstado($nivel));
    }

    public function test_dos_decisiones_sobre_el_mismo_paso_dejan_la_ultima_y_dos_filas_de_historial(): void
    {
        $nivel = $this->nivelEnRevision();

        $revisor = $this->revisor();
        app(AsignarTramite::class)->ejecutar($this->sup->id, $nivel->id, $revisor->id);
        $this->decidir($revisor->id, $nivel, 'plantel', 'rechazado', 'Mal');
        $this->decidir($this->sup->id, $nivel, 'plantel');

        $this->assertSame(['aceptado'], DB::table('decisiones_revision')->where('escuela_nivel_id', $nivel->id)->pluck('estado')->all());
        $this->assertSame($this->sup->id, (int) DB::table('decisiones_revision')->where('escuela_nivel_id', $nivel->id)->value('decidido_por'));
        $this->assertSame(2, DB::table('historial_decisiones_revision')->where('escuela_nivel_id', $nivel->id)->count());
        $this->assertSame(1, (int) DB::table('progreso_revision')->where('escuela_nivel_id', $nivel->id)->value('secciones_revisadas'));
    }

    public function test_inicial_necesita_11_pasos_para_aprobarse(): void
    {
        $nivel = $this->nivelDe(Solicitante::factory()->create(), 'inicial', 'en_revision');
        $pasos = PasosRevision::paraNivel('inicial');
        $this->assertCount(11, $pasos);

        $this->aceptar($nivel, array_slice($pasos, 0, 10));
        $this->assertSame('en_revision', $this->claveEstado($nivel));
        $this->decidir($this->sup->id, $nivel, $pasos[10]);

        $this->assertSame('aprobado', $this->claveEstado($nivel));
        $this->assertSame(11, (int) DB::table('progreso_revision')->where('escuela_nivel_id', $nivel->id)->value('secciones_total'));
    }

    /** Decide todos los pasos del nivel: el primero rechazado, el resto aceptados. */
    private function devolver(EscuelaNivel $nivel): void
    {
        $pasos = PasosRevision::paraNivel('primaria');
        $this->decidir($this->sup->id, $nivel, $pasos[0], 'rechazado', 'Falta');
        $this->aceptar($nivel, array_slice($pasos, 1));
    }

    public function test_no_se_decide_sobre_un_nivel_con_observaciones(): void
    {
        $nivel = $this->nivelEnRevision('con_observaciones');

        try {
            $this->decidir($this->sup->id, $nivel, 'plantel');
            $this->fail('Debió lanzar PrecondicionIncumplida');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame('en_revision', $e->etapaFaltante);
        }
        $this->assertSame(0, DB::table('decisiones_revision')->count());
    }

    public function test_cierra_la_ronda_cuando_el_ultimo_nivel_se_decide(): void
    {
        Event::fake([RondaCerrada::class]);
        $a = $this->nivelEnRevision();
        $b = $this->nivelEn($a->escuela, 'secundaria', 'en_revision');
        $pasosB = PasosRevision::paraNivel('secundaria');

        $this->devolver($a);
        Event::assertNotDispatched(RondaCerrada::class);
        $this->aceptar($b, array_slice($pasosB, 0, -1));
        $this->decidir($this->sup->id, $b, end($pasosB));

        Event::assertDispatchedTimes(RondaCerrada::class, 1);
        Event::assertDispatched(RondaCerrada::class, fn ($e) => $e->escuelaId === $a->escuela_id);
    }

    public function test_no_cierra_con_un_nivel_aun_en_revision(): void
    {
        Event::fake([RondaCerrada::class]);
        $a = $this->nivelEnRevision();
        $this->nivelEn($a->escuela, 'secundaria', 'en_revision');

        $this->devolver($a);

        $this->assertSame('con_observaciones', $this->claveEstado($a));
        Event::assertNotDispatched(RondaCerrada::class);
    }

    public function test_escuela_de_un_solo_nivel_cierra_al_devolver(): void
    {
        Event::fake([RondaCerrada::class]);
        $nivel = $this->nivelEnRevision();

        $this->devolver($nivel);

        Event::assertDispatchedTimes(RondaCerrada::class, 1);
    }

    public function test_aprobar_todo_no_cierra_la_ronda(): void
    {
        Event::fake([RondaCerrada::class]);
        $nivel = $this->nivelEnRevision();

        $this->aceptar($nivel, PasosRevision::paraNivel('primaria'));

        $this->assertSame('aprobado', $this->claveEstado($nivel));
        Event::assertNotDispatched(RondaCerrada::class);
    }

    public function test_guarda_una_evaluacion_como_linea_base_al_cerrar(): void
    {
        $nivel = $this->nivelEnRevision();
        $validacion = new ValidacionFinal(null, Carbon::now(), true, [], [], []);
        $mock = $this->mock(EjecutarValidacionFinal::class);
        $mock->shouldReceive('evaluar')->once()->with($nivel->escuela_id)->andReturn($validacion);
        $mock->shouldReceive('guardar')->once()->with($nivel->escuela_id, $validacion)->andReturn($validacion);

        $this->devolver($nivel);
    }

    public function test_no_guarda_linea_base_si_la_ronda_no_cierra(): void
    {
        $nivel = $this->nivelEnRevision();
        $this->nivelEn($nivel->escuela, 'secundaria', 'en_revision');
        $mock = $this->mock(EjecutarValidacionFinal::class);
        $mock->shouldNotReceive('evaluar');
        $mock->shouldNotReceive('guardar');

        $this->devolver($nivel);
    }

    public function test_si_falla_guardar_la_linea_base_la_decision_persiste(): void
    {
        Event::fake([RondaCerrada::class]);
        $nivel = $this->nivelEnRevision();
        $validacion = new ValidacionFinal(null, Carbon::now(), true, [], [], []);
        $mock = $this->mock(EjecutarValidacionFinal::class);
        $mock->shouldReceive('evaluar')->andReturn($validacion);
        $mock->shouldReceive('guardar')->andThrow(new RuntimeException('pdf roto'));

        $this->devolver($nivel);

        $this->assertSame('con_observaciones', $this->claveEstado($nivel));
        Event::assertDispatchedTimes(RondaCerrada::class, 1);
    }

    public function test_si_falla_evaluar_la_decision_persiste(): void
    {
        Event::fake([RondaCerrada::class]);
        $nivel = $this->nivelEnRevision();
        $mock = $this->mock(EjecutarValidacionFinal::class);
        $mock->shouldReceive('evaluar')->andThrow(new PrecondicionIncumplida('tramite', 'incompleto'));
        $mock->shouldNotReceive('guardar');

        $this->devolver($nivel);

        $this->assertSame('con_observaciones', $this->claveEstado($nivel));
        Event::assertDispatchedTimes(RondaCerrada::class, 1);
    }

    public function test_bloquea_la_escuela_despues_del_nivel(): void
    {
        $nivel = $this->nivelEnRevision();
        $bloqueos = [];
        DB::listen(function ($q) use (&$bloqueos) {
            if (str_contains($q->sql, 'for update')) {
                $bloqueos[] = str_contains($q->sql, '"escuela_niveles"') ? 'escuela_niveles' : (str_contains($q->sql, '"escuelas"') ? 'escuelas' : $q->sql);
            }
        });

        $this->decidir($this->sup->id, $nivel, 'plantel');

        $this->assertSame(['escuela_niveles', 'escuelas'], $bloqueos);
    }

    public function test_cierra_la_ronda_cuando_el_otro_nivel_ya_estaba_confirmado(): void
    {
        Event::fake([RondaCerrada::class]);
        $a = $this->nivelEnRevision();
        $this->nivelEn($a->escuela, 'secundaria', 'aprobado');

        $this->devolver($a);

        Event::assertDispatchedTimes(RondaCerrada::class, 1);
    }

    public function test_rollback_de_la_decision_no_despacha_el_evento(): void
    {
        Event::fake([RondaCerrada::class]);
        $nivel = $this->nivelEnRevision();
        $pasos = PasosRevision::paraNivel('primaria');
        $this->aceptar($nivel, array_slice($pasos, 1));

        try {
            DB::transaction(function () use ($nivel, $pasos) {
                $this->decidir($this->sup->id, $nivel, $pasos[0], 'rechazado', 'Falta');
                throw new RuntimeException('falla posterior');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame('en_revision', $this->claveEstado($nivel));
        Event::assertNotDispatched(RondaCerrada::class);
    }
}
