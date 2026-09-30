<?php

namespace Tests\Feature;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Http\Controllers\Tramite\DescargarDocumentoNivelController;
use App\Models\DocumentoEscuelaNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use App\Models\TipoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

class DocumentoNivelDownloadTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    private Solicitante $dueno;

    private EscuelaNivel $escuelaNivel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dueno = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $this->dueno->id]);
        $this->completarPaso2($escuela->id);
        $this->escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
            'turno' => 'matutino',
            'tipo_alumnado' => 'mixto',
        ]);
    }

    private function descargar(string $clave)
    {
        return $this->get(route('tramite.paso2-nivel-documentos.descargar', ['escuelaNivel' => $this->escuelaNivel->id, 'clave' => $clave]));
    }

    public function test_el_dueno_descarga_el_documento_del_nivel(): void
    {
        app(RegistrarDocumento::class)->ejecutar($this->escuelaNivel->escuela_id, 'acervo_bibliografico_primaria', UploadedFile::fake()->createWithContent('acervo.pdf', 'CONTENIDO-ACERVO'), new DatosDocumento, $this->escuelaNivel->id);
        $this->actingAs($this->dueno->user);

        $response = $this->descargar('acervo_bibliografico_primaria');

        $response->assertOk();
        $this->assertSame('CONTENIDO-ACERVO', $response->streamedContent());
    }

    /** Review Focus 2. */
    public function test_un_no_dueno_recibe_403(): void
    {
        app(RegistrarDocumento::class)->ejecutar($this->escuelaNivel->escuela_id, 'acervo_bibliografico_primaria', UploadedFile::fake()->create('acervo.pdf', 10, 'application/pdf'), new DatosDocumento, $this->escuelaNivel->id);
        $this->actingAs(Solicitante::factory()->create()->user);

        $this->descargar('acervo_bibliografico_primaria')->assertForbidden();
    }

    public function test_404_si_el_documento_no_esta_capturado(): void
    {
        $this->actingAs($this->dueno->user);

        $this->descargar('acervo_bibliografico_primaria')->assertNotFound();
    }

    public function test_404_si_la_clave_no_aplica_al_nivel_aunque_exista_el_archivo(): void
    {
        $ruta = "escuela_nivel/{$this->escuelaNivel->id}/inventario_laboratorio-legacy.pdf";
        DocumentoEscuelaNivel::create([
            'escuela_nivel_id' => $this->escuelaNivel->id,
            'tipo_documento_id' => TipoDocumento::where('clave', 'inventario_laboratorio')->value('id'),
            'archivo_path' => $ruta,
        ]);
        Storage::disk('documentos')->put($ruta, 'CONTENIDO');
        $this->actingAs($this->dueno->user);

        $this->descargar('inventario_laboratorio')->assertNotFound();
    }

    public function test_un_id_de_nivel_no_numerico_da_404(): void
    {
        $this->actingAs($this->dueno->user)
            ->get('/tramite/paso2/nivel/abc/documentos/acervo_bibliografico_primaria/archivo')
            ->assertNotFound();
    }

    public function test_la_ruta_la_atiende_un_controlador_no_un_closure(): void
    {
        $this->assertSame(
            DescargarDocumentoNivelController::class,
            Route::getRoutes()->getByName('tramite.paso2-nivel-documentos.descargar')->getActionName(),
        );
    }
}
