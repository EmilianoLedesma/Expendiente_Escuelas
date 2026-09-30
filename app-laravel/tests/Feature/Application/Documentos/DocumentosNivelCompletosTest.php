<?php

namespace Tests\Feature\Application\Documentos;

use App\Application\Documentos\DocumentosNivelCompletos;
use App\Models\DocumentoEscuelaNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use App\Models\TipoDocumento;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DocumentosNivelCompletosTest extends TestCase
{
    use RefreshDatabase;

    private Escuela $escuela;

    protected function setUp(): void
    {
        parent::setUp();
        (new CatalogoMinimoSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
    }

    private function nivel(string $clave): EscuelaNivel
    {
        return EscuelaNivel::create([
            'escuela_id' => $this->escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', $clave)->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
    }

    private function capturar(EscuelaNivel $escuelaNivel, string $clave): void
    {
        DocumentoEscuelaNivel::create([
            'escuela_nivel_id' => $escuelaNivel->id,
            'tipo_documento_id' => TipoDocumento::where('clave', $clave)->value('id'),
            'archivo_path' => "escuela_nivel/{$escuelaNivel->id}/{$clave}.pdf",
        ]);
    }

    private function servicio(): DocumentosNivelCompletos
    {
        return app(DocumentosNivelCompletos::class);
    }

    public function test_inicial_y_preescolar_solo_piden_formato_y_recibo(): void
    {
        (new TiposDocumentosSeeder)->run();

        foreach (['inicial', 'preescolar'] as $clave) {
            $this->assertSame(['formato_solicitud', 'recibo_pago_derechos'], $this->servicio()->clavesAplicables($this->nivel($clave)->id), $clave);
        }
    }

    public function test_primaria_pide_su_acervo_bibliografico(): void
    {
        (new TiposDocumentosSeeder)->run();

        $this->assertSame(
            ['formato_solicitud', 'recibo_pago_derechos', 'acervo_bibliografico_primaria'],
            $this->servicio()->clavesAplicables($this->nivel('primaria')->id),
        );
    }

    public function test_secundaria_pide_su_acervo_y_el_inventario_de_laboratorio(): void
    {
        (new TiposDocumentosSeeder)->run();

        $this->assertSame(
            ['formato_solicitud', 'recibo_pago_derechos', 'acervo_bibliografico_secundaria', 'inventario_laboratorio'],
            $this->servicio()->clavesAplicables($this->nivel('secundaria')->id),
        );
    }

    public function test_no_incluye_documentos_de_paso_2_2(): void
    {
        (new TiposDocumentosSeeder)->run();

        $claves = $this->servicio()->clavesAplicables($this->nivel('primaria')->id);

        $this->assertNotContains('ine', $claves);
        $this->assertNotContains('dictamen_uso_suelo', $claves);
    }

    /** Review Focus 5: dos niveles de la misma escuela llevan cuentas separadas. */
    public function test_los_pendientes_son_de_cada_nivel(): void
    {
        (new TiposDocumentosSeeder)->run();
        $primaria = $this->nivel('primaria');
        $secundaria = $this->nivel('secundaria');
        foreach (['formato_solicitud', 'recibo_pago_derechos', 'acervo_bibliografico_primaria'] as $clave) {
            $this->capturar($primaria, $clave);
        }

        $this->assertTrue($this->servicio()->paraEscuelaNivel($primaria->id));
        $this->assertSame([], $this->servicio()->clavesPendientes($primaria->id));
        $this->assertFalse($this->servicio()->paraEscuelaNivel($secundaria->id));
        $this->assertSame(
            ['formato_solicitud', 'recibo_pago_derechos', 'acervo_bibliografico_secundaria', 'inventario_laboratorio'],
            $this->servicio()->clavesPendientes($secundaria->id),
        );
    }

    public function test_catalogo_sin_documentos_por_nivel_lanza_runtime_exception(): void
    {
        // Deliberadamente sin TiposDocumentosSeeder.
        $escuelaNivel = $this->nivel('primaria');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TiposDocumentosSeeder');

        $this->servicio()->paraEscuelaNivel($escuelaNivel->id);
    }
}
