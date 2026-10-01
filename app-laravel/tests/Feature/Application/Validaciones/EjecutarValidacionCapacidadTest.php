<?php

namespace Tests\Feature\Application\Validaciones;

use App\Application\Matricula\DTO\DatosMatricula;
use App\Application\Matricula\RegistrarMatricula;
use App\Application\Personal\DTO\DatosPersona;
use App\Application\Personal\RegistrarPlantillaDocente;
use App\Application\Validaciones\ConstruirDatosCapacidad;
use App\Application\Validaciones\EjecutarValidacionCapacidad;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use App\Models\AulaNivel;
use App\Models\EscuelaNivel;
use App\Models\InstalacionEspacio;
use App\Models\MobiliarioNivel;
use App\Models\Sanitario;
use Database\Seeders\MobiliarioConceptosSeeder;
use Database\Seeders\ReglasValidacionSeeder;
use Database\Seeders\TiposEspaciosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class EjecutarValidacionCapacidadTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private function nivel(string $clave): EscuelaNivel
    {
        $escuelaNivel = $this->nivelListoPara($clave, 'plan_estudios');
        (new TiposEspaciosSeeder)->run();
        (new ReglasValidacionSeeder)->run();
        (new MobiliarioConceptosSeeder)->run();
        foreach (['plan_estudios', 'plantilla_docente'] as $paso) {
            DB::table('escuela_nivel_pasos')->insert([
                'escuela_nivel_id' => $escuelaNivel->id,
                'paso_captura_id' => DB::table('pasos_captura')->where('clave', $paso)->value('id'),
                'estado' => 'completado',
            ]);
        }

        return $escuelaNivel;
    }

    private function persona(int $cargoId, ?int $salaId = null, ?int $asignaturaId = null): DatosPersona
    {
        return new DatosPersona($cargoId, 'Persona', 'Mexicana', 'F', 'Licenciatura', '123', $salaId, $asignaturaId);
    }

    private function gradoId(EscuelaNivel $escuelaNivel, int $orden): int
    {
        return (int) DB::table('grados')->where('nivel_educativo_id', $escuelaNivel->nivel_educativo_id)->where('orden', $orden)->value('id');
    }

    private function tipoEspacio(string $clave): int
    {
        return (int) DB::table('tipos_espacios')->where('clave', $clave)->value('id');
    }

    private function sala(string $clave): int
    {
        return (int) DB::table('salas')->where('clave', $clave)->value('id');
    }

    public function test_sin_matricula_ni_plantilla_todo_lo_que_depende_de_ellas_no_es_evaluable(): void
    {
        $escuelaNivel = $this->nivel('primaria');

        $datos = app(ConstruirDatosCapacidad::class)->paraNivel($escuelaNivel->id);
        $reporte = app(EjecutarValidacionCapacidad::class)->ejecutar($escuelaNivel->id);

        $this->assertNull($datos->matriculaNivel);
        $this->assertNull($datos->personalPorCargo);
        $this->assertSame(0, $reporte->contar(EstadoResultado::NoCumple));
        $this->assertSame(EstadoResultado::NoEvaluable, $reporte->resultado('primaria.superficie.aulas')?->estado);
        $this->assertSame(EstadoResultado::NoEvaluable, $reporte->resultado('primaria.personal.director_tecnico')?->estado);
    }

    public function test_primaria_con_datos_capturados(): void
    {
        $escuelaNivel = $this->nivel('primaria');
        app(RegistrarPlantillaDocente::class)->ejecutar($escuelaNivel->id, [$this->persona($this->cargoId('primaria', 'Docente Titular de Grupo'))]);
        app(RegistrarMatricula::class)->ejecutar($escuelaNivel->id, new DatosMatricula(grupos: [
            ['gradoId' => $this->gradoId($escuelaNivel, 1), 'grupo' => 'A', 'alumnos' => 40],
            ['gradoId' => $this->gradoId($escuelaNivel, 2), 'grupo' => 'A', 'alumnos' => 30],
        ]));
        AulaNivel::create(['escuela_nivel_id' => $escuelaNivel->id, 'numero_aulas' => 2, 'superficie_m2' => 60]);
        $escuelaNivel->escuela->plantel->update(['metros_totales' => 200, 'metros_construidos' => 150]);

        $datos = app(ConstruirDatosCapacidad::class)->paraNivel($escuelaNivel->id);
        $reporte = app(EjecutarValidacionCapacidad::class)->ejecutar($escuelaNivel->id);

        $this->assertSame(70, $datos->matriculaNivel);
        $this->assertSame(70, $datos->matriculaPlantel);
        $this->assertSame(2, $datos->gradosOfertados);
        // 70 × 0.90 = 63 m² > 60
        $this->assertSame(EstadoResultado::NoCumple, $reporte->resultado('primaria.superficie.aulas')?->estado);
        // 70 × 2.50 = 175 m² ≤ 200
        $this->assertSame(EstadoResultado::Cumple, $reporte->resultado('primaria.superficie.predio_total')?->estado);
        $this->assertSame(EstadoResultado::NoCumple, $reporte->resultado('primaria.personal.director_tecnico')?->estado);
        // ⌊70/60⌋ = 1 docente de Educación Física, none declared
        $this->assertSame(EstadoResultado::NoCumple, $reporte->resultado('primaria.personal.educacion_fisica')?->estado);
        $this->assertSame(EstadoResultado::NoEvaluable, $reporte->resultado('primaria.infraestructura.altura_aulas')?->estado);
    }

    public function test_el_predio_suma_la_matricula_de_todos_los_niveles_del_plantel(): void
    {
        $primaria = $this->nivel('primaria');
        app(RegistrarMatricula::class)->ejecutar($primaria->id, new DatosMatricula(grupos: [['gradoId' => $this->gradoId($primaria, 1), 'grupo' => 'A', 'alumnos' => 40]]));
        $secundaria = EscuelaNivel::create([
            'escuela_id' => $primaria->escuela_id,
            'nivel_educativo_id' => DB::table('niveles_educativos')->where('clave', 'secundaria')->value('id'),
            'estado_id' => $primaria->estado_id,
            'tipo_tramite' => 'alta_nueva',
        ]);
        DB::table('matricula_grados')->insert(['escuela_nivel_id' => $secundaria->id, 'grado_id' => $this->gradoId($secundaria, 1), 'grupo' => 'A', 'cantidad_alumnos' => 35]);

        $datos = app(ConstruirDatosCapacidad::class)->paraNivel($primaria->id);

        $this->assertSame(40, $datos->matriculaNivel);
        $this->assertSame(75, $datos->matriculaPlantel);
    }

    public function test_solo_cuenta_personal_completo(): void
    {
        $escuelaNivel = $this->nivel('primaria');
        app(RegistrarPlantillaDocente::class)->ejecutar($escuelaNivel->id, [$this->persona($this->cargoId('primaria', 'Director Técnico'))]);
        DB::table('personal')->insert(['escuela_nivel_id' => $escuelaNivel->id, 'cargo_puesto_id' => $this->cargoId('primaria', 'Director Técnico'), 'nombre' => 'Borrador']);

        $datos = app(ConstruirDatosCapacidad::class)->paraNivel($escuelaNivel->id);

        $this->assertSame([$this->cargoId('primaria', 'Director Técnico') => 1], $datos->personalPorCargo);
    }

    public function test_espacios_sanitarios_y_biblioteca_del_plantel(): void
    {
        $escuelaNivel = $this->nivel('secundaria');
        $plantelId = $escuelaNivel->escuela->plantel_id;
        InstalacionEspacio::create(['plantel_id' => $plantelId, 'tipo_espacio_id' => $this->tipoEspacio('area_recreo'), 'cantidad' => 1, 'superficie_m2' => 150]);
        InstalacionEspacio::create(['plantel_id' => $plantelId, 'tipo_espacio_id' => $this->tipoEspacio('cancha_usos_multiples'), 'cantidad' => 1, 'superficie_m2' => 80]);
        InstalacionEspacio::create(['plantel_id' => $plantelId, 'tipo_espacio_id' => $this->tipoEspacio('direccion'), 'cantidad' => 1, 'superficie_m2' => 20]);
        $biblioteca = InstalacionEspacio::create(['plantel_id' => $plantelId, 'tipo_espacio_id' => $this->tipoEspacio('biblioteca'), 'cantidad' => 1, 'superficie_m2' => 40]);
        DB::table('biblioteca_materiales')->insert([
            ['instalacion_espacio_id' => $biblioteca->id, 'tipo_material_id' => DB::table('tipos_material_biblioteca')->where('clave', 'libros')->value('id'), 'numero_titulos' => 320],
            ['instalacion_espacio_id' => $biblioteca->id, 'tipo_material_id' => DB::table('tipos_material_biblioteca')->where('clave', 'revistas_especializadas')->value('id'), 'numero_titulos' => 50],
        ]);
        Sanitario::create(['plantel_id' => $plantelId, 'categoria' => 'alumnado_masculino', 'superficie_m2' => 12]);
        Sanitario::create(['plantel_id' => $plantelId, 'categoria' => 'alumnado_femenino', 'superficie_m2' => 10]);
        Sanitario::create(['plantel_id' => $plantelId, 'categoria' => 'personal', 'superficie_m2' => 5]);

        $datos = app(ConstruirDatosCapacidad::class)->paraNivel($escuelaNivel->id);

        $this->assertSame(230.0, $datos->superficieRecreativa);
        $this->assertSame(320, $datos->acervoTitulos);
        $this->assertSame(22.0, $datos->superficieSanitariosAlumnos);
        $this->assertNull($datos->superficieUsosMultiples);
    }

    public function test_secundaria_cuenta_educacion_fisica_por_asignatura(): void
    {
        $escuelaNivel = $this->nivel('secundaria');
        $asignatura = (int) DB::table('asignaturas')->where('nombre', 'Educación Física')->value('id');
        app(RegistrarPlantillaDocente::class)->ejecutar($escuelaNivel->id, [
            $this->persona($this->cargoId('secundaria', 'Docente Titular'), asignaturaId: $asignatura),
        ]);

        $this->assertSame(1, app(ConstruirDatosCapacidad::class)->paraNivel($escuelaNivel->id)->docentesEducacionFisica);
    }

    public function test_inicial_salas_asistentes_y_mobiliario(): void
    {
        $escuelaNivel = $this->nivel('inicial');
        app(RegistrarPlantillaDocente::class)->ejecutar($escuelaNivel->id, [
            $this->persona($this->cargoId('inicial', 'Asistente Educativo'), salaId: $this->sala('lactantes_a')),
        ]);
        app(RegistrarMatricula::class)->ejecutar($escuelaNivel->id, new DatosMatricula(salas: [$this->sala('lactantes_a') => 6]));
        $cuna = (int) DB::table('mobiliario_conceptos')->where('nombre', 'like', 'Cuna%')->where('sala_id', $this->sala('lactantes_a'))->value('id');
        MobiliarioNivel::create(['escuela_nivel_id' => $escuelaNivel->id, 'concepto_id' => $cuna, 'cantidad_declarada' => 3]);

        $datos = app(ConstruirDatosCapacidad::class)->paraNivel($escuelaNivel->id);
        $reporte = app(EjecutarValidacionCapacidad::class)->ejecutar($escuelaNivel->id);

        $this->assertSame(6, $datos->matriculaPorSala['lactantes_a']);
        $this->assertSame(1, $datos->personalPorCargoYSala[$this->cargoId('inicial', 'Asistente Educativo')]['lactantes_a']);
        // ⌈6/5⌉ = 2 asistentes, 1 declared
        $this->assertSame(EstadoResultado::NoCumple, $reporte->resultado('inicial.personal.asistente_lactantes')?->estado);
        $mobiliario = $reporte->resultado('inicial.mobiliario');
        $this->assertSame(EstadoResultado::NoCumple, $mobiliario?->estado);
        $this->assertNotContains('Cuna con barandal', array_column($mobiliario->detalles['faltantes'], 'concepto'));
    }
}
