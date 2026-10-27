<?php

namespace Tests\Feature\Database;

use App\Application\Validaciones\ConstruirDatosCapacidad;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\TiposEspaciosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** D8: Inicial sin cubículo, cancha ni campo de fútbol; lo capturado sobrevive y sigue contando. */
class RetirarEspaciosInicialTest extends TestCase
{
    use RefreshDatabase;

    private const RETIRADAS = ['cubiculo', 'cancha_usos_multiples', 'campo_futbol'];

    protected function setUp(): void
    {
        parent::setUp();

        (new CatalogoMinimoSeeder)->run();
        (new TiposEspaciosSeeder)->run();
    }

    private function migracion(): object
    {
        return require database_path('migrations/2026_10_27_000000_retirar_espacios_no_aplicables_de_inicial.php');
    }

    private function idTipo(string $clave): int
    {
        return (int) DB::table('tipos_espacios')->where('clave', $clave)->value('id');
    }

    private function idInicial(): int
    {
        return (int) NivelEducativo::where('clave', 'inicial')->value('id');
    }

    private function mapeosInicial(): int
    {
        return DB::table('niveles_tipos_espacios')
            ->where('nivel_educativo_id', $this->idInicial())
            ->whereIn('tipo_espacio_id', array_map($this->idTipo(...), self::RETIRADAS))
            ->count();
    }

    /** Estado de una base ya sembrada antes de D8. */
    private function reponerMapeosLegados(): void
    {
        $this->migracion()->down();
    }

    public function test_un_seed_nuevo_no_mapea_las_tres_a_inicial_pero_si_a_los_demas(): void
    {
        $this->assertSame(0, $this->mapeosInicial());

        foreach (['preescolar', 'primaria', 'secundaria'] as $nivel) {
            $this->assertSame(3, DB::table('niveles_tipos_espacios')
                ->where('nivel_educativo_id', NivelEducativo::where('clave', $nivel)->value('id'))
                ->whereIn('tipo_espacio_id', array_map($this->idTipo(...), self::RETIRADAS))
                ->count(), $nivel);
        }
    }

    public function test_la_migracion_retira_los_mapeos_legados_es_idempotente_y_reversible(): void
    {
        $this->reponerMapeosLegados();
        $this->assertSame(3, $this->mapeosInicial());
        $totalAntes = DB::table('niveles_tipos_espacios')->count();

        $this->migracion()->up();
        $this->migracion()->up();

        $this->assertSame(0, $this->mapeosInicial());
        $this->assertSame($totalAntes - 3, DB::table('niveles_tipos_espacios')->count(), 'no toca otros mapeos');

        $this->migracion()->down();
        $this->migracion()->down();
        $this->assertSame(3, $this->mapeosInicial());
    }

    public function test_filas_capturadas_sobreviven_y_siguen_sumando_a_area_recreativa(): void
    {
        $this->reponerMapeosLegados();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'numero_ext' => '10', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
        $nivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => $this->idInicial(),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
        DB::table('planteles')->where('id', $plantel->id)->update(['area_civica_m2' => 50]);
        foreach (self::RETIRADAS as $clave) {
            DB::table('instalaciones_espacios')->insert(['plantel_id' => $plantel->id, 'tipo_espacio_id' => $this->idTipo($clave), 'cantidad' => 1, 'superficie_m2' => 100]);
        }
        DB::table('sanitarios')->insert(['plantel_id' => $plantel->id, 'categoria' => 'personal', 'cantidad_retretes' => 1]);
        $recreativa = fn () => app(ConstruirDatosCapacidad::class)->paraNivel($nivel->id)->superficieRecreativa;
        $this->assertSame(200.0, $recreativa());

        $this->migracion()->up();

        $this->assertSame(0, $this->mapeosInicial());
        $this->assertSame(3, DB::table('instalaciones_espacios')->where('plantel_id', $plantel->id)->count());
        $this->assertSame(1, DB::table('sanitarios')->where('plantel_id', $plantel->id)->count());
        $this->assertSame('50.00', DB::table('planteles')->where('id', $plantel->id)->value('area_civica_m2'));
        $this->assertSame(200.0, $recreativa(), 'cancha + campo (recreativo_deportivo) siguen contando; el cubículo no es de esa categoría');
    }
}
