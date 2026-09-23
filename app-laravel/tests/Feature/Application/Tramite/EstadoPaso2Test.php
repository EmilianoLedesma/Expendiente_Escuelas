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
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

class EstadoPaso2Test extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    private Escuela $escuela;

    protected function setUp(): void
    {
        parent::setUp();
        (new TiposDocumentosSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
    }

    public function test_sin_responsable_falta_la_etapa_responsable(): void
    {
        $estado = app(EstadoPaso2::class);

        $this->assertFalse($estado->responsableCapturado($this->escuela->id));
        $this->assertFalse($estado->documentosCompletos($this->escuela->id));
        $this->assertFalse($estado->puedeSeleccionarNiveles($this->escuela->id));
        $this->assertSame(EstadoPaso2::RESPONSABLE, $estado->etapaFaltante($this->escuela->id));
    }

    public function test_con_responsable_sin_documentos_falta_la_etapa_documentos(): void
    {
        (new RegistrarResponsableLegal)->ejecutar($this->escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana'));
        $estado = app(EstadoPaso2::class);

        $this->assertTrue($estado->responsableCapturado($this->escuela->id));
        $this->assertFalse($estado->documentosCompletos($this->escuela->id));
        $this->assertSame(EstadoPaso2::DOCUMENTOS, $estado->etapaFaltante($this->escuela->id));
    }

    public function test_documentos_completos_pero_vencidos_falta_la_etapa_documentos(): void
    {
        $this->completarPaso2($this->escuela->id);
        app(RegistrarDocumento::class)->ejecutar($this->escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'), new DatosDocumento(
            fechaEmision: now()->subDays(60)->toDateString(),
        ));
        $estado = app(EstadoPaso2::class);

        $this->assertTrue($estado->documentosCompletos($this->escuela->id));
        $this->assertFalse($estado->documentosVigentes($this->escuela->id));
        $this->assertFalse($estado->puedeSeleccionarNiveles($this->escuela->id));
        $this->assertSame(EstadoPaso2::DOCUMENTOS, $estado->etapaFaltante($this->escuela->id));
    }

    public function test_paso2_completo_permite_seleccionar_niveles(): void
    {
        $this->completarPaso2($this->escuela->id);
        $estado = app(EstadoPaso2::class);

        $this->assertTrue($estado->puedeSeleccionarNiveles($this->escuela->id));
        $this->assertNull($estado->etapaFaltante($this->escuela->id));
    }
}
