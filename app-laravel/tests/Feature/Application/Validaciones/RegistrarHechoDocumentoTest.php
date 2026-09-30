<?php

namespace Tests\Feature\Application\Validaciones;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Application\Validaciones\RegistrarHechoDocumento;
use App\Models\DocumentoEscuela;
use App\Models\Escuela;
use App\Models\HechoDocumento;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrarHechoDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private int $escuelaId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();

        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuelaId = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id])->id;
        (new RegistrarResponsableLegal)->ejecutar($this->escuelaId, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juan Pérez'));
    }

    private function subir(string $clave): void
    {
        app(RegistrarDocumento::class)->ejecutar($this->escuelaId, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
    }

    public function test_guarda_el_hecho_atado_al_archivo_vigente_del_documento(): void
    {
        $this->subir('ine');

        app(RegistrarHechoDocumento::class)->ejecutar($this->escuelaId, 'ine', 'nombre_titular', 'PEREZ JUAN');

        $documento = DocumentoEscuela::where('escuela_id', $this->escuelaId)->sole();
        $hecho = HechoDocumento::sole();
        $this->assertSame($this->escuelaId, $hecho->escuela_id);
        $this->assertSame($documento->tipo_documento_id, $hecho->tipo_documento_id);
        $this->assertSame($documento->archivo_path, $hecho->archivo_path);
        $this->assertSame('nombre_titular', $hecho->tipo_hecho);
        $this->assertSame('PEREZ JUAN', $hecho->valor);
        $this->assertSame('captura_manual', $hecho->metodo);
    }

    public function test_guarda_el_valor_tal_como_se_capturo_sin_normalizar(): void
    {
        $this->subir('ine');

        app(RegistrarHechoDocumento::class)->ejecutar($this->escuelaId, 'ine', 'nombre_titular', '  Pérez  Juan ');

        $this->assertSame('  Pérez  Juan ', HechoDocumento::sole()->valor);
    }

    public function test_recapturar_el_mismo_hecho_del_mismo_archivo_reemplaza_el_valor(): void
    {
        $this->subir('ine');
        $registrar = app(RegistrarHechoDocumento::class);

        $registrar->ejecutar($this->escuelaId, 'ine', 'curp', 'PEGJ800101HQTRML08');
        $registrar->ejecutar($this->escuelaId, 'ine', 'curp', 'PEGJ800101HQTRML09');

        $this->assertSame('PEGJ800101HQTRML09', HechoDocumento::sole()->valor);
    }

    public function test_un_archivo_nuevo_produce_un_hecho_nuevo_y_conserva_el_anterior(): void
    {
        $registrar = app(RegistrarHechoDocumento::class);
        $this->subir('ine');
        $registrar->ejecutar($this->escuelaId, 'ine', 'curp', 'PEGJ800101HQTRML08');

        $this->subir('ine');
        $registrar->ejecutar($this->escuelaId, 'ine', 'curp', 'PEGJ800101HQTRML09');

        $this->assertSame(2, HechoDocumento::count());
        $this->assertSame(2, HechoDocumento::distinct()->count('archivo_path'));
    }

    public function test_rechaza_si_el_documento_no_se_ha_subido(): void
    {
        $this->expectException(PrecondicionIncumplida::class);

        app(RegistrarHechoDocumento::class)->ejecutar($this->escuelaId, 'ine', 'nombre_titular', 'PEREZ JUAN');
    }

    public function test_rechaza_tipo_de_hecho_desconocido(): void
    {
        $this->subir('ine');

        try {
            app(RegistrarHechoDocumento::class)->ejecutar($this->escuelaId, 'ine', 'rfc', 'PEGJ800101AB1');
            $this->fail('Se esperaba DatosInvalidos');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('hechos.ine.rfc', $e->errores);
        }

        $this->assertSame(0, HechoDocumento::count());
    }

    public function test_rechaza_documentos_de_ambito_plantel(): void
    {
        // PENDIENTE-motor-validacion-hechos P7: plantel documents are shared
        // across escuelas; which owner their facts belong to is undecided.
        $this->subir('dictamen_uso_suelo');

        $this->expectException(DatosInvalidos::class);

        app(RegistrarHechoDocumento::class)->ejecutar($this->escuelaId, 'dictamen_uso_suelo', 'nombre_titular', 'PEREZ JUAN');
    }

    public function test_la_tabla_rechaza_tipos_de_hecho_fuera_del_catalogo(): void
    {
        $this->subir('ine');
        $documento = DocumentoEscuela::where('escuela_id', $this->escuelaId)->sole();

        $this->expectException(QueryException::class);

        HechoDocumento::create([
            'escuela_id' => $this->escuelaId,
            'tipo_documento_id' => $documento->tipo_documento_id,
            'archivo_path' => $documento->archivo_path,
            'tipo_hecho' => 'color_favorito',
            'valor' => 'AZUL',
            'metodo' => 'captura_manual',
        ]);
    }
}
