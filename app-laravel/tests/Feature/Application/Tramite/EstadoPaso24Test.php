<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\Tramite\EstadoPaso24;
use App\Models\DocumentoEscuelaNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use App\Models\TipoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

class EstadoPaso24Test extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    private function nivel(string $clave = 'primaria', ?string $turno = null, ?string $tipoAlumnado = null): EscuelaNivel
    {
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
        $this->completarPaso2($escuela->id);

        return EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', $clave)->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
            'turno' => $turno,
            'tipo_alumnado' => $tipoAlumnado,
        ]);
    }

    private function capturar(EscuelaNivel $escuelaNivel, string $clave, ?string $fechaVigencia = null): DocumentoEscuelaNivel
    {
        return DocumentoEscuelaNivel::create([
            'escuela_nivel_id' => $escuelaNivel->id,
            'tipo_documento_id' => TipoDocumento::where('clave', $clave)->value('id'),
            'archivo_path' => "escuela_nivel/{$escuelaNivel->id}/{$clave}.pdf",
            'fecha_vigencia' => $fechaVigencia,
        ]);
    }

    public function test_sin_turno_ni_tipo_de_alumnado_falta_la_etapa_datos(): void
    {
        $this->assertSame(EstadoPaso24::DATOS, app(EstadoPaso24::class)->etapaFaltante($this->nivel()->id));
    }

    public function test_solo_turno_sin_tipo_de_alumnado_sigue_faltando_datos(): void
    {
        $this->assertSame(EstadoPaso24::DATOS, app(EstadoPaso24::class)->etapaFaltante($this->nivel(turno: 'matutino')->id));
    }

    public function test_con_datos_y_sin_documentos_falta_la_etapa_documentos(): void
    {
        $this->assertSame(EstadoPaso24::DOCUMENTOS_NIVEL, app(EstadoPaso24::class)->etapaFaltante($this->nivel(turno: 'matutino', tipoAlumnado: 'mixto')->id));
    }

    public function test_con_datos_y_todos_los_documentos_esta_completo(): void
    {
        $escuelaNivel = $this->nivel(turno: 'matutino', tipoAlumnado: 'mixto');
        foreach (['formato_solicitud', 'recibo_pago_derechos', 'acervo_bibliografico_primaria'] as $clave) {
            $this->capturar($escuelaNivel, $clave);
        }

        $this->assertNull(app(EstadoPaso24::class)->etapaFaltante($escuelaNivel->id));
    }

    public function test_un_documento_por_nivel_vencido_deja_pendiente_la_etapa_documentos(): void
    {
        $escuelaNivel = $this->nivel(turno: 'matutino', tipoAlumnado: 'mixto');
        DB::table('tipos_documentos')->insert([
            'clave' => 'documento_nivel_con_vigencia', 'nombre' => 'Documento con vigencia', 'aplica_persona' => 'ambas',
            'ambito' => 'escuela_nivel', 'vigencia_max_dias' => 30, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['formato_solicitud', 'recibo_pago_derechos', 'acervo_bibliografico_primaria'] as $clave) {
            $this->capturar($escuelaNivel, $clave);
        }
        $conVigencia = $this->capturar($escuelaNivel, 'documento_nivel_con_vigencia', now()->toDateString());

        // Control de no vacuidad: vigente hasta hoy, 2.4 está completo.
        $this->assertNull(app(EstadoPaso24::class)->etapaFaltante($escuelaNivel->id));

        $conVigencia->update(['fecha_vigencia' => now()->subDay()->toDateString()]);

        $this->assertSame(EstadoPaso24::DOCUMENTOS_NIVEL, app(EstadoPaso24::class)->etapaFaltante($escuelaNivel->id));
    }
}
