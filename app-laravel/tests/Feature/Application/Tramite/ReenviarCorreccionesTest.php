<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\AsignarTramite;
use App\Application\Revision\DecidirPaso;
use App\Application\Revision\PasosRevision;
use App\Application\Tramite\ReenviarCorrecciones;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\EvaluacionValidacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\Concerns\ConTramiteDevuelto;
use Tests\TestCase;

/** HUD devolución, Task 10: el dueño reenvía las correcciones de una ronda cerrada (spec 5.5). */
class ReenviarCorreccionesTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use ConTramiteDevuelto;
    use RefreshDatabase;

    private function reenviar(Escuela $escuela): void
    {
        app(ReenviarCorrecciones::class)->ejecutar($escuela->id, (int) $escuela->solicitante->user->id);
    }

    private function estadoDe(EscuelaNivel $nivel): string
    {
        return (string) DB::table('estados_expediente')->where('id', $nivel->fresh()->estado_id)->value('clave');
    }

    /** Trámite capturado completo (primaria) y devuelto con el plan de estudios rechazado y el plantel aceptado. */
    private function devuelto(): array
    {
        $escuela = $this->crearEscuelaConPlantel();
        $primaria = $this->completarTramite($escuela);
        $this->devolverTramite($escuela, [$primaria->id => ['plan_estudios' => 'Falta la modalidad']], [$primaria->id => ['plantel', 'matricula']]);

        return [$escuela, $primaria];
    }

    /** @param callable(): mixed $accion */
    private function rechaza(callable $accion, string $fragmento): void
    {
        try {
            $accion();
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertStringContainsString($fragmento, $e->getMessage());
        }
    }

    private function regla(string $nivelClave, string $clave, string $concepto): void
    {
        DB::table('reglas_validacion')->insert([
            'clave' => $clave, 'nivel_educativo_id' => DB::table('niveles_educativos')->where('clave', $nivelClave)->value('id'),
            'tipo_regla' => 'superficie', 'tipo_calculo' => 'ratio_por_alumno', 'ambito' => 'aula', 'redondeo' => 'na',
            'concepto' => $concepto, 'valor_numerico' => 0.90, 'unidad' => 'prueba', 'fuente' => 'prueba',
        ]);
    }

    public function test_reenvia_y_deja_en_revision_solo_los_niveles_devueltos(): void
    {
        [$escuela, $primaria] = $this->devuelto();
        $secundaria = $this->nivelEn($escuela, 'secundaria', 'aprobado');
        Log::spy();

        $this->reenviar($escuela);

        $this->assertSame('en_revision', $this->estadoDe($primaria));
        $this->assertSame('aprobado', $this->estadoDe($secundaria));
        $historial = DB::table('historial_estados_expediente')->where('escuela_nivel_id', $primaria->id)->sole();
        $this->assertSame('Correcciones reenviadas por el solicitante', $historial->comentario);
        $this->assertSame((int) $escuela->solicitante->user->id, (int) $historial->usuario_sedeq_id);
        $this->assertSame((int) DB::table('estados_expediente')->where('clave', 'en_revision')->value('id'), (int) $historial->estado_id);
        $this->assertSame(0, DB::table('historial_estados_expediente')->where('escuela_nivel_id', $secundaria->id)->count());
        // La línea base es la del cierre de la ronda: el reenvío no guarda evaluación.
        $this->assertSame(0, EvaluacionValidacion::count());
        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $mensaje, array $contexto) => $mensaje === 'Correcciones reenviadas a SEDEQ' && $contexto['escuela_id'] === $escuela->id && $contexto['niveles'] === 1)
            ->once();
    }

    public function test_conserva_decisiones_aceptadas_y_borra_las_rechazadas(): void
    {
        [$escuela, $primaria] = $this->devuelto();

        $this->reenviar($escuela);

        $this->assertEqualsCanonicalizing(
            ['matricula' => 'aceptado', 'plantel' => 'aceptado'],
            DB::table('decisiones_revision')->where('escuela_nivel_id', $primaria->id)->pluck('estado', 'paso_clave')->all(),
        );
    }

    public function test_recalcula_el_progreso(): void
    {
        [$escuela, $primaria] = $this->devuelto();
        // El revisor lo dejó con todo decidido: 10 de 10.
        DB::table('progreso_revision')->insert(['escuela_nivel_id' => $primaria->id, 'secciones_total' => 10, 'secciones_revisadas' => 10]);

        $this->reenviar($escuela);

        $p = DB::table('progreso_revision')->where('escuela_nivel_id', $primaria->id)->sole();
        $this->assertSame(count(PasosRevision::paraNivel('primaria')), (int) $p->secciones_total);
        $this->assertSame(2, (int) $p->secciones_revisadas);
    }

    public function test_no_reenvia_con_la_ronda_abierta(): void
    {
        [$escuela, $primaria] = $this->devuelto();
        $this->nivelEn($escuela, 'secundaria', 'en_revision');

        $this->rechaza(fn () => $this->reenviar($escuela), 'observaciones');

        $this->assertSame('con_observaciones', $this->estadoDe($primaria));
        $this->assertSame(3, DB::table('decisiones_revision')->where('escuela_nivel_id', $primaria->id)->count());
        $this->assertSame(0, DB::table('historial_estados_expediente')->count());
    }

    public function test_no_reenvia_si_falta_completar_un_paso_reabierto(): void
    {
        [$escuela, $primaria] = $this->devuelto();
        DB::table('escuela_nivel_pasos')->where('escuela_nivel_id', $primaria->id)
            ->where('paso_captura_id', DB::table('pasos_captura')->where('clave', 'plan_estudios')->value('id'))
            ->update(['estado' => 'en_progreso']);

        $this->rechaza(fn () => $this->reenviar($escuela), 'Plan de estudios');

        $this->assertSame('con_observaciones', $this->estadoDe($primaria));
        $this->assertSame('rechazado', DB::table('decisiones_revision')->where('escuela_nivel_id', $primaria->id)->where('paso_clave', 'plan_estudios')->value('estado'));
    }

    public function test_no_reenvia_si_el_nivel_devuelto_tiene_un_bloqueante(): void
    {
        [$escuela, $primaria] = $this->devuelto();
        $this->regla('primaria', 'primaria.superficie.aulas', 'Superficie de aulas');

        $this->rechaza(fn () => $this->reenviar($escuela), 'Capacidad instalada · Primaria: Superficie de aulas');

        $this->assertSame('con_observaciones', $this->estadoDe($primaria));
    }

    public function test_un_no_cumple_de_un_nivel_aprobado_no_bloquea(): void
    {
        [$escuela, $primaria] = $this->devuelto();
        $secundaria = $this->nivelEn($escuela, 'secundaria', 'aprobado');
        $this->regla('secundaria', 'secundaria.superficie.aulas', 'Superficie de aulas');
        // Precondición del caso: el hermano aprobado sí tiene un bloqueante en la evaluación completa.
        $completa = app(EjecutarValidacionFinal::class)->evaluar($escuela->id, exigirCompleto: false);
        $this->assertContains('Capacidad instalada · Secundaria: Superficie de aulas', $completa->motivosBloqueo());

        $this->reenviar($escuela);

        $this->assertSame('en_revision', $this->estadoDe($primaria));
        $this->assertSame('aprobado', $this->estadoDe($secundaria));
    }

    public function test_responsable_por_nivel_no_puede_reenviar(): void
    {
        [$escuela, $primaria] = $this->devuelto();
        $responsable = $this->responsableDe($primaria);

        $this->assertTrue($escuela->solicitante->user->can('reenviar', $escuela));
        $this->assertFalse($responsable->can('reenviar', $escuela));
        $this->assertFalse($this->supervisor()->can('reenviar', $escuela));
    }

    public function test_mantiene_la_asignacion_y_el_historial_de_decisiones(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $primaria = $this->completarTramite($escuela);
        $sup = $this->supervisor();
        $revisor = $this->revisor();
        DB::table('escuela_niveles')->where('id', $primaria->id)->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);
        app(AsignarTramite::class)->ejecutar($sup->id, $primaria->id, $revisor->id);
        $pasos = PasosRevision::paraNivel('primaria');
        app(DecidirPaso::class)->ejecutar($revisor->id, $primaria->id, $pasos[0], 'rechazado', 'Teléfono inválido');
        foreach (array_slice($pasos, 1) as $paso) {
            app(DecidirPaso::class)->ejecutar($revisor->id, $primaria->id, $paso, 'aceptado');
        }
        $this->assertSame('con_observaciones', $this->estadoDe($primaria));
        $asignaciones = DB::table('asignaciones_revision')->get()->toArray();
        $historial = DB::table('historial_decisiones_revision')->orderBy('id')->get()->toArray();

        $this->reenviar($escuela);

        $this->assertEquals($asignaciones, DB::table('asignaciones_revision')->get()->toArray());
        $this->assertEquals($historial, DB::table('historial_decisiones_revision')->orderBy('id')->get()->toArray());
        $this->assertSame(count($pasos) - 1, DB::table('decisiones_revision')->where('escuela_nivel_id', $primaria->id)->count());
    }

    public function test_bloquea_los_niveles_y_luego_la_escuela(): void
    {
        [$escuela] = $this->devuelto();
        $this->nivelEn($escuela, 'secundaria', 'aprobado');
        $bloqueos = [];
        DB::listen(function ($q) use (&$bloqueos) {
            if (str_contains($q->sql, 'for update')) {
                $bloqueos[] = str_contains($q->sql, '"escuela_niveles"') ? 'escuela_niveles' : (str_contains($q->sql, '"escuelas"') ? 'escuelas' : $q->sql);
            }
        });

        $this->reenviar($escuela);

        // Todos los niveles (por id) antes que la escuela, igual que EnviarTramite y DecidirPaso: nunca escuela -> nivel.
        $this->assertSame(['escuela_niveles', 'escuelas'], array_slice($bloqueos, 0, 2));
        $this->assertNotContains('escuelas', array_slice($bloqueos, 2), 'la escuela se bloquea una sola vez');
    }

    public function test_reenvio_concurrente_con_decision_del_revisor_serializa(): void
    {
        [$escuela, $primaria] = $this->devuelto();
        $sup = $this->supervisor();

        // Antes del reenvío el nivel está con el solicitante: la decisión se rechaza.
        try {
            app(DecidirPaso::class)->ejecutar($sup->id, $primaria->id, 'plan_estudios', 'aceptado');
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida) {
        }

        $this->reenviar($escuela);

        // Quien decide después del reenvío (tras esperar su bloqueo del nivel) relee en_revision y decide.
        app(DecidirPaso::class)->ejecutar($sup->id, $primaria->id, 'plan_estudios', 'aceptado');
        $this->assertSame('aceptado', DB::table('decisiones_revision')->where('escuela_nivel_id', $primaria->id)->where('paso_clave', 'plan_estudios')->value('estado'));
        $this->assertSame(3, (int) DB::table('progreso_revision')->where('escuela_nivel_id', $primaria->id)->value('secciones_revisadas'));

        // Un segundo reenvío ya no encuentra la ronda cerrada.
        $this->rechaza(fn () => $this->reenviar($escuela), 'observaciones');
    }
}
