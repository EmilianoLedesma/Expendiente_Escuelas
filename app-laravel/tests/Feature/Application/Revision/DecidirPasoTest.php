<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\AsignarTramite;
use App\Application\Revision\DecidirPaso;
use App\Application\Revision\PasosRevision;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_cambiar_el_ultimo_rechazo_a_aceptado_aprueba_desde_con_observaciones(): void
    {
        $nivel = $this->nivelEnRevision();
        $pasos = PasosRevision::paraNivel('primaria');
        $this->aceptar($nivel, array_slice($pasos, 0, 9));
        $this->decidir($this->sup->id, $nivel, $pasos[9], 'rechazado', 'Falta documento');

        $this->decidir($this->sup->id, $nivel, $pasos[9]);

        $this->assertSame('aprobado', $this->claveEstado($nivel));
        $this->assertSame(2, $this->filasEstado($nivel));
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
}
