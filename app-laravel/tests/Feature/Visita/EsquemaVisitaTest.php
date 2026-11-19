<?php

namespace Tests\Feature\Visita;

use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\CatalogoMinimoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\TestCase;

class EsquemaVisitaTest extends TestCase
{
    use ConNivelParaResponsables;
    use RefreshDatabase;

    private function nivel(): EscuelaNivel
    {
        return $this->nivelDe(Solicitante::factory()->create(), 'primaria', 'aprobado');
    }

    private function visita(EscuelaNivel $nivel, array $extra = []): int
    {
        return DB::table('visitas_inspeccion')->insertGetId($extra + [
            'escuela_nivel_id' => $nivel->id,
            'numero' => 1,
            'programada_para' => now()->addWeek(),

            'inspector' => 'Inspector Uno',
            'estado' => 'programada',
            'programada_por' => User::factory()->create()->id,
        ]);
    }

    public function test_seeds_three_visita_estados(): void
    {
        (new CatalogoMinimoSeeder)->run();

        $this->assertDatabaseHas('estados_expediente', ['clave' => 'visita_programada', 'orden' => 7]);
        $this->assertDatabaseHas('estados_expediente', ['clave' => 'en_subsanacion', 'orden' => 8]);
        $this->assertDatabaseHas('estados_expediente', ['clave' => 'visita_aprobada', 'orden' => 9]);
    }

    /** Falla si la sentencia no viola exactamente `$restriccion` con el SQLSTATE dado. */
    private function assertViola(callable $sentencia, string $sqlstate, string $restriccion): void
    {
        try {
            $sentencia();
        } catch (QueryException $e) {
            $this->assertSame($sqlstate, $e->getCode());
            $this->assertStringContainsString($restriccion, $e->getMessage());

            return;
        }
        $this->fail("No se violo {$restriccion}");
    }

    private function realizada(EscuelaNivel $nivel, array $extra = []): int
    {
        return $this->visita($nivel, $extra + ['estado' => 'realizada', 'resultado' => 'cumple', 'realizada_el' => now()->toDateString()]);
    }

    public function test_only_one_programada_visit_per_nivel(): void
    {
        $nivel = $this->nivel();
        $this->visita($nivel);

        $this->assertViola(fn () => $this->visita($nivel, ['numero' => 2]), '23505', 'visitas_inspeccion_una_programada');
    }

    public function test_numero_unique_per_nivel(): void
    {
        $nivel = $this->nivel();
        $this->realizada($nivel);

        $this->assertViola(fn () => $this->realizada($nivel), '23505', 'visitas_inspeccion_numero_unico');
    }

    public function test_realizada_requires_resultado(): void
    {
        $this->assertViola(
            fn () => $this->visita($this->nivel(), ['estado' => 'realizada', 'realizada_el' => now()->toDateString()]),
            '23514', 'visitas_inspeccion_resultado_iff_realizada');
    }

    public function test_programada_rejects_resultado(): void
    {
        $this->assertViola(fn () => $this->visita($this->nivel(), ['resultado' => 'cumple']), '23514', 'visitas_inspeccion_resultado_iff_realizada');
    }

    public function test_con_observaciones_requires_subsanar_hasta(): void
    {
        $this->assertViola(
            fn () => $this->realizada($this->nivel(), ['resultado' => 'con_observaciones']),
            '23514', 'visitas_inspeccion_subsanar_iff_observaciones');
    }

    public function test_cumple_rejects_subsanar_hasta(): void
    {
        $this->assertViola(
            fn () => $this->realizada($this->nivel(), ['subsanar_hasta' => now()->toDateString()]),
            '23514', 'visitas_inspeccion_subsanar_iff_observaciones');
    }

    public function test_programada_rejects_subsanar_hasta(): void
    {
        $this->assertViola(
            fn () => $this->visita($this->nivel(), ['subsanar_hasta' => now()->toDateString()]),
            '23514', 'visitas_inspeccion_subsanar_iff_observaciones');
    }

    public function test_blank_observacion_rejected(): void
    {
        $id = $this->visita($this->nivel());

        $this->assertViola(
            fn () => DB::table('observaciones_visita')->insert(['visita_id' => $id, 'orden' => 1, 'texto' => '   ']),
            '23514', 'observaciones_visita_texto_no_vacio');
    }

    public function test_blank_respuesta_nota_rejected(): void
    {
        $id = $this->visita($this->nivel());

        $this->assertViola(
            fn () => DB::table('respuestas_visita')->insert(['visita_id' => $id, 'nota' => '  ', 'creada_por' => User::factory()->create()->id]),
            '23514', 'respuestas_visita_nota_no_vacia');
    }

    public function test_deleting_nivel_cascades_all_tables(): void
    {
        $nivel = $this->nivel();
        $id = $this->visita($nivel);
        DB::table('observaciones_visita')->insert(['visita_id' => $id, 'orden' => 1, 'texto' => 'Falta extintor']);
        $respuesta = DB::table('respuestas_visita')->insertGetId(['visita_id' => $id, 'nota' => 'Corregido', 'creada_por' => User::factory()->create()->id]);
        DB::table('respuestas_visita_archivos')->insert(['respuesta_id' => $respuesta, 'archivo_path' => 'a/b.pdf', 'archivo_nombre' => 'b.pdf']);

        DB::table('escuela_niveles')->where('id', $nivel->id)->delete();

        foreach (['visitas_inspeccion', 'observaciones_visita', 'respuestas_visita', 'respuestas_visita_archivos'] as $tabla) {
            $this->assertSame(0, DB::table($tabla)->count(), $tabla);
        }
    }

    public function test_models_relations(): void
    {
        $nivel = $this->nivel();
        $id = $this->visita($nivel);
        DB::table('observaciones_visita')->insert(['visita_id' => $id, 'orden' => 1, 'texto' => 'X']);
        $r = DB::table('respuestas_visita')->insertGetId(['visita_id' => $id, 'nota' => 'N', 'creada_por' => User::factory()->create()->id]);
        DB::table('respuestas_visita_archivos')->insert(['respuesta_id' => $r, 'archivo_path' => 'p', 'archivo_nombre' => 'n']);

        $visita = $nivel->visitas()->first();
        $this->assertCount(1, $visita->observaciones);
        $this->assertCount(1, $visita->respuestas);
        $this->assertCount(1, $visita->respuestas->first()->archivos);
    }
}
