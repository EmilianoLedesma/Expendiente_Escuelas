<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\AsignarTramite;
use App\Application\Revision\DesasignarTramite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class DesasignarTramiteTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    public function test_todos_de_conserva_el_progreso(): void
    {
        $sup = $this->supervisor();
        $rev = $this->revisor();
        $nivel = $this->nivelEnRevision();
        app(AsignarTramite::class)->ejecutar($sup->id, $nivel->id, $rev->id);

        app(DesasignarTramite::class)->todosDe($sup->id, $rev->id);

        $this->assertSame(0, DB::table('asignaciones_revision')->count());
        $this->assertSame(1, DB::table('progreso_revision')->where('escuela_nivel_id', $nivel->id)->count());
    }

    public function test_todos_de_sin_asignaciones_no_registra_nada(): void
    {
        $sup = $this->supervisor();

        $this->assertSame(0, app(DesasignarTramite::class)->todosDe($sup->id, $this->revisor()->id));
        $this->assertSame(0, DB::table('bitacora_asignaciones')->count());
    }

    public function test_todos_de_rechaza_un_actor_que_no_es_supervisor(): void
    {
        $rev = $this->revisor();

        $this->expectException(PrecondicionIncumplida::class);
        app(DesasignarTramite::class)->todosDe($rev->id, $rev->id);
    }

    public function test_todos_de_quita_todas_las_del_revisor_y_registra_cada_una(): void
    {
        $sup = $this->supervisor();
        [$rev, $otro] = [$this->revisor(), $this->revisor()];
        $suyos = [$this->nivelEnRevision()->id, $this->nivelEnRevision()->id];
        app(AsignarTramite::class)->asignarSeleccion($sup->id, $suyos, $rev->id);
        $ajeno = $this->nivelEnRevision()->id;
        app(AsignarTramite::class)->ejecutar($sup->id, $ajeno, $otro->id);

        $quitados = app(DesasignarTramite::class)->todosDe($sup->id, $rev->id);

        $this->assertSame(2, $quitados);
        $this->assertSame([$ajeno], DB::table('asignaciones_revision')->pluck('escuela_nivel_id')->map(fn ($id) => (int) $id)->all());
        $logs = DB::table('bitacora_asignaciones')->where('accion', 'desasignar_revocacion')->get();
        $this->assertEqualsCanonicalizing($suyos, $logs->pluck('escuela_nivel_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([$rev->id], $logs->pluck('revisor_id')->map(fn ($id) => (int) $id)->unique()->values()->all());
    }
}
