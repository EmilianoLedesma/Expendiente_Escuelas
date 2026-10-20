<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Application\Tramite\EstadoPaso2;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CompletaPaso2;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\Concerns\DictamenDeEjemplo;
use Tests\TestCase;

/**
 * Contratos del Paso 2 en el orden de 2026-10-20 (spec docs/specs/2026-10-20-paso2-integracion-end-to-end.md §4):
 * responsable → niveles → documentos generales. Elegir niveles ya no depende de los documentos.
 */
class EstadoPaso2Test extends TestCase
{
    use CompletaPaso2;
    use ConNivelParaResponsables;
    use RefreshDatabase;

    private Escuela $escuela;

    protected function setUp(): void
    {
        parent::setUp();
        (new TiposDocumentosSeeder)->run();
        (new CatalogoMinimoSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
    }

    private function estado(): EstadoPaso2
    {
        return app(EstadoPaso2::class);
    }

    private function registrarResponsable(): void
    {
        (new RegistrarResponsableLegal)->ejecutar($this->escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana'));
    }

    public function test_sin_responsable_falta_la_etapa_responsable_y_no_se_eligen_niveles(): void
    {
        $estado = $this->estado();

        $this->assertFalse($estado->responsableCapturado($this->escuela->id));
        $this->assertFalse($estado->puedeSeleccionarNiveles($this->escuela->id));
        $this->assertFalse($estado->puedeCapturarDocumentosGenerales($this->escuela->id));
        $this->assertFalse($estado->generalesSatisfechos($this->escuela->id));
        $this->assertSame(EstadoPaso2::RESPONSABLE, $estado->etapaFaltante($this->escuela->id));
    }

    public function test_con_responsable_se_eligen_niveles_sin_documentos(): void
    {
        $this->registrarResponsable();
        $estado = $this->estado();

        $this->assertTrue($estado->puedeSeleccionarNiveles($this->escuela->id));
        $this->assertFalse($estado->nivelesSeleccionados($this->escuela->id));
        $this->assertFalse($estado->puedeCapturarDocumentosGenerales($this->escuela->id));
        $this->assertSame(EstadoPaso2::NIVELES, $estado->etapaFaltante($this->escuela->id));
    }

    public function test_con_responsable_y_niveles_se_capturan_los_documentos_generales(): void
    {
        $this->registrarResponsable();
        $this->nivelEn($this->escuela, 'primaria');
        $estado = $this->estado();

        $this->assertTrue($estado->nivelesSeleccionados($this->escuela->id));
        $this->assertTrue($estado->puedeCapturarDocumentosGenerales($this->escuela->id));
        $this->assertFalse($estado->documentosCompletos($this->escuela->id));
        $this->assertFalse($estado->generalesSatisfechos($this->escuela->id));
        $this->assertSame(EstadoPaso2::DOCUMENTOS, $estado->etapaFaltante($this->escuela->id));
    }

    public function test_documentos_completos_pero_vencidos_falta_la_etapa_documentos(): void
    {
        $this->nivelEn($this->escuela, 'primaria');
        $this->completarPaso2($this->escuela->id);
        app(RegistrarDocumento::class)->ejecutar($this->escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'), DictamenDeEjemplo::vencido());
        $estado = $this->estado();

        $this->assertTrue($estado->documentosCompletos($this->escuela->id));
        $this->assertFalse($estado->documentosVigentes($this->escuela->id));
        $this->assertTrue($estado->puedeSeleccionarNiveles($this->escuela->id), 'Un documento vencido no impide editar los niveles.');
        $this->assertFalse($estado->generalesSatisfechos($this->escuela->id));
        $this->assertSame(EstadoPaso2::DOCUMENTOS, $estado->etapaFaltante($this->escuela->id));
    }

    public function test_un_dictamen_emitido_hace_mucho_sin_fecha_expresa_no_bloquea_el_avance(): void
    {
        $this->nivelEn($this->escuela, 'primaria');
        $this->completarPaso2($this->escuela->id);
        $dictamen = DictamenDeEjemplo::conVigencia('sin_fecha_expresa');
        app(RegistrarDocumento::class)->ejecutar($this->escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'), new DatosDocumento(
            fechaEmision: now()->subDays(400)->toDateString(),
            autoridadExpide: $dictamen->autoridadExpide,
            folioDictamen: $dictamen->folioDictamen,
            giroAutorizado: $dictamen->giroAutorizado,
            tipoVigencia: 'sin_fecha_expresa',
        ));
        $estado = $this->estado();

        $this->assertTrue($estado->documentosVigentes($this->escuela->id));
        $this->assertTrue($estado->generalesSatisfechos($this->escuela->id));
    }

    public function test_paso2_completo_satisface_los_generales(): void
    {
        $this->nivelEn($this->escuela, 'primaria');
        $this->completarPaso2($this->escuela->id);
        $estado = $this->estado();

        $this->assertTrue($estado->generalesSatisfechos($this->escuela->id));
        $this->assertNull($estado->etapaFaltante($this->escuela->id));
    }

    /** Expediente histórico: documentos completos capturados cuando aún no existían niveles. */
    public function test_historico_documentos_completos_sin_niveles_pide_los_niveles(): void
    {
        $this->completarPaso2($this->escuela->id);
        $estado = $this->estado();

        $this->assertFalse($estado->nivelesSeleccionados($this->escuela->id));
        $this->assertTrue($estado->documentosCompletos($this->escuela->id));
        $this->assertFalse($estado->generalesSatisfechos($this->escuela->id));
        $this->assertSame(EstadoPaso2::NIVELES, $estado->etapaFaltante($this->escuela->id));
    }
}
