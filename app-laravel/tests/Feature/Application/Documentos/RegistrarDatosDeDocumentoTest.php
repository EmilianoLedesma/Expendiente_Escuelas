<?php

namespace Tests\Feature\Application\Documentos;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\ResponsableLegal;
use App\Models\Solicitante;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Typed data captured with an upload (ADR-007): one row per document,
 * overwritten on re-upload and deleted when a re-upload brings no data, so
 * facts from a replaced file never survive it.
 */
class RegistrarDatosDeDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private Escuela $escuela;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
        ResponsableLegal::create(['escuela_id' => $this->escuela->id, 'tipo_persona' => 'fisica']);
    }

    private function subir(string $clave, DatosDocumento $datos): void
    {
        (new RegistrarDocumento)->ejecutar($this->escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), $datos);
    }

    private function documentoEscuelaId(): int
    {
        return DocumentoEscuela::where('escuela_id', $this->escuela->id)->sole()->id;
    }

    public function test_guarda_nombre_y_curp_de_la_ine(): void
    {
        $this->subir('ine', new DatosDocumento(identidadNombre: 'PEREZ GOMEZ JUAN', identidadCurp: 'PEGJ800101HQTRML09'));

        $this->assertDatabaseHas('credenciales_ine', [
            'documento_escuela_id' => $this->documentoEscuelaId(),
            'nombre' => 'PEREZ GOMEZ JUAN',
            'curp' => 'PEGJ800101HQTRML09',
        ]);
    }

    public function test_resubir_con_datos_nuevos_reemplaza_los_anteriores(): void
    {
        $this->subir('ine', new DatosDocumento(identidadNombre: 'PEREZ JUAN', identidadCurp: 'PEGJ800101HQTRML08'));
        $this->subir('ine', new DatosDocumento(identidadNombre: 'PEREZ GOMEZ JUAN', identidadCurp: 'PEGJ800101HQTRML09'));

        $this->assertDatabaseCount('credenciales_ine', 1);
        $this->assertDatabaseHas('credenciales_ine', ['nombre' => 'PEREZ GOMEZ JUAN', 'curp' => 'PEGJ800101HQTRML09']);
    }

    public function test_resubir_sin_datos_borra_los_del_archivo_anterior(): void
    {
        $this->subir('ine', new DatosDocumento(identidadNombre: 'PEREZ JUAN', identidadCurp: 'PEGJ800101HQTRML08'));
        $this->subir('ine', new DatosDocumento);

        $this->assertDatabaseCount('credenciales_ine', 0);
    }

    public function test_guarda_datos_de_la_constancia_de_curp(): void
    {
        $this->subir('constancia_curp', new DatosDocumento(identidadNombre: 'JUAN PEREZ GOMEZ', identidadCurp: 'PEGJ800101HQTRML09'));

        $this->assertDatabaseHas('constancias_curp', [
            'documento_escuela_id' => $this->documentoEscuelaId(),
            'nombre' => 'JUAN PEREZ GOMEZ',
            'curp' => 'PEGJ800101HQTRML09',
        ]);
        $this->assertDatabaseCount('credenciales_ine', 0);
    }

    public function test_guarda_datos_de_la_constancia_de_situacion_fiscal(): void
    {
        $this->subir('constancia_situacion_fiscal', new DatosDocumento(fiscalNombre: 'JUAN PEREZ GOMEZ', fiscalRfc: 'PEGJ800101AB1'));

        $this->assertDatabaseHas('constancias_situacion_fiscal', [
            'documento_escuela_id' => $this->documentoEscuelaId(),
            'nombre_razon_social' => 'JUAN PEREZ GOMEZ',
            'rfc' => 'PEGJ800101AB1',
        ]);
    }

    public function test_guarda_el_domicilio_del_certificado_de_numero_oficial(): void
    {
        $this->subir('certificado_numero_oficial', new DatosDocumento(
            domicilioCalle: 'Av. Juárez',
            domicilioNumeroExt: '12',
            domicilioColonia: 'Centro',
            domicilioMunicipio: 'Querétaro',
            domicilioCodigoPostal: '76000',
        ));

        $documento = DocumentoPlantel::where('plantel_id', $this->escuela->plantel_id)->sole();
        $this->assertDatabaseHas('certificados_numero_oficial', [
            'documento_plantel_id' => $documento->id,
            'calle' => 'Av. Juárez',
            'numero_ext' => '12',
            'colonia' => 'Centro',
            'municipio' => 'Querétaro',
            'codigo_postal' => '76000',
        ]);
    }

    public function test_el_catalogo_incluye_las_constancias_de_curp_y_situacion_fiscal(): void
    {
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'constancia_curp', 'aplica_persona' => 'ambas', 'ambito' => 'escuela']);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'constancia_situacion_fiscal', 'aplica_persona' => 'ambas', 'ambito' => 'escuela']);
    }
}
