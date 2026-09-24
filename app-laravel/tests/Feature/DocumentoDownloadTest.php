<?php

namespace Tests\Feature;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Http\Controllers\Tramite\DescargarDocumentoController;
use App\Models\DocumentoEscuela;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use App\Models\TipoDocumento;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentoDownloadTest extends TestCase
{
    use RefreshDatabase;

    private Solicitante $solicitante;

    private Escuela $escuela;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
    }

    private function crearEscuela(string $tipoPersona): void
    {
        $this->solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $this->solicitante->id]);
        (new RegistrarResponsableLegal)->ejecutar($this->escuela->id, $tipoPersona === 'moral'
            ? new DatosResponsableLegal(tipoPersona: 'moral', domicilioNotificaciones: 'Calle 1', razonSocial: 'Colegio SA', nombreRepresentanteLegal: 'Juan Pérez')
            : new DatosResponsableLegal(tipoPersona: 'fisica', domicilioNotificaciones: 'Calle 1', nombre: 'Juana Pérez'));
    }

    private function capturar(string $clave, string $contenido = 'PDF'): void
    {
        (new RegistrarDocumento)->ejecutar($this->escuela->id, $clave, UploadedFile::fake()->createWithContent("{$clave}.pdf", $contenido), new DatosDocumento);
    }

    private function descargar(string $clave)
    {
        return $this->get(route('tramite.paso2-documentos.descargar', ['escuela' => $this->escuela->id, 'clave' => $clave]));
    }

    public function test_el_dueno_descarga_el_contenido_del_documento_capturado(): void
    {
        $this->crearEscuela('fisica');
        $this->capturar('ine', 'CONTENIDO-INE');
        $this->actingAs($this->solicitante->user);

        $response = $this->descargar('ine');

        $response->assertOk();
        $this->assertSame('CONTENIDO-INE', $response->streamedContent());
    }

    public function test_un_no_dueno_recibe_403(): void
    {
        $this->crearEscuela('fisica');
        $this->capturar('ine');
        $this->actingAs(Solicitante::factory()->create()->user);

        $this->descargar('ine')->assertForbidden();
    }

    public function test_404_si_el_documento_no_existe(): void
    {
        $this->crearEscuela('fisica');
        $this->actingAs($this->solicitante->user);

        $this->descargar('ine')->assertNotFound();
    }

    public function test_404_si_la_clave_no_aplica_al_tipo_persona_aunque_exista_el_archivo(): void
    {
        $this->crearEscuela('moral');
        // WS-2.4a: RegistrarDocumento ya rechaza esta escritura, así que la
        // fila se inserta directo (simulando datos preexistentes/legacy) para
        // probar que la lectura (ObtenerDocumentoCapturado) igual la filtra,
        // como defensa en profundidad.
        $tipo = TipoDocumento::where('clave', 'acta_nacimiento')->firstOrFail();
        DocumentoEscuela::create([
            'escuela_id' => $this->escuela->id,
            'tipo_documento_id' => $tipo->id,
            'archivo_path' => 'escuela/'.$this->escuela->id.'/acta_nacimiento-legacy.pdf',
            'estado_validacion' => 'pendiente',
        ]);
        Storage::disk('documentos')->put('escuela/'.$this->escuela->id.'/acta_nacimiento-legacy.pdf', 'NO-APLICA');
        $this->actingAs($this->solicitante->user);

        $this->descargar('acta_nacimiento')->assertNotFound();
    }

    public function test_404_si_la_clave_no_existe_en_el_catalogo(): void
    {
        $this->crearEscuela('fisica');
        $this->actingAs($this->solicitante->user);

        $this->descargar('no_existe')->assertNotFound();
    }

    public function test_404_sin_responsable_legal_registrado(): void
    {
        $this->solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $this->solicitante->id]);
        $this->capturar('ine');
        $this->actingAs($this->solicitante->user);

        $this->descargar('ine')->assertNotFound();
    }

    public function test_la_ruta_la_atiende_un_controlador_no_un_closure(): void
    {
        $this->assertSame(
            DescargarDocumentoController::class,
            Route::getRoutes()->getByName('tramite.paso2-documentos.descargar')->getActionName(),
        );
    }

    public function test_el_dueno_descarga_un_documento_de_ambito_plantel(): void
    {
        $this->crearEscuela('fisica');
        $this->capturar('escritura_inmueble', 'CONTENIDO-ESCRITURA');
        $this->actingAs($this->solicitante->user);

        $response = $this->descargar('escritura_inmueble');

        $response->assertOk();
        $this->assertSame('CONTENIDO-ESCRITURA', $response->streamedContent());
    }

    public function test_404_si_el_registro_existe_pero_falta_el_archivo(): void
    {
        $this->crearEscuela('fisica');
        $this->capturar('ine');
        $disco = Storage::disk('documentos');
        $disco->delete($disco->allFiles());
        $this->actingAs($this->solicitante->user);

        $this->descargar('ine')->assertNotFound();
    }
}
