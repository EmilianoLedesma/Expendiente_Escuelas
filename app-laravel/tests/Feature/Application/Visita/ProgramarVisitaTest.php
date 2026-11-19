<?php

namespace Tests\Feature\Application\Visita;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Visita\ProgramarVisita;
use App\Application\Visita\VisitaProgramada;
use App\Models\VisitaInspeccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class ProgramarVisitaTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private function programar($actor, int $nivelId, ?\DateTimeInterface $fecha = null, string $inspector = 'Inspector Uno'): int
    {
        return app(ProgramarVisita::class)->ejecutar($actor, $nivelId, $fecha ?? now()->addWeek(), $inspector);
    }

    private function estadoDe(int $nivelId): string
    {
        return DB::table('escuela_niveles')->join('estados_expediente as e', 'e.id', '=', 'escuela_niveles.estado_id')
            ->where('escuela_niveles.id', $nivelId)->value('e.clave');
    }

    public function test_visits_table_has_no_lugar_column(): void
    {
        $this->assertFalse(Schema::hasColumn('visitas_inspeccion', 'lugar'));
    }

    public function test_revisor_forbidden(): void
    {
        $nivel = $this->nivelEnRevision('aprobado');

        $this->expectException(PrecondicionIncumplida::class);
        $this->programar($this->revisor(), $nivel->id);
    }

    public function test_nivel_en_revision_rejected(): void
    {
        $nivel = $this->nivelEnRevision('en_revision');

        $this->expectException(DatosInvalidos::class);
        $this->programar($this->supervisor(), $nivel->id);
    }

    public function test_aprobado_creates_visita_1_moves_nivel_and_writes_historial(): void
    {
        $nivel = $this->nivelEnRevision('aprobado');
        $sup = $this->supervisor();

        $id = $this->programar($sup, $nivel->id, now()->addDays(3), ' Ana Pérez ');

        $v = VisitaInspeccion::findOrFail($id);
        $this->assertSame(1, (int) $v->numero);
        $this->assertSame('programada', $v->estado);
        $this->assertSame('Ana Pérez', $v->inspector);
        $this->assertSame($sup->id, (int) $v->programada_por);
        $this->assertSame('visita_programada', $this->estadoDe($nivel->id));
        $estadoId = DB::table('estados_expediente')->where('clave', 'visita_programada')->value('id');
        $this->assertDatabaseHas('historial_estados_expediente', [
            'escuela_nivel_id' => $nivel->id, 'estado_id' => $estadoId, 'usuario_sedeq_id' => $sup->id,
        ]);
    }

    public function test_second_visit_from_en_subsanacion_gets_numero_2(): void
    {
        $nivel = $this->nivelEnRevision('en_subsanacion');
        $sup = $this->supervisor();
        DB::table('visitas_inspeccion')->insert([
            'escuela_nivel_id' => $nivel->id, 'numero' => 1, 'programada_para' => now()->subWeek(), 'inspector' => 'Y',
            'estado' => 'realizada', 'resultado' => 'con_observaciones', 'realizada_el' => now()->subDay()->toDateString(),
            'subsanar_hasta' => now()->addWeek()->toDateString(), 'programada_por' => $sup->id,
        ]);

        $id = $this->programar($sup, $nivel->id);

        $this->assertSame(2, (int) VisitaInspeccion::findOrFail($id)->numero);
        $this->assertSame('visita_programada', $this->estadoDe($nivel->id));
    }

    public function test_second_programada_blocked(): void
    {
        $nivel = $this->nivelEnRevision('aprobado');
        $sup = $this->supervisor();
        $this->programar($sup, $nivel->id);

        // El nivel ya está en visita_programada: lo rechaza la precondición de estado.
        $this->expectException(DatosInvalidos::class);
        $this->programar($sup, $nivel->id);
    }

    public function test_partial_index_violation_becomes_datos_invalidos(): void
    {
        $nivel = $this->nivelEnRevision('aprobado');
        $sup = $this->supervisor();
        // Visita programada colada con el nivel todavía en aprobado: solo el índice parcial lo detiene.
        DB::table('visitas_inspeccion')->insert([
            'escuela_nivel_id' => $nivel->id, 'numero' => 1, 'programada_para' => now()->addWeek(), 'inspector' => 'Y',
            'estado' => 'programada', 'programada_por' => $sup->id,
        ]);

        try {
            $this->programar($sup, $nivel->id);
            $this->fail('Debio rechazar');
        } catch (DatosInvalidos $e) {
            $this->assertStringContainsString('Ya hay una visita programada para este nivel.', $e->getMessage());
        }
        $this->assertSame('aprobado', $this->estadoDe($nivel->id));
    }

    public function test_event_dispatched_after_commit(): void
    {
        Event::fake([VisitaProgramada::class]);
        $nivel = $this->nivelEnRevision('aprobado');

        $id = $this->programar($this->supervisor(), $nivel->id);

        Event::assertDispatched(VisitaProgramada::class, fn ($e) => $e->visitaId === $id);
    }

    public function test_event_not_dispatched_on_failed_validation(): void
    {
        Event::fake([VisitaProgramada::class]);
        $nivel = $this->nivelEnRevision('aprobado');

        try {
            $this->programar($this->supervisor(), $nivel->id, now()->subDays(2));
        } catch (DatosInvalidos) {
        }

        Event::assertNotDispatched(VisitaProgramada::class);
        $this->assertSame('aprobado', $this->estadoDe($nivel->id));
    }

    public function test_past_date_rejected_but_today_allowed(): void
    {
        $nivel = $this->nivelEnRevision('aprobado');
        $sup = $this->supervisor();

        try {
            $this->programar($sup, $nivel->id, now()->subDay());
            $this->fail('Debio rechazar');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('fecha', $e->errores);
        }

        $this->assertGreaterThan(0, $this->programar($sup, $nivel->id, now()->startOfDay()));
    }

    public function test_blank_or_overlong_inspector_rejected(): void
    {
        $nivel = $this->nivelEnRevision('aprobado');
        $sup = $this->supervisor();

        foreach ([['  ', 'inspector'], [str_repeat('a', 151), 'inspector']] as [$insp, $campo]) {
            try {
                $this->programar($sup, $nivel->id, null, $insp);
                $this->fail("Debio rechazar {$campo}");
            } catch (DatosInvalidos $e) {
                $this->assertArrayHasKey($campo, $e->errores);
            }
        }
        $this->assertSame(0, DB::table('visitas_inspeccion')->count());
    }
}
