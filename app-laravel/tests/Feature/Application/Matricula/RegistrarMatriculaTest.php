<?php

namespace Tests\Feature\Application\Matricula;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Matricula\DTO\DatosMatricula;
use App\Application\Matricula\RegistrarMatricula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class RegistrarMatriculaTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private function gradoId(string $nivel, int $orden): int
    {
        return (int) DB::table('grados')
            ->join('niveles_educativos', 'niveles_educativos.id', '=', 'grados.nivel_educativo_id')
            ->where('niveles_educativos.clave', $nivel)->where('grados.orden', $orden)->value('grados.id');
    }

    private function salaId(string $clave): int
    {
        return (int) DB::table('salas')->where('clave', $clave)->value('id');
    }

    public function test_primaria_guarda_grupos_por_grado_y_completa_el_paso(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'matricula');

        app(RegistrarMatricula::class)->ejecutar($escuelaNivel->id, new DatosMatricula(grupos: [
            ['gradoId' => $this->gradoId('primaria', 1), 'grupo' => 'a', 'alumnos' => 25],
            ['gradoId' => $this->gradoId('primaria', 1), 'grupo' => 'B', 'alumnos' => 20],
            ['gradoId' => $this->gradoId('primaria', 2), 'grupo' => 'A', 'alumnos' => 30],
        ]));

        $this->assertSame(75, (int) DB::table('matricula_grados')->where('escuela_nivel_id', $escuelaNivel->id)->sum('cantidad_alumnos'));
        $this->assertDatabaseHas('matricula_grados', ['escuela_nivel_id' => $escuelaNivel->id, 'grupo' => 'A', 'cantidad_alumnos' => 25]);
        $this->assertTrue($this->pasoCompletado($escuelaNivel, 'matricula'));
    }

    public function test_volver_a_guardar_reemplaza_los_grupos(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'matricula');
        $registrar = app(RegistrarMatricula::class);

        $registrar->ejecutar($escuelaNivel->id, new DatosMatricula(grupos: [['gradoId' => $this->gradoId('primaria', 1), 'grupo' => 'A', 'alumnos' => 25], ['gradoId' => $this->gradoId('primaria', 2), 'grupo' => 'A', 'alumnos' => 25]]));
        $registrar->ejecutar($escuelaNivel->id, new DatosMatricula(grupos: [['gradoId' => $this->gradoId('primaria', 1), 'grupo' => 'A', 'alumnos' => 30]]));

        $this->assertSame(1, DB::table('matricula_grados')->where('escuela_nivel_id', $escuelaNivel->id)->count());
    }

    public function test_inicial_guarda_alumnos_por_sala(): void
    {
        $escuelaNivel = $this->nivelListoPara('inicial', 'matricula');

        app(RegistrarMatricula::class)->ejecutar($escuelaNivel->id, new DatosMatricula(salas: [
            $this->salaId('lactantes_a') => 8,
            $this->salaId('maternal_a') => 12,
        ]));

        $this->assertSame(20, (int) DB::table('matricula_salas')->where('escuela_nivel_id', $escuelaNivel->id)->sum('cantidad_alumnos'));
        $this->assertTrue($this->pasoCompletado($escuelaNivel, 'matricula'));
    }

    public function test_rechaza_grados_de_otro_nivel_grupos_repetidos_y_cantidades_negativas(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'matricula');

        try {
            app(RegistrarMatricula::class)->ejecutar($escuelaNivel->id, new DatosMatricula(grupos: [
                ['gradoId' => $this->gradoId('secundaria', 1), 'grupo' => 'A', 'alumnos' => 10],
                ['gradoId' => $this->gradoId('primaria', 1), 'grupo' => 'A', 'alumnos' => 10],
                ['gradoId' => $this->gradoId('primaria', 1), 'grupo' => 'a', 'alumnos' => 10],
                ['gradoId' => $this->gradoId('primaria', 2), 'grupo' => 'A', 'alumnos' => -1],
            ]));
            $this->fail('Se esperaba DatosInvalidos');
        } catch (DatosInvalidos $e) {
            $this->assertEqualsCanonicalizing(['grupos.0.gradoId', 'grupos.2.grupo', 'grupos.3.alumnos'], array_keys($e->errores));
        }

        $this->assertSame(0, DB::table('matricula_grados')->count());
    }

    public function test_exige_al_menos_un_alumno_y_la_forma_del_nivel(): void
    {
        $inicial = $this->nivelListoPara('inicial', 'matricula');

        try {
            app(RegistrarMatricula::class)->ejecutar($inicial->id, new DatosMatricula(salas: [$this->salaId('lactantes_a') => 0]));
            $this->fail('Se esperaba DatosInvalidos');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('matricula', $e->errores);
        }

        try {
            app(RegistrarMatricula::class)->ejecutar($inicial->id, new DatosMatricula(grupos: [['gradoId' => 1, 'grupo' => 'A', 'alumnos' => 5]]));
            $this->fail('Se esperaba DatosInvalidos');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('matricula', $e->errores);
        }
    }

    public function test_no_se_puede_saltar_el_orden_del_wizard(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plantilla_docente');

        $this->expectException(PrecondicionIncumplida::class);

        app(RegistrarMatricula::class)->ejecutar($escuelaNivel->id, new DatosMatricula(grupos: [['gradoId' => $this->gradoId('primaria', 1), 'grupo' => 'A', 'alumnos' => 25]]));
    }

    /** Like WS-5b: the delete-and-recreate runs under the level's row lock. A real race cannot run in PHPUnit; this checks the lock is taken. */
    public function test_reemplaza_la_matricula_bajo_el_lock_del_nivel(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'matricula');
        DB::enableQueryLog();

        app(RegistrarMatricula::class)->ejecutar($escuelaNivel->id, new DatosMatricula(grupos: [['gradoId' => $this->gradoId('primaria', 1), 'grupo' => 'A', 'alumnos' => 25]]));

        $this->assertNotEmpty(array_filter(DB::getQueryLog(), fn (array $q) => str_contains($q['query'], 'from "escuela_niveles"') && str_ends_with($q['query'], 'for update')));
    }
}
