<?php

namespace Tests\Feature\Application\Visita;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Visita\AceptarSubsanacion;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class AceptarSubsanacionTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private EscuelaNivel $nivel;

    private function preparar(string $estado = 'en_subsanacion', string $resultado = 'con_observaciones'): int
    {
        $this->nivel = $this->nivelEnRevision($estado);
        $sup = $this->supervisor();

        return DB::table('visitas_inspeccion')->insertGetId([
            'escuela_nivel_id' => $this->nivel->id, 'numero' => 1, 'programada_para' => now()->subDays(3),
            'inspector' => 'Y', 'estado' => 'realizada', 'resultado' => $resultado, 'realizada_el' => now()->subDay()->toDateString(),
            'subsanar_hasta' => $resultado === 'con_observaciones' ? now()->addDays(4)->toDateString() : null,
            'programada_por' => $sup->id, 'registrada_por' => $sup->id,
        ]);
    }

    private function estadoDe(): string
    {
        return DB::table('escuela_niveles')->join('estados_expediente as e', 'e.id', '=', 'escuela_niveles.estado_id')
            ->where('escuela_niveles.id', $this->nivel->id)->value('e.clave');
    }

    public function test_supervisor_moves_nivel_to_visita_aprobada_with_historial(): void
    {
        $id = $this->preparar();
        $sup = $this->supervisor();

        app(AceptarSubsanacion::class)->ejecutar($sup, $id);

        $this->assertSame('visita_aprobada', $this->estadoDe());
        $this->assertDatabaseHas('historial_estados_expediente', [
            'escuela_nivel_id' => $this->nivel->id,
            'estado_id' => DB::table('estados_expediente')->where('clave', 'visita_aprobada')->value('id'),
            'usuario_sedeq_id' => $sup->id,
            'comentario' => 'Subsanación aceptada',
        ]);
    }

    public function test_wrong_estado_rejected(): void
    {
        $id = $this->preparar('visita_programada');

        $this->expectException(DatosInvalidos::class);
        app(AceptarSubsanacion::class)->ejecutar($this->supervisor(), $id);
    }

    public function test_visit_that_complies_rejected(): void
    {
        $id = $this->preparar('en_subsanacion', 'cumple');

        $this->expectException(DatosInvalidos::class);
        app(AceptarSubsanacion::class)->ejecutar($this->supervisor(), $id);
    }

    public function test_not_latest_visit_rejected(): void
    {
        $id = $this->preparar();
        $sup = $this->supervisor();
        DB::table('visitas_inspeccion')->insert([
            'escuela_nivel_id' => $this->nivel->id, 'numero' => 2, 'programada_para' => now(),
            'inspector' => 'Y', 'estado' => 'programada', 'programada_por' => $sup->id,
        ]);

        $this->expectException(DatosInvalidos::class);
        app(AceptarSubsanacion::class)->ejecutar($sup, $id);
    }

    public function test_revisor_rejected(): void
    {
        $id = $this->preparar();

        $this->expectException(PrecondicionIncumplida::class);
        app(AceptarSubsanacion::class)->ejecutar($this->revisor(), $id);
    }

    public function test_applicant_and_stranger_rejected(): void
    {
        $id = $this->preparar();

        foreach ([$this->nivel->escuela->solicitante->user, Solicitante::factory()->create()->user] as $u) {
            try {
                app(AceptarSubsanacion::class)->ejecutar($u, $id);
                $this->fail('debía rechazar');
            } catch (PrecondicionIncumplida) {
                $this->assertSame('en_subsanacion', $this->estadoDe());
            }
        }
    }
}
