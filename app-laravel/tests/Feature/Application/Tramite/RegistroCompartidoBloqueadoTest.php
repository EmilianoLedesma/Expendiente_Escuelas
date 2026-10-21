<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\Tramite\RegistroCompartidoBloqueado;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\GradosSeeder;
use Database\Seeders\MobiliarioConceptosSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * WS-7b-2: un nivel solo puede editar los datos compartidos del plantel (Paso 3) mientras
 * ningún OTRO nivel del plantel, de cualquier escuela, tenga datos propios de Paso 3.
 */
class RegistroCompartidoBloqueadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new CatalogoMinimoSeeder)->run();
        (new GradosSeeder)->run();
        (new MobiliarioConceptosSeeder)->run();
        (new PasosCapturaSeeder)->run();
    }

    private function plantel(): Plantel
    {
        return Plantel::create([
            'calle' => 'Av. Juárez', 'numero_ext' => '12', 'colonia' => 'Centro',
            'municipio' => 'Querétaro', 'codigo_postal' => '76000',
        ]);
    }

    /** Una escuela con otro Solicitante: ADR-009 permite varias escuelas (de dueños distintos) por plantel. */
    private function escuela(Plantel $plantel): Escuela
    {
        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
    }

    private function nivel(Escuela $escuela, string $clave): EscuelaNivel
    {
        return EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', $clave)->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
    }

    /** Fila propia de Paso 3 en la tabla indicada. */
    private function datosPropios(string $tabla, EscuelaNivel $nivel): void
    {
        if ($tabla === 'modalidad') {
            $nivel->update(['modalidad' => 'escolarizada']);

            return;
        }

        DB::table($tabla)->insert(['escuela_nivel_id' => $nivel->id] + match ($tabla) {
            'aulas_nivel' => ['numero_aulas' => 4],
            'mobiliario_nivel' => ['concepto_id' => DB::table('mobiliario_conceptos')->value('id'), 'cantidad_declarada' => 3],
            'personal' => ['nombre' => 'Ana López'],
            'matricula_salas' => ['sala_id' => DB::table('salas')->value('id'), 'cantidad_alumnos' => 5],
            'matricula_grados' => ['grado_id' => DB::table('grados')->value('id'), 'cantidad_alumnos' => 5],
        });
    }

    /** @return array<string, array{string}> */
    public static function fuentes(): array
    {
        return array_combine(
            $f = ['aulas_nivel', 'mobiliario_nivel', 'personal', 'matricula_salas', 'matricula_grados', 'modalidad'],
            array_map(fn (string $t) => [$t], $f),
        );
    }

    public function test_sin_otros_niveles_no_esta_bloqueado(): void
    {
        $plantel = $this->plantel();
        $nivel = $this->nivel($this->escuela($plantel), 'primaria');

        $this->assertFalse(app(RegistroCompartidoBloqueado::class)->bloqueado($plantel->id, $nivel->id));
    }

    #[DataProvider('fuentes')]
    public function test_datos_propios_en_cualquier_tabla_de_otro_nivel_bloquean(string $tabla): void
    {
        $plantel = $this->plantel();
        $escuela = $this->escuela($plantel);
        $nivel = $this->nivel($escuela, 'primaria');
        $otro = $this->nivel($escuela, 'secundaria');
        $this->datosPropios($tabla, $otro);

        $this->assertTrue(app(RegistroCompartidoBloqueado::class)->bloqueado($plantel->id, $nivel->id));
    }

    public function test_las_banderas_de_pasos_no_bloquean(): void
    {
        $plantel = $this->plantel();
        $escuela = $this->escuela($plantel);
        $nivel = $this->nivel($escuela, 'primaria');
        $otro = $this->nivel($escuela, 'secundaria');
        DB::table('escuela_nivel_pasos')->insert([
            'escuela_nivel_id' => $otro->id,
            'paso_captura_id' => DB::table('pasos_captura')->value('id'),
            'estado' => 'completado',
        ]);

        $this->assertFalse(app(RegistroCompartidoBloqueado::class)->bloqueado($plantel->id, $nivel->id));
    }

    public function test_los_datos_del_propio_nivel_no_bloquean(): void
    {
        $plantel = $this->plantel();
        $escuela = $this->escuela($plantel);
        $nivel = $this->nivel($escuela, 'primaria');
        $this->nivel($escuela, 'secundaria');
        $this->datosPropios('aulas_nivel', $nivel);

        $this->assertFalse(app(RegistroCompartidoBloqueado::class)->bloqueado($plantel->id, $nivel->id));
    }

    public function test_un_nivel_de_otra_escuela_del_mismo_plantel_bloquea(): void
    {
        $plantel = $this->plantel();
        $nivel = $this->nivel($this->escuela($plantel), 'primaria');
        $otro = $this->nivel($this->escuela($plantel), 'secundaria');
        $this->datosPropios('aulas_nivel', $otro);

        $this->assertTrue(app(RegistroCompartidoBloqueado::class)->bloqueado($plantel->id, $nivel->id));
    }

    public function test_un_nivel_de_otro_plantel_no_bloquea(): void
    {
        $plantel = $this->plantel();
        $nivel = $this->nivel($this->escuela($plantel), 'primaria');
        $ajeno = $this->nivel($this->escuela($this->plantel()), 'secundaria');
        $this->datosPropios('aulas_nivel', $ajeno);

        $this->assertFalse(app(RegistroCompartidoBloqueado::class)->bloqueado($plantel->id, $nivel->id));
    }

    public function test_quitar_los_datos_del_otro_nivel_desbloquea(): void
    {
        $plantel = $this->plantel();
        $escuela = $this->escuela($plantel);
        $nivel = $this->nivel($escuela, 'primaria');
        $otro = $this->nivel($escuela, 'secundaria');
        $this->datosPropios('aulas_nivel', $otro);
        $regla = app(RegistroCompartidoBloqueado::class);
        $this->assertTrue($regla->bloqueado($plantel->id, $nivel->id));

        DB::table('aulas_nivel')->where('escuela_nivel_id', $otro->id)->delete();

        $this->assertFalse($regla->bloqueado($plantel->id, $nivel->id));
    }

    public function test_bloquear_niveles_toma_for_update_sobre_todos_los_niveles_del_plantel_antes_de_tocar_escuelas(): void
    {
        $plantel = $this->plantel();
        $a = $this->escuela($plantel);
        $b = $this->escuela($plantel);
        $this->nivel($a, 'primaria');
        $this->nivel($a, 'secundaria');
        $this->nivel($b, 'inicial');
        $sql = [];
        $bindings = [];
        DB::listen(function ($query) use (&$sql, &$bindings) {
            $sql[] = $query->sql;
            $bindings[] = $query->bindings;
        });

        DB::transaction(fn () => app(RegistroCompartidoBloqueado::class)->bloquearNiveles($plantel->id));

        $conBloqueo = array_keys(array_filter($sql, fn (string $s) => str_ends_with($s, 'for update')));
        $this->assertNotEmpty($conBloqueo, 'Nunca se tomó un FOR UPDATE.');
        $this->assertMatchesRegularExpression('/^select .* from "escuela_niveles"/', $sql[$conBloqueo[0]]);
        $bloqueoEscuelas = array_filter($sql, fn (string $s) => str_ends_with($s, 'for update') && preg_match('/^select ("\w+"|\*) from "escuelas"/', $s) === 1);
        $this->assertSame([], $bloqueoEscuelas, 'bloquearNiveles no debe bloquear escuelas antes que los niveles.');
        $this->assertSame([$plantel->id], $bindings[$conBloqueo[0]], 'El bloqueo debe filtrar por los niveles del plantel.');
    }
}
