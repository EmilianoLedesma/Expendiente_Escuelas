<?php

namespace Tests\Feature\Application\Visita;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Visita\ListarVisitasRealizadas;
use App\Models\EscuelaNivel;
use App\Models\User;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\Concerns\ConVisitas;
use Tests\Concerns\GeneraPdfs;
use Tests\TestCase;

/** Registro de visitas realizadas (supervisor, solo lectura): una fila por visita `realizada`, de más reciente a más antigua. */
class ListarVisitasRealizadasTest extends TestCase
{
    use ConTramitesEnRevision;
    use ConVisitas;
    use GeneraPdfs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new PasosCapturaSeeder)->run();
    }

    private function nombrar(EscuelaNivel $nivel, string $nombre): void
    {
        DB::table('escuelas')->where('id', $nivel->escuela_id)->update(['nombre_aprobado' => $nombre]);
    }

    private function realizada(string $nombre, string $fecha, string $resultado = 'cumple', string $estadoNivel = 'visita_aprobada'): array
    {
        $nivel = $this->nivelEnRevision($estadoNivel);
        $this->nombrar($nivel, $nombre);
        $id = $this->visitaDe($nivel, 1, 'realizada', $resultado, ['realizada_el' => $fecha]);

        return [$nivel, $id];
    }

    private function listar(array $args = []): array
    {
        return app(ListarVisitasRealizadas::class)->ejecutar($this->supervisor(), ...$args);
    }

    public function test_solo_lista_visitas_realizadas_de_la_mas_reciente_a_la_mas_antigua(): void
    {
        [, $vieja] = $this->realizada('Colegio Viejo', '2026-01-10');
        [, $nueva] = $this->realizada('Colegio Nuevo', '2026-03-10');
        [, $empate] = $this->realizada('Colegio Empate', '2026-03-10');
        $this->visitaDe($this->nivelEnRevision('visita_programada'), 1, 'programada');

        $r = $this->listar();

        $this->assertSame(3, $r['total']);
        $this->assertSame([$empate, $nueva, $vieja], array_map(fn ($f) => $f->visitaId, $r['filas']));
    }

    public function test_los_campos_traen_conteos_acta_y_estado_actual_del_nivel(): void
    {
        [$nivel, $id] = $this->realizada('Colegio Campos', '2026-02-05', 'con_observaciones', 'en_subsanacion');
        DB::table('visitas_inspeccion')->where('id', $id)->update(['acta_path' => 'visitas/1/acta.pdf', 'acta_nombre' => 'acta.pdf', 'subsanar_hasta' => '2026-02-20']);
        DB::table('observaciones_visita')->insert([['visita_id' => $id, 'orden' => 1, 'texto' => 'Uno'], ['visita_id' => $id, 'orden' => 2, 'texto' => 'Dos']]);
        Storage::fake('documentos');
        $this->respuestaDe($id, 'a.pdf', $this->archivoEnDisco());
        $this->respuestaDe($id, 'b.pdf', $this->archivoEnDisco());
        $this->respuestaDe($id, 'c.pdf', $this->archivoEnDisco());
        // Otra visita sin acta ni observaciones no contamina los conteos.
        [$otro] = $this->realizada('Colegio Otro', '2026-02-01');

        $f = collect($this->listar()['filas'])->keyBy('visitaId')[$id];

        $this->assertSame($nivel->id, $f->escuelaNivelId);
        $this->assertSame($nivel->escuela_id, $f->escuelaId);
        $this->assertSame('Colegio Campos', $f->escuela);
        $this->assertSame(1, $f->numero);
        $this->assertSame('2026-02-05', $f->realizadaEl->toDateString());
        $this->assertSame('Inspector Uno', $f->inspector);
        $this->assertSame('con_observaciones', $f->resultado);
        $this->assertSame('2026-02-20', $f->subsanarHasta->toDateString());
        $this->assertSame(2, $f->observacionesCount);
        $this->assertSame(3, $f->respuestasCount);
        $this->assertTrue($f->tieneActa);
        $this->assertSame('en_subsanacion', $f->nivelEstado);
        $this->assertSame('En subsanación', $f->nivelEstadoEtiqueta);
        $this->assertNotSame('', $f->nivel);

        $g = collect($this->listar()['filas'])->firstWhere('escuelaNivelId', $otro->id);
        $this->assertSame([0, 0, false, null], [$g->observacionesCount, $g->respuestasCount, $g->tieneActa, $g->subsanarHasta]);
    }

    public function test_busca_por_escuela_y_por_nivel_escapando_comodines(): void
    {
        $this->realizada('Instituto Cumbres', '2026-01-01');
        $this->realizada('Colegio 100% Real', '2026-01-02');
        $this->realizada('Colegio Valle', '2026-01-03');

        $this->assertSame(['Instituto Cumbres'], array_map(fn ($f) => $f->escuela, $this->listar(['cumbres'])['filas']));
        $this->assertSame(['Colegio 100% Real'], array_map(fn ($f) => $f->escuela, $this->listar(['100%'])['filas']));
        $this->assertSame([], $this->listar(['%x%'])['filas']);
        $nivel = $this->listar()['filas'][0]->nivel;
        $this->assertCount(3, $this->listar([strtolower($nivel)])['filas']);
    }

    public function test_filtra_por_resultado(): void
    {
        $this->realizada('Colegio Cumple', '2026-01-01', 'cumple');
        $this->realizada('Colegio Observado', '2026-01-02', 'con_observaciones', 'en_subsanacion');

        $this->assertSame(['Colegio Observado'], array_map(fn ($f) => $f->escuela, $this->listar(['', 'con_observaciones'])['filas']));
        $this->assertSame(['Colegio Cumple'], array_map(fn ($f) => $f->escuela, $this->listar(['', 'cumple'])['filas']));
        $this->assertCount(2, $this->listar(['', null])['filas']);
    }

    public function test_el_rango_de_fechas_es_inclusivo_en_ambos_extremos(): void
    {
        foreach (['2026-01-09' => 'A', '2026-01-10' => 'B', '2026-01-20' => 'C', '2026-01-21' => 'D'] as $fecha => $n) {
            $this->realizada("Colegio $n", $fecha);
        }

        $nombres = fn (array $r) => array_map(fn ($f) => $f->escuela, $r['filas']);

        $this->assertSame(['Colegio C', 'Colegio B'], $nombres($this->listar(['', null, '2026-01-10', '2026-01-20'])));
        $this->assertSame(['Colegio D', 'Colegio C'], $nombres($this->listar(['', null, '2026-01-20'])));
        $this->assertSame(['Colegio B', 'Colegio A'], $nombres($this->listar(['', null, null, '2026-01-10'])));
        $this->assertSame(['Colegio B'], $nombres($this->listar(['', null, '2026-01-10', '2026-01-10'])));
    }

    public function test_datos_invalidos_se_rechazan(): void
    {
        foreach ([['', 'rechazado'], ['', null, 'no-es-fecha'], ['', null, null, '2026-13-45'], ['', null, '2026-02-01', '2026-01-01']] as $args) {
            try {
                $this->listar($args);
                $this->fail('debía lanzar DatosInvalidos');
            } catch (DatosInvalidos $e) {
                $this->assertNotEmpty($e->errores);
            }
        }
    }

    public function test_oculta_escuelas_archivadas_y_borradores(): void
    {
        [$archivada] = $this->realizada('Colegio Archivado', '2026-01-01');
        DB::table('escuelas')->where('id', $archivada->escuela_id)->update(['archivado_at' => now()]);
        [$borrador] = $this->realizada('Colegio Borrador', '2026-01-02', 'cumple', 'en_captura');
        $this->realizada('Colegio Visible', '2026-01-03');

        $this->assertSame(['Colegio Visible'], array_map(fn ($f) => $f->escuela, $this->listar()['filas']));
        $this->assertSame(1, $this->listar()['total']);
    }

    public function test_tope_de_filas_con_total_completo(): void
    {
        $nivel = $this->nivelEnRevision('visita_aprobada');
        $filas = [];
        for ($i = 1; $i <= ListarVisitasRealizadas::LIMITE + 2; $i++) {
            $filas[] = ['escuela_nivel_id' => $nivel->id, 'numero' => $i, 'programada_para' => now(), 'inspector' => 'I', 'estado' => 'realizada',
                'resultado' => 'cumple', 'realizada_el' => '2026-01-01', 'programada_por' => $this->supervisor()->id];
        }
        DB::table('visitas_inspeccion')->insert($filas);

        $r = $this->listar();

        $this->assertCount(ListarVisitasRealizadas::LIMITE, $r['filas']);
        $this->assertSame(ListarVisitasRealizadas::LIMITE + 2, $r['total']);
    }

    public function test_solo_el_supervisor_puede_listar(): void
    {
        foreach ([$this->revisor(), User::factory()->create()] as $actor) {
            try {
                app(ListarVisitasRealizadas::class)->ejecutar($actor);
                $this->fail('debía lanzar PrecondicionIncumplida');
            } catch (PrecondicionIncumplida $e) {
                $this->assertSame('rol', $e->etapaFaltante);
            }
        }
    }

    public function test_el_numero_de_consultas_no_crece_con_las_visitas(): void
    {
        $contar = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->listar();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };
        $this->realizada('Colegio 1', '2026-01-01');
        $una = $contar();
        foreach (range(2, 5) as $i) {
            $this->realizada("Colegio $i", '2026-01-0'.$i);
        }

        $this->assertSame($una, $contar());
    }
}
