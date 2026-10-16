<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class TablasRevisionTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    public function test_existen_las_tablas_de_revision(): void
    {
        foreach (['asignaciones_revision', 'prioridades_revision', 'progreso_revision', 'bitacora_asignaciones', 'revisores'] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}.");
        }
    }

    /** 'urgente' (7) ya lo rechaza VARCHAR(5); un valor corto prueba el CHECK en sí. */
    public function test_prioridad_fuera_del_catalogo_se_rechaza(): void
    {
        $nivel = $this->nivelEnRevision();

        foreach (['urgente' => 'character varying(5)', 'nula' => 'prioridades_revision_prioridad_check'] as $valor => $motivo) {
            try {
                DB::transaction(fn () => DB::table('prioridades_revision')->insert(['escuela_nivel_id' => $nivel->id, 'prioridad' => $valor]));
                $this->fail("Se aceptó la prioridad {$valor}.");
            } catch (QueryException $e) {
                $this->assertStringContainsString($motivo, $e->getMessage());
            }
        }
    }

    public function test_borrar_el_nivel_borra_su_asignacion_prioridad_progreso_y_bitacora(): void
    {
        $nivel = $this->nivelEnRevision();
        $sup = $this->supervisor();
        $rev = $this->revisor();
        DB::table('asignaciones_revision')->insert(['escuela_nivel_id' => $nivel->id, 'revisor_id' => $rev->id, 'asignado_por' => $sup->id]);
        DB::table('prioridades_revision')->insert(['escuela_nivel_id' => $nivel->id, 'prioridad' => 'alta']);
        DB::table('progreso_revision')->insert(['escuela_nivel_id' => $nivel->id]);
        DB::table('bitacora_asignaciones')->insert(['escuela_nivel_id' => $nivel->id, 'revisor_id' => $rev->id, 'accion' => 'asignar', 'actor_id' => $sup->id]);

        $nivel->delete();

        foreach (['asignaciones_revision', 'prioridades_revision', 'progreso_revision', 'bitacora_asignaciones'] as $tabla) {
            $this->assertSame(0, DB::table($tabla)->count(), $tabla);
        }
    }

    public function test_progreso_con_mas_revisadas_que_total_se_rechaza(): void
    {
        [$bueno, $malo] = [$this->nivelEnRevision(), $this->nivelEnRevision()];

        DB::table('progreso_revision')->insert(['escuela_nivel_id' => $bueno->id, 'secciones_revisadas' => 3, 'secciones_total' => 7]);

        try {
            DB::transaction(fn () => DB::table('progreso_revision')->insert(['escuela_nivel_id' => $malo->id, 'secciones_revisadas' => 8, 'secciones_total' => 7]));
            $this->fail('Se aceptó revisadas > total.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('progreso_revision_check', $e->getMessage());
        }
    }
}
