<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\TramiteEditable;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\Concerns\ConTramiteDevuelto;
use Tests\TestCase;

/** WS-7a §5.1: editable = every nivel en_captura (zero niveles too); sent or mixed = not editable. */
class TramiteEditableTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use ConTramiteDevuelto;
    use RefreshDatabase;

    private function estado(string $clave): int
    {
        return (int) DB::table('estados_expediente')->where('clave', $clave)->value('id');
    }

    public function test_sin_niveles_es_editable(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        (new CatalogoMinimoSeeder)->run();

        $this->assertTrue(app(TramiteEditable::class)->esEditable($escuela->id));
    }

    public function test_todo_en_captura_es_editable_y_enviado_no(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);

        $this->assertTrue(app(TramiteEditable::class)->esEditable($escuela->id));

        $nivel->update(['estado_id' => $this->estado('en_revision')]);

        $this->assertFalse(app(TramiteEditable::class)->esEditable($escuela->id));
    }

    public function test_un_estado_mezclado_no_es_editable(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'secundaria')->value('id'),
            'estado_id' => $this->estado('en_revision'),
            'tipo_tramite' => 'alta_nueva',
        ]);

        $this->assertFalse(app(TramiteEditable::class)->esEditable($escuela->id));
    }

    /** @return array{0: Escuela, 1: EscuelaNivel, 2: EscuelaNivel} primaria y secundaria en revisión */
    private function escuelaConDosNiveles(): array
    {
        $primaria = $this->nivelDe(Solicitante::factory()->create(), 'primaria', 'en_revision');
        $secundaria = $this->nivelEn($primaria->escuela, 'secundaria', 'en_revision');

        return [$primaria->escuela, $primaria, $secundaria];
    }

    public function test_con_paso_reabierto_es_editable_aunque_haya_nivel_devuelto(): void
    {
        [$escuela, $primaria, $secundaria] = $this->escuelaConDosNiveles();
        $this->devolverTramite($escuela, [$primaria->id => ['inmueble' => 'x']], [$secundaria->id => ['inmueble']]);
        $sut = app(TramiteEditable::class);

        $this->assertTrue($sut->esEditable($escuela->id, 'inmueble', $primaria->id));
        $this->assertFalse($sut->esEditable($escuela->id, 'plantel'));
        $sut->asegurarEscuela($escuela->id, 'inmueble', $primaria->id);
    }

    public function test_paso_aceptado_sigue_bloqueado(): void
    {
        [$escuela, $primaria, $secundaria] = $this->escuelaConDosNiveles();
        $this->devolverTramite($escuela, [$primaria->id => ['inmueble' => 'x']], [$primaria->id => ['matricula'], $secundaria->id => ['inmueble']]);
        $sut = app(TramiteEditable::class);

        $this->assertFalse($sut->esEditable($escuela->id, 'matricula', $primaria->id));
        $this->assertFalse($sut->esEditable($escuela->id, 'inmueble', $secundaria->id));
        $this->expectException(PrecondicionIncumplida::class);
        $sut->asegurarEscuela($escuela->id, 'matricula', $primaria->id);
    }

    public function test_sin_paso_un_tramite_devuelto_sigue_no_editable(): void
    {
        [$escuela, $primaria, $secundaria] = $this->escuelaConDosNiveles();
        $this->devolverTramite($escuela, [$primaria->id => ['inmueble' => 'x']], [$secundaria->id => ['inmueble']]);

        $this->assertFalse(app(TramiteEditable::class)->esEditable($escuela->id));
        $this->expectException(PrecondicionIncumplida::class);
        app(TramiteEditable::class)->asegurarEscuela($escuela->id);
    }

    public function test_ronda_abierta_no_habilita_el_paso(): void
    {
        [$escuela, $primaria] = $this->escuelaConDosNiveles();
        $this->devolverTramite($escuela, [$primaria->id => ['inmueble' => 'x']]); // secundaria sigue en revisión

        $this->assertFalse(app(TramiteEditable::class)->esEditable($escuela->id, 'inmueble', $primaria->id));
    }

    public function test_asegurar_plantel_permite_paso_reabierto_de_la_escuela_duena_del_rechazo(): void
    {
        [$escuela, $primaria, $secundaria] = $this->escuelaConDosNiveles();
        $this->devolverTramite($escuela, [$primaria->id => ['documentos' => 'x']], [$secundaria->id => ['documentos']]);
        $otra = Escuela::create(['plantel_id' => $escuela->plantel_id, 'solicitante_id' => $escuela->solicitante_id]);
        $this->nivelEn($otra, 'primaria', 'en_revision');

        app(TramiteEditable::class)->asegurarPlantel($escuela->plantel_id, 'documentos', $escuela->id);
        $this->addToAssertionCount(1);
    }

    public function test_asegurar_plantel_sigue_rechazando_si_otra_escuela_enviada_y_el_paso_no_esta_reabierto(): void
    {
        [$escuela, $primaria, $secundaria] = $this->escuelaConDosNiveles();
        $this->devolverTramite($escuela, [$primaria->id => ['documentos' => 'x']], [$secundaria->id => ['documentos']]);
        $otra = Escuela::create(['plantel_id' => $escuela->plantel_id, 'solicitante_id' => $escuela->solicitante_id]);
        $this->nivelEn($otra, 'primaria', 'en_revision');
        $sut = app(TramiteEditable::class);

        try {
            $sut->asegurarPlantel($escuela->plantel_id, 'plantel', $escuela->id); // plantel no rechazado
            $this->fail('debió lanzar');
        } catch (PrecondicionIncumplida) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(PrecondicionIncumplida::class);
        $sut->asegurarPlantel($escuela->plantel_id);
    }
}
