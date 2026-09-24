<?php

namespace Tests\Feature\Application\Documentos;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\EstadoPaso2;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\ResponsableLegal;
use App\Models\Solicitante;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class RegistrarDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuela(string $tipoPersona = 'fisica'): Escuela
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        ResponsableLegal::create(['escuela_id' => $escuela->id, 'tipo_persona' => $tipoPersona]);

        return $escuela;
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

    // WS-2.4a — un caller que se salte la UI no debe poder subir el documento de
    // identidad que no corresponde al tipo_persona real de la escuela (mismo
    // caso que rechazaría DocumentosCompletos::clavesAplicables en la UI).
    public function test_rechaza_clave_no_aplicable_al_tipo_persona_de_la_escuela(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela('moral'); // identidad aplicable es escritura_poder_facultades, no acta_nacimiento

        try {
            (new RegistrarDocumento)->ejecutar($escuela->id, 'acta_nacimiento', UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'), new DatosDocumento);
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('clave', $e->errores);
        }

        $this->assertDatabaseCount('documentos_escuela', 0);
    }

    // WS-2.4b — sin responsable legal no hay tipo_persona con qué verificar
    // aplicabilidad; antes esto se toleraba (bypass conocido), ahora se
    // rechaza antes de cualquier escritura, para cualquier clave.
    public function test_rechaza_registrar_documento_sin_responsable_legal_capturado(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);

        $this->expectException(PrecondicionIncumplida::class);

        (new RegistrarDocumento)->ejecutar($escuela->id, 'ine', UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'), new DatosDocumento);
    }

    // WS-2.4b — cierra el hueco de WS-2.4a: sin responsable, una clave no
    // aplicable a NINGÚN tipo_persona (o a ninguno todavía verificable) debe
    // rechazarse por la precondición de orden, no colarse por ausencia de
    // tipo_persona.
    public function test_rechaza_clave_no_aplicable_cuando_tampoco_hay_responsable(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);

        try {
            (new RegistrarDocumento)->ejecutar($escuela->id, 'acta_nacimiento', UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'), new DatosDocumento);
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(EstadoPaso2::RESPONSABLE, $e->etapaFaltante);
        }

        $this->assertDatabaseCount('documentos_escuela', 0);
    }

    public function test_rechaza_un_archivo_que_no_es_pdf(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();

        try {
            (new RegistrarDocumento)->ejecutar($escuela->id, 'ine', UploadedFile::fake()->create('x.png', 10, 'image/png'), new DatosDocumento);
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('archivos.ine', $e->errores);
        }

        $this->assertDatabaseCount('documentos_escuela', 0);
        Storage::disk('documentos')->assertDirectoryEmpty('escuela');
    }

    public function test_primera_carga_guarda_en_ruta_unica_registrada_en_la_fila(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();

        (new RegistrarDocumento)->ejecutar($escuela->id, 'ine', UploadedFile::fake()->create('v1.pdf', 50, 'application/pdf'), new DatosDocumento);

        $documento = DocumentoEscuela::first();
        $this->assertNotNull($documento);
        $this->assertMatchesRegularExpression('#^escuela/'.$escuela->id.'/ine-[0-9A-Z]{26}\.pdf$#', $documento->archivo_path);
        Storage::disk('documentos')->assertExists($documento->archivo_path);
    }

    public function test_reemplazo_exitoso_apunta_a_ruta_nueva_y_borra_la_anterior(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        (new RegistrarDocumento)->ejecutar($escuela->id, 'ine', UploadedFile::fake()->create('v1.pdf', 50, 'application/pdf'), new DatosDocumento);

        $rutaAnterior = DocumentoEscuela::first()->archivo_path;
        Storage::disk('documentos')->assertExists($rutaAnterior);

        (new RegistrarDocumento)->ejecutar($escuela->id, 'ine', UploadedFile::fake()->create('v2.pdf', 60, 'application/pdf'), new DatosDocumento);

        $documento = DocumentoEscuela::first();
        $this->assertNotSame($rutaAnterior, $documento->archivo_path);
        Storage::disk('documentos')->assertExists($documento->archivo_path);
        Storage::disk('documentos')->assertMissing($rutaAnterior);
    }

    public function test_fallo_dentro_de_la_transaccion_conserva_archivo_y_fila_previos(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        (new RegistrarDocumento)->ejecutar($escuela->id, 'escritura_inmueble', UploadedFile::fake()->createWithContent('v1.pdf', 'contenido-version-1'), new DatosDocumento(
            tipoAcreditacion: 'escritura_publica',
            numeroEscritura: 'E-100',
        ));

        $documentoAntes = DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->first();
        $rutaAnterior = $documentoAntes->archivo_path;
        Storage::disk('documentos')->assertExists($rutaAnterior);
        $bytesAnteriores = Storage::disk('documentos')->get($rutaAnterior);

        try {
            // tipoAcreditacion inválido viola el CHECK de acreditaciones_ocupacion_legal.tipo,
            // forzando el fallo dentro de la transacción tras reemplazar la fila base.
            (new RegistrarDocumento)->ejecutar($escuela->id, 'escritura_inmueble', UploadedFile::fake()->createWithContent('v2.pdf', 'contenido-version-2-mas-largo'), new DatosDocumento(
                tipoAcreditacion: 'tipo_invalido',
            ));
            $this->fail('Se esperaba que la transacción fallara por el CHECK de tipo.');
        } catch (\Throwable) {
            // esperado
        }

        $documentoDespues = DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->first();
        $this->assertSame($rutaAnterior, $documentoDespues->archivo_path);
        $this->assertSame($documentoAntes->estado_validacion, $documentoDespues->estado_validacion);
        Storage::disk('documentos')->assertExists($rutaAnterior);
        $this->assertSame($bytesAnteriores, Storage::disk('documentos')->get($rutaAnterior), 'los bytes del archivo previo no deben alterarse');

        $archivosPlantel = Storage::disk('documentos')->allFiles("plantel/{$escuela->plantel_id}");
        $this->assertCount(1, $archivosPlantel, 'no debe quedar un archivo nuevo huérfano');
    }

    public function test_rollback_de_una_transaccion_externa_que_envuelve_ejecutar_conserva_archivo_y_fila_previos(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        (new RegistrarDocumento)->ejecutar($escuela->id, 'ine', UploadedFile::fake()->createWithContent('v1.pdf', 'contenido-version-1'), new DatosDocumento);

        $documentoAntes = DocumentoEscuela::first();
        $rutaAnterior = $documentoAntes->archivo_path;
        $bytesAnteriores = Storage::disk('documentos')->get($rutaAnterior);

        try {
            // RegistrarDocumento::ejecutar() puede correr dentro de una
            // transacción de un caller externo (p. ej. un caso de uso mayor
            // o un adaptador de API futuro). Si ese exterior hace rollback,
            // el archivo previo no debe borrarse: el borrado se difiere con
            // DB::afterCommit() hasta que la transacción externa confirme de
            // verdad, no hasta que el DB::transaction() interno de
            // RegistrarDocumento termine su propio savepoint.
            DB::transaction(function () use ($escuela) {
                (new RegistrarDocumento)->ejecutar($escuela->id, 'ine', UploadedFile::fake()->createWithContent('v2.pdf', 'contenido-version-2-mas-largo'), new DatosDocumento);

                throw new RuntimeException('fuerza rollback de la transacción externa');
            });
            $this->fail('Se esperaba que la transacción externa fallara.');
        } catch (RuntimeException) {
            // esperado
        }

        $documentoDespues = DocumentoEscuela::first();
        $this->assertSame($rutaAnterior, $documentoDespues->archivo_path);
        Storage::disk('documentos')->assertExists($rutaAnterior);
        $this->assertSame($bytesAnteriores, Storage::disk('documentos')->get($rutaAnterior), 'el archivo previo no debe borrarse si la transacción externa hace rollback');
    }
}
