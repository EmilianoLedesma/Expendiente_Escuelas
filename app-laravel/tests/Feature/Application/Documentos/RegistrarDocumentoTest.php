<?php

namespace Tests\Feature\Application\Documentos;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Models\DocumentoEscuela;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class RegistrarDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuela(): Escuela
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    public function test_registra_documento_de_ambito_escuela(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();

        (new RegistrarDocumento)->ejecutar($escuela->id, 'ine', UploadedFile::fake()->create('ine.pdf', 50, 'application/pdf'), new DatosDocumento);

        $this->assertDatabaseHas('documentos_escuela', ['escuela_id' => $escuela->id, 'estado_validacion' => 'pendiente']);
    }

    public function test_registra_documento_de_ambito_plantel_via_escuela(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();

        (new RegistrarDocumento)->ejecutar($escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->create('d.pdf', 50, 'application/pdf'), new DatosDocumento(fechaEmision: '2026-09-01'));

        $this->assertDatabaseHas('documentos_plantel', [
            'plantel_id' => $escuela->plantel_id,
            'fecha_emision' => '2026-09-01',
            'fecha_vigencia' => '2026-10-01',
            'estado_validacion' => 'pendiente',
        ]);
    }

    public function test_registra_la_extension_de_constancia_seguridad(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();

        (new RegistrarDocumento)->ejecutar($escuela->id, 'constancia_seguridad_estructural', UploadedFile::fake()->create('c.pdf', 50, 'application/pdf'), new DatosDocumento(
            fechaEmision: '2026-01-15',
            peritoNombre: 'Ing. Juan Pérez',
            peritoCedulaProfesional: '1234567',
            peritoRegistroDro: 'DRO-100',
            peritoRegistroAutoridad: 'Municipio de Querétaro',
            peritoRegistroVigencia: '2026-01-01',
        ));

        $this->assertDatabaseHas('constancias_seguridad_estructural', [
            'perito_nombre' => 'Ing. Juan Pérez',
            'perito_registro_dro' => 'DRO-100',
        ]);
    }

    public function test_registra_la_extension_de_acreditacion_ocupacion(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();

        (new RegistrarDocumento)->ejecutar($escuela->id, 'escritura_inmueble', UploadedFile::fake()->create('e.pdf', 50, 'application/pdf'), new DatosDocumento(
            tipoAcreditacion: 'escritura_publica',
            numeroEscritura: 'E-500',
            notarioNombre: 'Lic. Ana Notaria',
        ));

        $this->assertDatabaseHas('acreditaciones_ocupacion_legal', [
            'tipo' => 'escritura_publica',
            'numero_escritura' => 'E-500',
        ]);
    }

    public function test_reemplaza_en_el_lugar_y_reinicia_estado_validacion_a_pendiente(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        (new RegistrarDocumento)->ejecutar($escuela->id, 'ine', UploadedFile::fake()->create('v1.pdf', 50, 'application/pdf'), new DatosDocumento);

        $this->assertDatabaseCount('documentos_escuela', 1);
        $documento = DocumentoEscuela::first();
        $documento->update(['estado_validacion' => 'rechazado']);

        (new RegistrarDocumento)->ejecutar($escuela->id, 'ine', UploadedFile::fake()->create('v2.pdf', 50, 'application/pdf'), new DatosDocumento);

        $this->assertDatabaseCount('documentos_escuela', 1);
        $this->assertDatabaseHas('documentos_escuela', ['escuela_id' => $escuela->id, 'estado_validacion' => 'pendiente']);
    }

    public function test_rechaza_clave_desconocida(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();

        $this->expectException(InvalidArgumentException::class);

        (new RegistrarDocumento)->ejecutar($escuela->id, 'clave_inventada', UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'), new DatosDocumento);
    }
}
