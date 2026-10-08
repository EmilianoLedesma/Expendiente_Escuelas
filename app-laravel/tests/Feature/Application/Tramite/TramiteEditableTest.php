<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\Tramite\TramiteEditable;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use Database\Seeders\CatalogoMinimoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\TestCase;

/** WS-7a §5.1: editable = every nivel en_captura (zero niveles too); sent or mixed = not editable. */
class TramiteEditableTest extends TestCase
{
    use CapturaExpedienteConsistente;
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
}
