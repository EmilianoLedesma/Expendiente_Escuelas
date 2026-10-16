<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\AsignarTramite;
use App\Application\Revision\LimiteDeRevisorAlcanzado;
use App\Models\EscuelaNivel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class AsignarTramiteTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private User $sup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = $this->supervisor();
    }

    private function asignar(int $nivelId, int $revisorId, bool $confirmar = false): void
    {
        app(AsignarTramite::class)->ejecutar($this->sup->id, $nivelId, $revisorId, $confirmar);
    }

    /** @return list<EscuelaNivel> */
    private function llenar(User $revisor, int $n = 5): array
    {
        $niveles = [];
        for ($i = 0; $i < $n; $i++) {
            $niveles[] = $nivel = $this->nivelEnRevision();
            $this->asignar($nivel->id, $revisor->id);
        }

        return $niveles;
    }

    private function revisorDe(EscuelaNivel $nivel): ?int
    {
        $id = DB::table('asignaciones_revision')->where('escuela_nivel_id', $nivel->id)->value('revisor_id');

        return $id === null ? null : (int) $id;
    }

    /** @return list<string> */
    private function bitacora(): array
    {
        return DB::table('bitacora_asignaciones')->orderBy('id')->pluck('accion')->all();
    }

    public function test_asigna_y_registra_en_bitacora_con_progreso_en_cero(): void
    {
        $rev = $this->revisor();
        $nivel = $this->nivelEnRevision();

        $this->asignar($nivel->id, $rev->id);

        $fila = DB::table('asignaciones_revision')->where('escuela_nivel_id', $nivel->id)->first();
        $this->assertSame($rev->id, (int) $fila->revisor_id);
        $this->assertSame($this->sup->id, (int) $fila->asignado_por);
        $this->assertSame(0, (int) DB::table('progreso_revision')->where('escuela_nivel_id', $nivel->id)->value('secciones_revisadas'));
        $log = DB::table('bitacora_asignaciones')->sole();
        $this->assertSame(['asignar', $rev->id, $this->sup->id, $nivel->id], [$log->accion, (int) $log->revisor_id, (int) $log->actor_id, (int) $log->escuela_nivel_id]);
    }

    public function test_mover_entre_revisores_es_reasignar_y_conserva_el_progreso(): void
    {
        [$a, $b] = [$this->revisor(), $this->revisor()];
        $nivel = $this->nivelEnRevision();
        $this->asignar($nivel->id, $a->id);
        DB::table('progreso_revision')->where('escuela_nivel_id', $nivel->id)->update(['secciones_revisadas' => 3, 'secciones_total' => 7]);

        $this->asignar($nivel->id, $b->id);

        $this->assertSame($b->id, $this->revisorDe($nivel));
        $this->assertSame(['asignar', 'reasignar'], $this->bitacora());
        $this->assertSame(3, (int) DB::table('progreso_revision')->where('escuela_nivel_id', $nivel->id)->value('secciones_revisadas'));
    }

    public function test_reasignar_al_mismo_revisor_no_hace_nada_aun_en_el_limite(): void
    {
        $rev = $this->revisor();
        $niveles = $this->llenar($rev);

        $this->asignar($niveles[0]->id, $rev->id);

        $this->assertCount(5, $this->bitacora());
        $this->assertSame(5, DB::table('asignaciones_revision')->where('revisor_id', $rev->id)->count());
    }

    public function test_el_sexto_lanza_limite_y_no_cambia_nada(): void
    {
        $rev = $this->revisor();
        $this->llenar($rev);
        $sexto = $this->nivelEnRevision();

        try {
            $this->asignar($sexto->id, $rev->id);
            $this->fail('Se esperaba LimiteDeRevisorAlcanzado.');
        } catch (LimiteDeRevisorAlcanzado $e) {
            $this->assertSame([$rev->id, 5], [$e->revisorId, $e->limite]);
        }

        $this->assertNull($this->revisorDe($sexto));
        $this->assertSame(5, DB::table('asignaciones_revision')->where('revisor_id', $rev->id)->count());
        $this->assertCount(5, $this->bitacora());
        $this->assertSame(0, DB::table('progreso_revision')->where('escuela_nivel_id', $sexto->id)->count());
    }

    public function test_con_confirmacion_desasigna_el_de_menor_progreso_y_lo_registra(): void
    {
        $rev = $this->revisor();
        $niveles = $this->llenar($rev);
        foreach ($niveles as $i => $n) {
            DB::table('progreso_revision')->where('escuela_nivel_id', $n->id)->update(['secciones_revisadas' => $i === 2 ? 1 : 4, 'secciones_total' => 7]);
        }
        // La prioridad solo desempata: el de menor progreso sale aunque sea alta.
        DB::table('prioridades_revision')->insert(['escuela_nivel_id' => $niveles[2]->id, 'prioridad' => 'alta']);
        $sexto = $this->nivelEnRevision();

        $this->asignar($sexto->id, $rev->id, true);

        $this->assertSame($rev->id, $this->revisorDe($sexto));
        $this->assertNull($this->revisorDe($niveles[2]));
        $this->assertSame(1, (int) DB::table('progreso_revision')->where('escuela_nivel_id', $niveles[2]->id)->value('secciones_revisadas'));
        $log = DB::table('bitacora_asignaciones')->where('accion', 'desasignar_sobrecarga')->sole();
        $this->assertSame([$niveles[2]->id, $rev->id, $this->sup->id], [(int) $log->escuela_nivel_id, (int) $log->revisor_id, (int) $log->actor_id]);
        $this->assertSame(5, DB::table('asignaciones_revision')->where('revisor_id', $rev->id)->count());
    }

    public function test_a_igual_progreso_sale_el_de_menor_prioridad_y_sin_fila_cuenta_como_media(): void
    {
        $rev = $this->revisor();
        $niveles = $this->llenar($rev);
        DB::table('prioridades_revision')->insert([
            ['escuela_nivel_id' => $niveles[0]->id, 'prioridad' => 'alta'],
            ['escuela_nivel_id' => $niveles[1]->id, 'prioridad' => 'baja'],
            ['escuela_nivel_id' => $niveles[2]->id, 'prioridad' => 'alta'],
        ]);
        // El más reciente sin fila de prioridad (media) no debe ganarle a la baja.
        $this->asignar($this->nivelEnRevision()->id, $rev->id, true);

        $this->assertNull($this->revisorDe($niveles[1]));
    }

    public function test_a_igual_progreso_y_prioridad_sale_el_asignado_mas_recientemente(): void
    {
        $rev = $this->revisor();
        $niveles = $this->llenar($rev);
        foreach ($niveles as $i => $n) {
            DB::table('asignaciones_revision')->where('escuela_nivel_id', $n->id)->update(['asignado_at' => now()->subDays(10 - $i)]);
        }
        DB::table('asignaciones_revision')->where('escuela_nivel_id', $niveles[1]->id)->update(['asignado_at' => now()->subDay()]);

        $this->asignar($this->nivelEnRevision()->id, $rev->id, true);

        $this->assertNull($this->revisorDe($niveles[1]));
    }

    public function test_rechaza_niveles_que_no_estan_en_revision(): void
    {
        $rev = $this->revisor();
        foreach (['en_captura', 'preregistro', 'con_observaciones', 'aprobado', 'rechazado'] as $estado) {
            $nivel = $this->nivelEnRevision($estado);
            try {
                $this->asignar($nivel->id, $rev->id);
                $this->fail("Se esperaba PrecondicionIncumplida para {$estado}.");
            } catch (PrecondicionIncumplida $e) {
                $this->assertSame('en_revision', $e->etapaFaltante, $estado);
            }
        }
        $this->assertSame(0, DB::table('asignaciones_revision')->count());
    }

    public function test_rechaza_un_usuario_sin_rol_revisor(): void
    {
        $nivel = $this->nivelEnRevision();

        $this->expectException(PrecondicionIncumplida::class);
        $this->asignar($nivel->id, User::factory()->create()->id);
    }

    public function test_rechaza_un_actor_que_no_es_supervisor(): void
    {
        $rev = $this->revisor();
        $nivel = $this->nivelEnRevision();

        try {
            app(AsignarTramite::class)->ejecutar($rev->id, $nivel->id, $rev->id);
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame('rol', $e->etapaFaltante);
        }
        $this->assertNull($this->revisorDe($nivel));
    }

    public function test_seleccion_es_todo_o_nada(): void
    {
        $rev = $this->revisor();
        $bueno = $this->nivelEnRevision();
        $enCaptura = $this->nivelEnRevision('en_captura');

        try {
            app(AsignarTramite::class)->asignarSeleccion($this->sup->id, [$bueno->id, $enCaptura->id], $rev->id);
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida) {
        }

        $this->assertSame(0, DB::table('asignaciones_revision')->count());
        $this->assertSame([], $this->bitacora());
    }

    public function test_seleccion_cuenta_completa_contra_el_limite(): void
    {
        $rev = $this->revisor();
        $previos = $this->llenar($rev, 3);
        $seleccion = [$this->nivelEnRevision()->id, $this->nivelEnRevision()->id, $this->nivelEnRevision()->id];

        try {
            app(AsignarTramite::class)->asignarSeleccion($this->sup->id, $seleccion, $rev->id);
            $this->fail('Se esperaba LimiteDeRevisorAlcanzado.');
        } catch (LimiteDeRevisorAlcanzado) {
        }
        $this->assertSame(3, DB::table('asignaciones_revision')->count());

        app(AsignarTramite::class)->asignarSeleccion($this->sup->id, $seleccion, $rev->id, true);

        $this->assertSame(5, DB::table('asignaciones_revision')->where('revisor_id', $rev->id)->count());
        $this->assertSame(3, DB::table('asignaciones_revision')->whereIn('escuela_nivel_id', $seleccion)->count());
        $this->assertSame(2, DB::table('asignaciones_revision')->whereIn('escuela_nivel_id', array_map(fn ($n) => $n->id, $previos))->count());
        $this->assertSame(1, DB::table('bitacora_asignaciones')->where('accion', 'desasignar_sobrecarga')->count());
    }

    public function test_una_seleccion_mayor_que_el_limite_se_rechaza_aun_confirmada(): void
    {
        $rev = $this->revisor();
        $seleccion = array_map(fn () => $this->nivelEnRevision()->id, range(1, 6));

        $this->expectException(LimiteDeRevisorAlcanzado::class);
        app(AsignarTramite::class)->asignarSeleccion($this->sup->id, $seleccion, $rev->id, true);
    }

    public function test_la_sobrecarga_nunca_desasigna_un_nivel_de_la_propia_seleccion(): void
    {
        $rev = $this->revisor();
        $niveles = $this->llenar($rev);
        $x = $niveles[4];
        foreach ($niveles as $i => $n) {
            DB::table('asignaciones_revision')->where('escuela_nivel_id', $n->id)->update(['asignado_at' => now()->subDays(10 - $i)]);
        }
        $y = $this->nivelEnRevision();

        app(AsignarTramite::class)->asignarSeleccion($this->sup->id, [$x->id, $y->id], $rev->id, true);

        $this->assertSame($rev->id, $this->revisorDe($x));
        $this->assertSame($rev->id, $this->revisorDe($y));
        $this->assertSame(5, DB::table('asignaciones_revision')->where('revisor_id', $rev->id)->count());
        $log = DB::table('bitacora_asignaciones')->where('accion', 'desasignar_sobrecarga')->sole();
        $this->assertSame($niveles[3]->id, (int) $log->escuela_nivel_id);
        $this->assertNull($this->revisorDe($niveles[3]));
    }

    public function test_una_seleccion_de_seis_con_uno_ya_suyo_se_rechaza_aun_confirmada(): void
    {
        $rev = $this->revisor();
        $niveles = $this->llenar($rev);
        $seleccion = [$niveles[0]->id, ...array_map(fn () => $this->nivelEnRevision()->id, range(1, 5))];

        try {
            app(AsignarTramite::class)->asignarSeleccion($this->sup->id, $seleccion, $rev->id, true);
            $this->fail('Se esperaba LimiteDeRevisorAlcanzado.');
        } catch (LimiteDeRevisorAlcanzado) {
        }

        $this->assertSame(array_map(fn ($n) => $n->id, $niveles), DB::table('asignaciones_revision')->orderBy('escuela_nivel_id')->pluck('escuela_nivel_id')->map(fn ($id) => (int) $id)->all());
        $this->assertCount(5, $this->bitacora());
    }

    public function test_el_bloqueo_del_revisor_precede_al_conteo_y_el_limite_se_sostiene(): void
    {
        $rev = $this->revisor();
        $this->llenar($rev, 4);
        [$quinto, $sexto] = [$this->nivelEnRevision(), $this->nivelEnRevision()];

        DB::enableQueryLog();
        $this->asignar($quinto->id, $rev->id);
        $log = DB::getQueryLog();
        $consultas = array_column($log, 'query');
        DB::disableQueryLog();

        $bloqueo = $this->indice($consultas, '/from "users" where .*for update/i');
        $conteo = $this->indice($consultas, '/select count\(\*\).* from "asignaciones_revision"/i');
        $this->assertNotNull($bloqueo, 'No se bloqueó la fila users del revisor.');
        $this->assertNotNull($conteo, 'No se contaron las asignaciones del revisor.');
        $this->assertContains($rev->id, $log[$bloqueo]['bindings'], 'El FOR UPDATE no fue sobre la fila del revisor.');
        $this->assertNotContains($this->sup->id, $log[$bloqueo]['bindings']);
        $this->assertLessThan($conteo, $bloqueo);

        $this->expectException(LimiteDeRevisorAlcanzado::class);
        try {
            $this->asignar($sexto->id, $rev->id);
        } finally {
            $this->assertSame(5, DB::table('asignaciones_revision')->where('revisor_id', $rev->id)->count());
        }
    }

    /** @param list<string> $consultas */
    private function indice(array $consultas, string $patron): ?int
    {
        foreach ($consultas as $i => $sql) {
            if (preg_match($patron, $sql)) {
                return $i;
            }
        }

        return null;
    }
}
