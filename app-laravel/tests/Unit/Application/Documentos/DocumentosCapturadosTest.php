<?php

namespace Tests\Unit\Application\Documentos;

use App\Application\Documentos\DocumentosCapturados;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoEscuelaNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use App\Models\TipoDocumento;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentosCapturadosTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuela(): Escuela
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    public function test_devuelve_solo_los_documentos_aplicables_ya_capturados(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $tipoIne = TipoDocumento::where('clave', 'ine')->firstOrFail();

        DocumentoEscuela::create([
            'escuela_id' => $escuela->id,
            'tipo_documento_id' => $tipoIne->id,
            'archivo_path' => "escuela/{$escuela->id}/ine-ABC.pdf",
        ]);

        $resultado = app(DocumentosCapturados::class)->paraEscuela($escuela->id, 'fisica');

        $this->assertTrue($resultado->has('ine'));
        $this->assertSame("escuela/{$escuela->id}/ine-ABC.pdf", $resultado['ine']['archivoPath']);
        $this->assertSame('ine-ABC.pdf', $resultado['ine']['nombreArchivo']);
        $this->assertFalse($resultado->has('acta_nacimiento'));
    }

    public function test_no_incluye_claves_no_capturadas(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();

        $resultado = app(DocumentosCapturados::class)->paraEscuela($escuela->id, 'fisica');

        $this->assertCount(0, $resultado);
    }

    public function test_para_escuela_nivel_devuelve_solo_los_documentos_aplicables_del_nivel(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
        foreach (['formato_solicitud' => 'formato-A.pdf', 'inventario_laboratorio' => 'inventario-B.pdf'] as $clave => $archivo) {
            DocumentoEscuelaNivel::create([
                'escuela_nivel_id' => $escuelaNivel->id,
                'tipo_documento_id' => TipoDocumento::where('clave', $clave)->value('id'),
                'archivo_path' => "escuela_nivel/{$escuelaNivel->id}/{$archivo}",
            ]);
        }

        $resultado = app(DocumentosCapturados::class)->paraEscuelaNivel($escuelaNivel->id);

        $this->assertSame(['formato_solicitud'], $resultado->keys()->all(), 'inventario_laboratorio es de Secundaria: no aplica a Primaria');
        $this->assertSame('formato-A.pdf', $resultado['formato_solicitud']['nombreArchivo']);
    }
}
