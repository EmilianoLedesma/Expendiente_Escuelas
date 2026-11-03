<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Revision\AsignarTramite;
use App\Application\Revision\DesasignarTramite;
use App\Application\Revision\RolesSedeq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class DesasignarLiberarTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    public function test_liberar_quita_y_registra_cada_asignacion_sin_exigir_supervisor(): void
    {
        $sup = $this->supervisor();
        $rev = $this->revisor();
        $admin = User::factory()->create()->assignRole(RolesSedeq::ADMIN);
        $ids = [$this->nivelEnRevision()->id, $this->nivelEnRevision()->id];
        app(AsignarTramite::class)->asignarSeleccion($sup->id, $ids, $rev->id);

        $n = app(DesasignarTramite::class)->liberar($admin->id, $ids, 'desasignar_archivo');

        $this->assertSame(2, $n);
        $this->assertSame(0, DB::table('asignaciones_revision')->count());
        $logs = DB::table('bitacora_asignaciones')->where('accion', 'desasignar_archivo')->get();
        $this->assertCount(2, $logs);
        $this->assertSame([$admin->id], $logs->pluck('actor_id')->map(fn ($id) => (int) $id)->unique()->values()->all());
        $this->assertEqualsCanonicalizing($ids, $logs->pluck('escuela_nivel_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_liberar_con_lista_vacia_devuelve_cero(): void
    {
        $this->assertSame(0, app(DesasignarTramite::class)->liberar(1, [], 'desasignar_archivo'));
    }
}
