<?php

namespace Tests\Feature\Application\Visita;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Visita\ActualizarVisita;
use App\Application\Visita\ProgramarVisita;
use App\Models\VisitaInspeccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class ActualizarVisitaTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private function programada($sup): array
    {
        $nivel = $this->nivelEnRevision('aprobado');
        $id = app(ProgramarVisita::class)->ejecutar($sup, $nivel->id, now()->addWeek(), 'Inspector Uno');

        return [$nivel, $id];
    }

    public function test_updates_fields_without_changing_estado(): void
    {
        $sup = $this->supervisor();
        [$nivel, $id] = $this->programada($sup);
        $estado = DB::table('escuela_niveles')->where('id', $nivel->id)->value('estado_id');
        $historial = DB::table('historial_estados_expediente')->count();

        app(ActualizarVisita::class)->ejecutar($sup, $id, now()->addDays(10), ' Otra Persona ');

        $v = VisitaInspeccion::findOrFail($id);
        $this->assertSame('Otra Persona', $v->inspector);
        $this->assertSame(now()->addDays(10)->toDateString(), $v->programada_para->toDateString());
        $this->assertSame($estado, DB::table('escuela_niveles')->where('id', $nivel->id)->value('estado_id'));
        $this->assertSame($historial, DB::table('historial_estados_expediente')->count());
    }

    public function test_revisor_forbidden(): void
    {
        [, $id] = $this->programada($this->supervisor());

        $this->expectException(PrecondicionIncumplida::class);
        app(ActualizarVisita::class)->ejecutar($this->revisor(), $id, now()->addDays(2), 'I');
    }

    public function test_only_on_programada(): void
    {
        $sup = $this->supervisor();
        [, $id] = $this->programada($sup);
        DB::table('visitas_inspeccion')->where('id', $id)->update(['estado' => 'realizada', 'resultado' => 'cumple', 'realizada_el' => now()->toDateString()]);

        $this->expectException(PrecondicionIncumplida::class);
        app(ActualizarVisita::class)->ejecutar($sup, $id, now()->addDays(2), 'I');
    }

    public function test_validates_inputs(): void
    {
        $sup = $this->supervisor();
        [, $id] = $this->programada($sup);

        $this->expectException(DatosInvalidos::class);
        app(ActualizarVisita::class)->ejecutar($sup, $id, now()->subDays(2), 'L', 'I');
    }
}
