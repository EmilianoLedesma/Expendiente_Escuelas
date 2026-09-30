<?php

namespace Tests\Unit\Models;

use App\Models\DocumentoEscuelaNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\ReciboPagoDerechos;
use App\Models\Solicitante;
use App\Models\TipoDocumento;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentoEscuelaNivelTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_recibo_extiende_al_documento_del_nivel_con_pk_no_estandar(): void
    {
        (new TiposDocumentosSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
        $documento = DocumentoEscuelaNivel::create([
            'escuela_nivel_id' => $escuelaNivel->id,
            'tipo_documento_id' => TipoDocumento::where('clave', 'recibo_pago_derechos')->value('id'),
            'archivo_path' => 'x.pdf',
        ]);

        $recibo = ReciboPagoDerechos::create(['documento_escuela_nivel_id' => $documento->id, 'folio' => 'F-1', 'monto' => '1500.00', 'fecha_pago' => '2026-09-01']);

        $this->assertSame('pendiente', $documento->fresh()->estado_validacion);
        $this->assertSame($documento->id, $recibo->documento_escuela_nivel_id);
        $this->assertSame('F-1', $documento->reciboPagoDerechos->folio);
    }
}
