<?php

namespace Tests\Feature\Application\Documentos;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\EstadoPaso2;
use App\Application\Tramite\EstadoPaso24;
use App\Infrastructure\Documentos\AlmacenDocumentos;
use App\Infrastructure\Documentos\AlmacenDocumentosLocal;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoEscuelaNivel;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
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
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

class RegistrarDocumentoTest extends TestCase
{
    use CompletaPaso2;
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

    public function test_visto_bueno_proteccion_civil_vence_a_los_30_dias(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();

        (new RegistrarDocumento)->ejecutar($escuela->id, 'visto_bueno_proteccion_civil', UploadedFile::fake()->create('pc.pdf', 50, 'application/pdf'), new DatosDocumento(fechaEmision: '2026-09-01'));

        $this->assertDatabaseHas('documentos_plantel', [
            'plantel_id' => $escuela->plantel_id,
            'fecha_emision' => '2026-09-01',
            'fecha_vigencia' => '2026-10-01',
        ]);
    }

    public function test_registra_la_extension_de_dictamen_de_uso_de_suelo(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();

        (new RegistrarDocumento)->ejecutar($escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->create('d.pdf', 50, 'application/pdf'), new DatosDocumento(
            fechaEmision: '2026-09-01',
            autoridadExpide: 'Dirección de Desarrollo Urbano',
            folioDictamen: 'DUS-2026-001',
            giroAutorizado: 'Escuela de educación básica',
        ));

        $this->assertDatabaseHas('dictamenes_uso_suelo', [
            'autoridad_expide' => 'Dirección de Desarrollo Urbano',
            'folio_dictamen' => 'DUS-2026-001',
            'giro_autorizado' => 'Escuela de educación básica',
        ]);
    }

    public function test_la_vigencia_de_la_constancia_es_la_fecha_de_vigencia_del_documento(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();

        (new RegistrarDocumento)->ejecutar($escuela->id, 'constancia_seguridad_estructural', UploadedFile::fake()->create('c.pdf', 50, 'application/pdf'), new DatosDocumento(
            fechaEmision: '2025-12-01',
            peritoNombre: 'Ing. Juan Pérez',
            peritoRegistroDro: 'DRO-100',
            vigenciaConstancia: '2026-01-15',
        ));

        $this->assertDatabaseHas('documentos_plantel', [
            'plantel_id' => $escuela->plantel_id,
            'fecha_emision' => '2025-12-01',
            'fecha_vigencia' => '2026-01-15',
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
            vigenciaConstancia: '2027-06-30',
            peritoRegistroAutoridad: 'Municipio de Querétaro',
            peritoRegistroVigencia: '2026-01-01',
        ));

        $this->assertDatabaseHas('constancias_seguridad_estructural', [
            'perito_nombre' => 'Ing. Juan Pérez',
            'perito_registro_dro' => 'DRO-100',
            'vigencia_constancia' => '2027-06-30',
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
            fechaEscritura: '2024-03-15',
            propietarioNombre: 'Colegio Norte SA de CV',
            notarioNombre: 'Lic. Ana Notaria',
        ));

        $this->assertDatabaseHas('acreditaciones_ocupacion_legal', [
            'tipo' => 'escritura_publica',
            'numero_escritura' => 'E-500',
            'fecha_escritura' => '2024-03-15',
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
        $escuela = $this->crearEscuela('moral'); // poder_gestor solo aplica a fisica_con_gestor

        try {
            (new RegistrarDocumento)->ejecutar($escuela->id, 'poder_gestor', UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'), new DatosDocumento);
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            // Minor 5 — la clave 'clave' no corresponde a ningún campo Livewire;
            // "archivos.{clave}" es la misma ruta que usa el rechazo de PDF y sí
            // resuelve a un campo real del formulario.
            $this->assertArrayHasKey('archivos.poder_gestor', $e->errores);
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
        (new RegistrarDocumento)->ejecutar($escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->createWithContent('v1.pdf', 'contenido-version-1'), new DatosDocumento(
            fechaEmision: '2026-09-01',
            giroAutorizado: 'Escuela de educación básica',
        ));

        $documentoAntes = DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->first();
        $rutaAnterior = $documentoAntes->archivo_path;
        Storage::disk('documentos')->assertExists($rutaAnterior);
        $bytesAnteriores = Storage::disk('documentos')->get($rutaAnterior);

        try {
            // giro_autorizado es VARCHAR(200) y este documento no valida largo antes de escribir:
            // el fallo ocurre dentro de la transacción, tras reemplazar la fila base.
            (new RegistrarDocumento)->ejecutar($escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->createWithContent('v2.pdf', 'contenido-version-2-mas-largo'), new DatosDocumento(
                fechaEmision: '2026-09-02',
                giroAutorizado: str_repeat('x', 201),
            ));
            $this->fail('Se esperaba que la transacción fallara por el largo de la columna.');
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

        // Minor 6 — el archivo nuevo (v2) que ejecutar() escribió a disco antes
        // de que la transacción externa hiciera rollback no debe quedar huérfano:
        // ninguna fila lo referencia.
        $archivosEscuela = Storage::disk('documentos')->allFiles("escuela/{$escuela->id}");
        $this->assertCount(1, $archivosEscuela, 'el archivo nuevo (v2) quedó huérfano tras el rollback de la transacción externa');
    }

    // WS-2 item 2 — si el borrado diferido del archivo anterior falla (p. ej.
    // el disco fue removido entre la subida y el afterCommit), esa excepción
    // corre DESPUÉS del commit real (DB::afterCommit se dispara dentro de
    // commit(), antes de que DB::transaction() regrese) y no debe alcanzar el
    // catch(Throwable) de ejecutar(): ese catch borraría $ruta, el archivo
    // NUEVO al que la fila ya confirmada apunta.
    public function test_fallo_al_borrar_el_archivo_anterior_no_borra_el_archivo_nuevo_ni_propaga(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();

        $almacenReal = new AlmacenDocumentosLocal;
        (new RegistrarDocumento($almacenReal))->ejecutar($escuela->id, 'ine', UploadedFile::fake()->createWithContent('v1.pdf', 'contenido-version-1'), new DatosDocumento);
        $rutaAnterior = DocumentoEscuela::first()->archivo_path;

        $almacenQueFallaAlBorrar = new class($almacenReal, $rutaAnterior) implements AlmacenDocumentos
        {
            public function __construct(private AlmacenDocumentos $real, private string $rutaQueFalla) {}

            public function guardar(string $ambito, int $ownerId, string $clave, UploadedFile $archivo): string
            {
                return $this->real->guardar($ambito, $ownerId, $clave, $archivo);
            }

            public function eliminar(string $path): void
            {
                if ($path === $this->rutaQueFalla) {
                    throw new RuntimeException('disco no disponible');
                }

                $this->real->eliminar($path);
            }
        };

        // No debe lanzar: la excepción del borrado diferido debe quedar contenida.
        (new RegistrarDocumento($almacenQueFallaAlBorrar))->ejecutar($escuela->id, 'ine', UploadedFile::fake()->createWithContent('v2.pdf', 'contenido-version-2-mas-largo'), new DatosDocumento);

        $documento = DocumentoEscuela::first();
        $this->assertNotSame($rutaAnterior, $documento->archivo_path);
        Storage::disk('documentos')->assertExists($documento->archivo_path);
    }

    /** Escuela con Paso 2 completo y un nivel; con turno/tipo de alumnado salvo que se pida lo contrario. */
    private function crearEscuelaNivel(string $claveNivel = 'primaria', bool $conDatos = true): EscuelaNivel
    {
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
        $this->completarPaso2($escuela->id);

        return EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', $claveNivel)->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
            'turno' => $conDatos ? 'matutino' : null,
            'tipo_alumnado' => $conDatos ? 'mixto' : null,
        ]);
    }

    private function recibo(string $folio = 'F-123', string $monto = '1500.00', ?string $fechaPago = null): DatosDocumento
    {
        return new DatosDocumento(folio: $folio, monto: $monto, fechaPago: $fechaPago ?? now()->toDateString());
    }

    private function pdf(string $nombre = 'd.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nombre, 10, 'application/pdf');
    }

    /** Revisión Task 4 (Minor 3): DatosInvalidos con la clave archivos.{clave} y sin filas ni archivos nuevos en ningún ámbito. */
    private function assertRechazoSinEscritura(string $clave, callable $subir): void
    {
        $tablas = ['documentos_plantel', 'documentos_escuela', 'documentos_escuela_nivel'];
        $filasAntes = array_map(fn ($t) => DB::table($t)->count(), $tablas);
        $archivosAntes = Storage::disk('documentos')->allFiles();

        try {
            $subir();
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey("archivos.{$clave}", $e->errores);
        }

        $this->assertSame($filasAntes, array_map(fn ($t) => DB::table($t)->count(), $tablas));
        $this->assertSame($archivosAntes, Storage::disk('documentos')->allFiles());
    }

    public function test_registra_documento_de_ambito_escuela_nivel_en_su_propia_ruta(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();

        app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'acervo_bibliografico_primaria', $this->pdf(), new DatosDocumento, $escuelaNivel->id);

        $documento = DocumentoEscuelaNivel::first();
        $this->assertNotNull($documento);
        $this->assertSame($escuelaNivel->id, $documento->escuela_nivel_id);
        $this->assertSame('pendiente', $documento->estado_validacion);
        $this->assertMatchesRegularExpression('#^escuela_nivel/'.$escuelaNivel->id.'/acervo_bibliografico_primaria-[0-9A-Z]{26}\.pdf$#', $documento->archivo_path);
        Storage::disk('documentos')->assertExists($documento->archivo_path);
    }

    /** ADR-007 + WS-5b: the title count the per-level acervo check reads. */
    public function test_guarda_el_numero_de_titulos_de_la_relacion_de_acervo(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();

        app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'acervo_bibliografico_primaria', $this->pdf(), new DatosDocumento(acervoTitulos: 320), $escuelaNivel->id);

        $this->assertDatabaseHas('relaciones_acervo_bibliografico', [
            'documento_escuela_nivel_id' => DocumentoEscuelaNivel::sole()->id,
            'numero_titulos' => 320,
        ]);
    }

    public function test_resubir_la_relacion_sin_titulos_borra_los_del_archivo_anterior(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel('secundaria');
        $subir = fn (DatosDocumento $datos) => app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'acervo_bibliografico_secundaria', $this->pdf(), $datos, $escuelaNivel->id);

        $subir(new DatosDocumento(acervoTitulos: 320));
        $subir(new DatosDocumento(acervoTitulos: 310));
        $this->assertDatabaseHas('relaciones_acervo_bibliografico', ['numero_titulos' => 310]);
        $this->assertDatabaseCount('relaciones_acervo_bibliografico', 1);

        $subir(new DatosDocumento);
        $this->assertDatabaseCount('relaciones_acervo_bibliografico', 0);
    }

    public function test_rechaza_un_numero_de_titulos_negativo_sin_escribir_nada(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();

        try {
            app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'acervo_bibliografico_primaria', $this->pdf(), new DatosDocumento(acervoTitulos: -1), $escuelaNivel->id);
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('acervo.titulos', $e->errores);
        }

        $this->assertDatabaseCount('documentos_escuela_nivel', 0);
    }

    public function test_registra_el_recibo_con_su_extension(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();

        app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'recibo_pago_derechos', $this->pdf(), new DatosDocumento(
            folio: 'F-777', monto: '2500.50', fechaPago: '2026-09-01', portalReferencia: 'portal-tributario.queretaro.gob.mx',
        ), $escuelaNivel->id);

        $this->assertDatabaseHas('recibos_pago_derechos', [
            'documento_escuela_nivel_id' => DocumentoEscuelaNivel::first()->id,
            'folio' => 'F-777',
            'monto' => '2500.50',
            'fecha_pago' => '2026-09-01',
            'portal_referencia' => 'portal-tributario.queretaro.gob.mx',
        ]);
    }

    public function test_rechaza_un_recibo_sin_folio_monto_ni_fecha_sin_escribir_nada(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();

        try {
            app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'recibo_pago_derechos', $this->pdf(), new DatosDocumento, $escuelaNivel->id);
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('recibo.folio', $e->errores);
            $this->assertArrayHasKey('recibo.monto', $e->errores);
            $this->assertArrayHasKey('recibo.fechaPago', $e->errores);
        }

        $this->assertDatabaseCount('documentos_escuela_nivel', 0);
        Storage::disk('documentos')->assertDirectoryEmpty('escuela_nivel');
    }

    public function test_rechaza_un_monto_cero(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();

        try {
            app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'recibo_pago_derechos', $this->pdf(), $this->recibo(monto: '0'), $escuelaNivel->id);
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('recibo.monto', $e->errores);
        }
    }

    /** Con control: mañana se rechaza, hoy se acepta. */
    public function test_rechaza_una_fecha_de_pago_futura_y_acepta_la_de_hoy(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();

        try {
            app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'recibo_pago_derechos', $this->pdf(), $this->recibo(fechaPago: now()->addDay()->toDateString()), $escuelaNivel->id);
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('recibo.fechaPago', $e->errores);
        }
        $this->assertDatabaseCount('documentos_escuela_nivel', 0);

        app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'recibo_pago_derechos', $this->pdf(), $this->recibo(fechaPago: now()->toDateString()), $escuelaNivel->id);
        $this->assertDatabaseCount('documentos_escuela_nivel', 1);
    }

    /** Review Focus 2. */
    public function test_rechaza_un_nivel_de_otra_escuela(): void
    {
        $escuelaNivelDeA = $this->crearEscuelaNivel();
        $escuelaNivelDeB = $this->crearEscuelaNivel();

        try {
            app(RegistrarDocumento::class)->ejecutar($escuelaNivelDeB->escuela_id, 'acervo_bibliografico_primaria', $this->pdf(), new DatosDocumento, $escuelaNivelDeA->id);
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('archivos.acervo_bibliografico_primaria', $e->errores);
        }

        $this->assertDatabaseCount('documentos_escuela_nivel', 0);
    }

    public function test_rechaza_una_clave_que_no_aplica_al_nivel(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel('primaria');

        $this->assertRechazoSinEscritura('inventario_laboratorio', fn () => app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'inventario_laboratorio', $this->pdf(), new DatosDocumento, $escuelaNivel->id));
    }

    public function test_rechaza_una_clave_por_nivel_sin_escuela_nivel(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();

        $this->assertRechazoSinEscritura('formato_solicitud', fn () => app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'formato_solicitud', $this->pdf(), new DatosDocumento));
    }

    public function test_rechaza_una_clave_de_paso_2_2_con_escuela_nivel(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();

        $this->assertRechazoSinEscritura('ine', fn () => app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'ine', $this->pdf(), new DatosDocumento, $escuelaNivel->id));
    }

    public function test_rechaza_el_formato_antes_de_capturar_turno_y_tipo_de_alumnado(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel(conDatos: false);

        try {
            app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'formato_solicitud', $this->pdf(), new DatosDocumento, $escuelaNivel->id);
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(EstadoPaso24::DATOS, $e->etapaFaltante);
        }

        $this->assertDatabaseCount('documentos_escuela_nivel', 0);
    }

    public function test_rechaza_documentos_del_nivel_con_paso2_incompleto(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);

        try {
            (new RegistrarDocumento)->ejecutar($escuela->id, 'acervo_bibliografico_primaria', $this->pdf(), new DatosDocumento, $escuelaNivel->id);
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida $e) {
            // Revisión M7: la etapa es la de Paso 2 (documentos de 2.2), no la de 2.4.
            $this->assertSame(EstadoPaso2::DOCUMENTOS, $e->etapaFaltante);
        }

        $this->assertDatabaseCount('documentos_escuela_nivel', 0);
    }

    public function test_rechaza_un_archivo_que_no_es_pdf_en_el_nivel(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();

        $this->assertRechazoSinEscritura('acervo_bibliografico_primaria', fn () => app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'acervo_bibliografico_primaria', UploadedFile::fake()->create('x.png', 10, 'image/png'), new DatosDocumento, $escuelaNivel->id));
    }

    public function test_reemplazo_en_el_nivel_apunta_a_ruta_nueva_y_borra_la_anterior(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();
        app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'formato_solicitud', $this->pdf('v1.pdf'), new DatosDocumento, $escuelaNivel->id);
        $rutaAnterior = DocumentoEscuelaNivel::first()->archivo_path;

        app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'formato_solicitud', $this->pdf('v2.pdf'), new DatosDocumento, $escuelaNivel->id);

        $this->assertDatabaseCount('documentos_escuela_nivel', 1);
        $documento = DocumentoEscuelaNivel::first();
        $this->assertNotSame($rutaAnterior, $documento->archivo_path);
        Storage::disk('documentos')->assertExists($documento->archivo_path);
        Storage::disk('documentos')->assertMissing($rutaAnterior);
    }

    public function test_rollback_externo_en_el_nivel_conserva_el_archivo_previo_y_no_deja_huerfano_el_nuevo(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();
        app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'acervo_bibliografico_primaria', UploadedFile::fake()->createWithContent('v1.pdf', 'contenido-version-1'), new DatosDocumento, $escuelaNivel->id);
        $rutaAnterior = DocumentoEscuelaNivel::first()->archivo_path;

        try {
            DB::transaction(function () use ($escuelaNivel) {
                app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'acervo_bibliografico_primaria', UploadedFile::fake()->createWithContent('v2.pdf', 'contenido-version-2-mas-largo'), new DatosDocumento, $escuelaNivel->id);

                throw new RuntimeException('fuerza rollback de la transacción externa');
            });
            $this->fail('Se esperaba que la transacción externa fallara.');
        } catch (RuntimeException) {
            // esperado
        }

        $this->assertSame($rutaAnterior, DocumentoEscuelaNivel::first()->archivo_path);
        $this->assertSame('contenido-version-1', Storage::disk('documentos')->get($rutaAnterior));
        $this->assertCount(1, Storage::disk('documentos')->allFiles("escuela_nivel/{$escuelaNivel->id}"), 'el archivo nuevo (v2) quedó huérfano');
    }

    public function test_reemplazar_el_recibo_actualiza_su_extension_sin_duplicar(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();

        app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'recibo_pago_derechos', $this->pdf(), $this->recibo(folio: 'F-1'), $escuelaNivel->id);
        app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'recibo_pago_derechos', $this->pdf(), $this->recibo(folio: 'F-2'), $escuelaNivel->id);

        $this->assertDatabaseCount('documentos_escuela_nivel', 1);
        $this->assertDatabaseCount('recibos_pago_derechos', 1);
        $this->assertDatabaseHas('recibos_pago_derechos', ['folio' => 'F-2']);
    }

    /**
     * Revisión Task 4 (I1): los datos del nivel cambian entre verificarNivel()
     * y el lock. Costura determinista: al ejecutarse la lectura de
     * $rutaAnterior (justo antes de guardar el archivo y abrir la
     * transacción), un DB::listen hace lo que haría RegistrarDatosNivel.
     *
     * @param  array<string, string|null>  $cambio
     */
    private function cambiarDatosDelNivelAntesDelLock(EscuelaNivel $escuelaNivel, array $cambio): void
    {
        $hecho = false;
        DB::listen(function ($query) use (&$hecho, $escuelaNivel, $cambio) {
            if (! $hecho && str_starts_with($query->sql, 'select "archivo_path" from "documentos_escuela_nivel"')) {
                $hecho = true;
                DB::table('escuela_niveles')->where('id', $escuelaNivel->id)->update($cambio);
            }
        });
    }

    public function test_rechaza_el_formato_si_turno_o_tipo_de_alumnado_cambian_antes_del_lock(): void
    {
        foreach ([['turno' => 'vespertino'], ['turno' => null, 'tipo_alumnado' => null]] as $cambio) {
            $escuelaNivel = $this->crearEscuelaNivel();
            $archivosAntes = Storage::disk('documentos')->allFiles();
            $this->cambiarDatosDelNivelAntesDelLock($escuelaNivel, $cambio);

            try {
                app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'formato_solicitud', $this->pdf(), new DatosDocumento, $escuelaNivel->id);
                $this->fail('Se esperaba PrecondicionIncumplida: '.json_encode($cambio));
            } catch (PrecondicionIncumplida $e) {
                $this->assertSame(EstadoPaso24::DATOS, $e->etapaFaltante);
            }

            $this->assertSame(0, DocumentoEscuelaNivel::where('escuela_nivel_id', $escuelaNivel->id)->count());
            $this->assertSame($archivosAntes, Storage::disk('documentos')->allFiles(), 'el archivo nuevo quedó huérfano');
        }
    }

    /** Control: la misma costura con un documento que no es el Formato no lo bloquea. */
    public function test_el_cambio_de_datos_antes_del_lock_no_afecta_otros_documentos_del_nivel(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();
        $this->cambiarDatosDelNivelAntesDelLock($escuelaNivel, ['turno' => 'vespertino']);

        app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'acervo_bibliografico_primaria', $this->pdf(), new DatosDocumento, $escuelaNivel->id);

        $this->assertSame('vespertino', $escuelaNivel->fresh()->turno, 'la costura no se disparó');
        $this->assertDatabaseCount('documentos_escuela_nivel', 1);
    }

    /** Revisión Task 4 (Minor 1): el nivel se bloquea FOR UPDATE; los ámbitos de Paso 2.2 no. */
    public function test_solo_el_ambito_escuela_nivel_bloquea_la_fila_del_nivel(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();
        $sqls = [];
        DB::listen(function ($query) use (&$sqls) {
            $sqls[] = $query->sql;
        });
        $bloqueos = function () use (&$sqls) {
            return count(preg_grep('/from "escuela_niveles".* for update$/', $sqls));
        };

        app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'ine', $this->pdf(), new DatosDocumento);
        app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'dictamen_uso_suelo', $this->pdf(), new DatosDocumento(fechaEmision: now()->toDateString()));
        $this->assertSame(0, $bloqueos());

        app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'acervo_bibliografico_primaria', $this->pdf(), new DatosDocumento, $escuelaNivel->id);
        $this->assertSame(1, $bloqueos());
    }

    /** Revisión Task 4 (Minor 4): monto con la forma de NUMERIC(10,2); con control aceptado. */
    public function test_rechaza_montos_que_no_son_numeric_10_2_y_acepta_el_minimo(): void
    {
        $escuelaNivel = $this->crearEscuelaNivel();

        foreach (['1e3', '0.004', '123456789', '-5', '0.00', ' 10', '10.'] as $monto) {
            try {
                app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'recibo_pago_derechos', $this->pdf(), $this->recibo(monto: $monto), $escuelaNivel->id);
                $this->fail("Se esperaba DatosInvalidos para monto {$monto}.");
            } catch (DatosInvalidos $e) {
                $this->assertArrayHasKey('recibo.monto', $e->errores, $monto);
            }
        }
        $this->assertDatabaseCount('recibos_pago_derechos', 0);

        app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'recibo_pago_derechos', $this->pdf(), $this->recibo(monto: '0.01'), $escuelaNivel->id);
        app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'recibo_pago_derechos', $this->pdf(), $this->recibo(monto: '99999999.99'), $escuelaNivel->id);
        $this->assertDatabaseHas('recibos_pago_derechos', ['monto' => '99999999.99']);
    }

    // --- Acreditación de ocupación legal (escritura_inmueble): invariantes por variante ---

    /**
     * @param  array<string, mixed>  $campos  sobrescribe o agrega campos del DTO base de la variante
     */
    private function datosAcreditacion(string $tipo, array $campos = []): DatosDocumento
    {
        $base = match ($tipo) {
            'escritura_publica' => [
                'numeroEscritura' => 'E-500',
                'fechaEscritura' => '2024-03-15',
                'propietarioNombre' => 'Colegio Norte SA de CV',
                'notarioNombre' => 'Lic. Ana Notaria',
            ],
            'arrendamiento', 'comodato' => [
                'arrendadorComodante' => 'Inmobiliaria Sur SA',
                'arrendatarioComodatario' => 'Colegio Norte SA de CV',
                'fechaContrato' => '2025-01-10',
                'fechaInicioVigencia' => '2025-02-01',
                'vigenciaContrato' => '2030-01-31',
                'usoAutorizado' => 'Prestación de servicio educativo',
            ],
            'otro' => [
                'otroEspecifique' => 'Convenio de usufructo',
                'otroReferencia' => 'CU-77',
                'otroTitular' => 'Colegio Norte SA de CV',
                'otroFechaExpedicion' => '2024-06-01',
                'otroAutoridad' => 'Notaría 14',
                'otroDerecho' => 'Usufructo',
                'usoAutorizado' => 'Prestación de servicio educativo',
            ],
            default => [],
        };

        return new DatosDocumento(...['tipoAcreditacion' => $tipo, ...$base, ...$campos]);
    }

    private function escuelaConAcreditacion(): Escuela
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();

        return $this->crearEscuela();
    }

    private function registrarAcreditacion(Escuela $escuela, DatosDocumento $datos): void
    {
        (new RegistrarDocumento)->ejecutar($escuela->id, 'escritura_inmueble', UploadedFile::fake()->create('e.pdf', 50, 'application/pdf'), $datos);
    }

    /** @return array<string, mixed> */
    private function filaAcreditacion(): array
    {
        return (array) DB::table('acreditaciones_ocupacion_legal')->first();
    }

    /** @param  list<string>  $claves  campos del formulario que deben venir en los errores */
    private function assertAcreditacionRechazada(Escuela $escuela, DatosDocumento $datos, array $claves): void
    {
        try {
            $this->registrarAcreditacion($escuela, $datos);
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            foreach ($claves as $clave) {
                $this->assertArrayHasKey("acreditacionForm.{$clave}", $e->errores);
            }
        }

        $this->assertDatabaseCount('acreditaciones_ocupacion_legal', 0);
        $this->assertDatabaseCount('documentos_plantel', 0);
        $this->assertSame([], Storage::disk('documentos')->allFiles());
    }

    public function test_escritura_persiste_propietario_y_datos_registrales(): void
    {
        $escuela = $this->escuelaConAcreditacion();

        $this->registrarAcreditacion($escuela, $this->datosAcreditacion('escritura_publica', [
            'notarioNumero' => '14',
            'notarioLocalidad' => 'Querétaro, Querétaro',
            'folioRpp' => 'FME-123',
            'fechaInscripcionRpp' => '2024-04-01',
        ]));

        $this->assertDatabaseHas('acreditaciones_ocupacion_legal', [
            'tipo' => 'escritura_publica',
            'numero_escritura' => 'E-500',
            'fecha_escritura' => '2024-03-15',
            'propietario_nombre' => 'Colegio Norte SA de CV',
            'notario_nombre' => 'Lic. Ana Notaria',
            'notario_numero' => '14',
            'notario_localidad' => 'Querétaro, Querétaro',
            'folio_rpp' => 'FME-123',
            'fecha_inscripcion_rpp' => '2024-04-01',
        ]);
    }

    public function test_escritura_rechaza_datos_esenciales_faltantes(): void
    {
        $escuela = $this->escuelaConAcreditacion();

        foreach (['numeroEscritura', 'fechaEscritura', 'propietarioNombre', 'notarioNombre'] as $campo) {
            $this->assertAcreditacionRechazada($escuela, $this->datosAcreditacion('escritura_publica', [$campo => null]), [$campo]);
        }
    }

    public function test_rechaza_fechas_futuras(): void
    {
        $escuela = $this->escuelaConAcreditacion();
        $futura = now()->addDay()->toDateString();

        $this->assertAcreditacionRechazada($escuela, $this->datosAcreditacion('escritura_publica', ['fechaEscritura' => $futura]), ['fechaEscritura']);
        $this->assertAcreditacionRechazada($escuela, $this->datosAcreditacion('escritura_publica', ['fechaInscripcionRpp' => $futura]), ['fechaInscripcionRpp']);
        $this->assertAcreditacionRechazada($escuela, $this->datosAcreditacion('arrendamiento', ['fechaContrato' => $futura]), ['fechaContrato']);
        $this->assertAcreditacionRechazada($escuela, $this->datosAcreditacion('comodato', ['ratificadoNotario' => true, 'ratificacionFecha' => $futura]), ['ratificacionFecha']);
        $this->assertAcreditacionRechazada($escuela, $this->datosAcreditacion('otro', ['otroFechaExpedicion' => $futura]), ['otroFechaExpedicion']);
    }

    public function test_rechaza_valores_que_exceden_la_columna_o_fechas_mal_formadas(): void
    {
        $escuela = $this->escuelaConAcreditacion();

        $this->assertAcreditacionRechazada($escuela, $this->datosAcreditacion('escritura_publica', ['numeroEscritura' => str_repeat('1', 51)]), ['numeroEscritura']);
        $this->assertAcreditacionRechazada($escuela, $this->datosAcreditacion('escritura_publica', ['fechaEscritura' => '15/03/2024']), ['fechaEscritura']);
    }

    public function test_tipo_invalido_se_rechaza_antes_de_escribir(): void
    {
        $escuela = $this->escuelaConAcreditacion();

        $this->assertAcreditacionRechazada($escuela, $this->datosAcreditacion('tipo_invalido'), ['tipo']);
    }

    public function test_arrendamiento_persiste_el_contrato_y_anula_campos_de_otras_variantes(): void
    {
        $escuela = $this->escuelaConAcreditacion();

        // Un caller que evita Livewire manda además campos de escritura y de "otro".
        $this->registrarAcreditacion($escuela, $this->datosAcreditacion('arrendamiento', [
            'numeroEscritura' => 'E-1',
            'fechaEscritura' => '2024-03-15',
            'propietarioNombre' => 'Otro Propietario',
            'notarioNombre' => 'Lic. Notario',
            'folioRpp' => 'FME-9',
            'fechaInscripcionRpp' => '2024-04-01',
            'otroEspecifique' => 'Algo',
            'otroDerecho' => 'Usufructo',
            'observaciones' => null,
        ]));

        $fila = $this->filaAcreditacion();
        $this->assertSame('arrendamiento', $fila['tipo']);
        $this->assertSame('Inmobiliaria Sur SA', $fila['arrendador_comodante']);
        $this->assertSame('2025-02-01', $fila['fecha_inicio_vigencia']);
        $this->assertSame('2030-01-31', $fila['vigencia_contrato']);
        $this->assertSame('Prestación de servicio educativo', $fila['uso_autorizado']);
        foreach (['numero_escritura', 'fecha_escritura', 'propietario_nombre', 'notario_nombre', 'folio_rpp', 'fecha_inscripcion_rpp', 'otro_especifique', 'otro_derecho'] as $columna) {
            $this->assertNull($fila[$columna], $columna);
        }
    }

    public function test_escritura_anula_campos_de_contrato_y_de_otro_enviados_por_un_caller(): void
    {
        $escuela = $this->escuelaConAcreditacion();

        $this->registrarAcreditacion($escuela, $this->datosAcreditacion('escritura_publica', [
            'arrendadorComodante' => 'Alguien',
            'vigenciaContrato' => '2030-01-31',
            'usoAutorizado' => 'Educativo',
            'ratificadoNotario' => true,
            'otroReferencia' => 'X',
        ]));

        $fila = $this->filaAcreditacion();
        foreach (['arrendador_comodante', 'vigencia_contrato', 'uso_autorizado', 'ratificado_notario', 'otro_referencia'] as $columna) {
            $this->assertNull($fila[$columna], $columna);
        }
    }

    public function test_contrato_exige_inicio_fin_y_uso_autorizado(): void
    {
        $escuela = $this->escuelaConAcreditacion();

        foreach (['arrendamiento', 'comodato'] as $tipo) {
            foreach (['arrendadorComodante', 'arrendatarioComodatario', 'fechaContrato', 'fechaInicioVigencia', 'vigenciaContrato', 'usoAutorizado'] as $campo) {
                $this->assertAcreditacionRechazada($escuela, $this->datosAcreditacion($tipo, [$campo => null]), [$campo]);
            }
        }
    }

    public function test_el_fin_de_vigencia_no_puede_ser_anterior_al_inicio_ni_a_la_celebracion(): void
    {
        $escuela = $this->escuelaConAcreditacion();

        $this->assertAcreditacionRechazada($escuela, $this->datosAcreditacion('arrendamiento', ['vigenciaContrato' => '2025-01-15']), ['vigenciaContrato']);
        $this->assertAcreditacionRechazada($escuela, $this->datosAcreditacion('comodato', ['fechaInicioVigencia' => '2024-01-01', 'vigenciaContrato' => '2024-06-01']), ['vigenciaContrato']);
    }

    public function test_vigencia_indeterminada_guarda_fin_nulo_aunque_se_envie_una_fecha(): void
    {
        $escuela = $this->escuelaConAcreditacion();

        $this->registrarAcreditacion($escuela, $this->datosAcreditacion('comodato', ['vigenciaIndeterminada' => true]));

        $fila = $this->filaAcreditacion();
        $this->assertTrue($fila['vigencia_indeterminada']);
        $this->assertNull($fila['vigencia_contrato']);
        $this->assertSame('2025-02-01', $fila['fecha_inicio_vigencia']);
    }

    public function test_vigencia_indeterminada_sin_marcar_exige_fin(): void
    {
        $escuela = $this->escuelaConAcreditacion();

        $this->assertAcreditacionRechazada($escuela, $this->datosAcreditacion('arrendamiento', ['vigenciaIndeterminada' => false, 'vigenciaContrato' => null]), ['vigenciaContrato']);
    }

    public function test_ratificacion_se_conserva_en_arrendamiento_y_comodato(): void
    {
        $escuela = $this->escuelaConAcreditacion();

        foreach (['arrendamiento', 'comodato'] as $tipo) {
            DB::table('acreditaciones_ocupacion_legal')->delete();
            DB::table('documentos_plantel')->delete();

            $this->registrarAcreditacion($escuela, $this->datosAcreditacion($tipo, [
                'ratificadoNotario' => true,
                'ratificacionFecha' => '2025-01-20',
                'ratificacionNotarioNombre' => 'Lic. Ana Notaria',
                'ratificacionNotariaNumero' => '14',
            ]));

            $fila = $this->filaAcreditacion();
            $this->assertTrue($fila['ratificado_notario'], $tipo);
            $this->assertSame('2025-01-20', $fila['ratificacion_fecha'], $tipo);
            $this->assertSame('Lic. Ana Notaria', $fila['ratificacion_notario_nombre'], $tipo);
            $this->assertSame('14', $fila['ratificacion_notaria_numero'], $tipo);
        }
    }

    public function test_los_datos_de_ratificacion_se_anulan_si_no_esta_ratificado(): void
    {
        $escuela = $this->escuelaConAcreditacion();

        $this->registrarAcreditacion($escuela, $this->datosAcreditacion('arrendamiento', [
            'ratificadoNotario' => false,
            'ratificacionFecha' => '2025-01-20',
            'ratificacionNotarioNombre' => 'Lic. Ana Notaria',
            'ratificacionNotariaNumero' => '14',
        ]));

        $fila = $this->filaAcreditacion();
        $this->assertFalse($fila['ratificado_notario']);
        foreach (['ratificacion_fecha', 'ratificacion_notario_nombre', 'ratificacion_notaria_numero'] as $columna) {
            $this->assertNull($fila[$columna], $columna);
        }
    }

    public function test_la_ratificacion_no_es_obligatoria(): void
    {
        $escuela = $this->escuelaConAcreditacion();

        $this->registrarAcreditacion($escuela, $this->datosAcreditacion('comodato'));

        $this->assertDatabaseCount('acreditaciones_ocupacion_legal', 1);
    }

    public function test_arrendador_que_no_es_propietario_exige_referencia_de_facultades(): void
    {
        $escuela = $this->escuelaConAcreditacion();

        $this->assertAcreditacionRechazada($escuela, $this->datosAcreditacion('arrendamiento', ['arrendadorEsPropietario' => false]), ['facultadesReferencia']);

        $this->registrarAcreditacion($escuela, $this->datosAcreditacion('arrendamiento', ['arrendadorEsPropietario' => false, 'facultadesReferencia' => 'Poder notarial 123']));
        $fila = $this->filaAcreditacion();
        $this->assertFalse($fila['arrendador_es_propietario']);
        $this->assertSame('Poder notarial 123', $fila['facultades_referencia']);
    }

    public function test_arrendador_propietario_anula_la_referencia_de_facultades(): void
    {
        $escuela = $this->escuelaConAcreditacion();

        $this->registrarAcreditacion($escuela, $this->datosAcreditacion('comodato', ['arrendadorEsPropietario' => true, 'facultadesReferencia' => 'Poder 1']));

        $fila = $this->filaAcreditacion();
        $this->assertTrue($fila['arrendador_es_propietario']);
        $this->assertNull($fila['facultades_referencia']);
    }

    public function test_comodato_persiste_estructura_equivalente_a_arrendamiento(): void
    {
        $escuela = $this->escuelaConAcreditacion();

        $this->registrarAcreditacion($escuela, $this->datosAcreditacion('comodato'));

        $this->assertDatabaseHas('acreditaciones_ocupacion_legal', [
            'tipo' => 'comodato',
            'arrendador_comodante' => 'Inmobiliaria Sur SA',
            'arrendatario_comodatario' => 'Colegio Norte SA de CV',
            'fecha_contrato' => '2025-01-10',
            'fecha_inicio_vigencia' => '2025-02-01',
            'vigencia_contrato' => '2030-01-31',
        ]);
    }

    public function test_otro_exige_metadatos_del_instrumento_derecho_y_uso(): void
    {
        $escuela = $this->escuelaConAcreditacion();

        foreach (['otroEspecifique', 'otroReferencia', 'otroTitular', 'otroFechaExpedicion', 'otroAutoridad', 'otroDerecho', 'usoAutorizado'] as $campo) {
            $this->assertAcreditacionRechazada($escuela, $this->datosAcreditacion('otro', [$campo => null]), [$campo]);
        }
    }

    public function test_otro_persiste_metadatos_sin_exigir_leyenda_de_uso_educativo(): void
    {
        $escuela = $this->escuelaConAcreditacion();

        $this->registrarAcreditacion($escuela, $this->datosAcreditacion('otro', [
            'usoAutorizado' => 'Ocupación de oficinas administrativas',
            'folioRpp' => 'FME-9',
            'arrendadorComodante' => 'Se descarta',
        ]));

        $fila = $this->filaAcreditacion();
        $this->assertSame('otro', $fila['tipo']);
        $this->assertSame('Usufructo', $fila['otro_derecho']);
        $this->assertSame('Ocupación de oficinas administrativas', $fila['uso_autorizado']);
        $this->assertSame('FME-9', $fila['folio_rpp']);
        $this->assertNull($fila['arrendador_comodante']);
    }

    public function test_reemplazar_la_acreditacion_por_otra_variante_deja_nulos_los_campos_anteriores(): void
    {
        $escuela = $this->escuelaConAcreditacion();
        $this->registrarAcreditacion($escuela, $this->datosAcreditacion('escritura_publica', ['folioRpp' => 'FME-123']));

        $this->registrarAcreditacion($escuela, $this->datosAcreditacion('arrendamiento'));

        $this->assertDatabaseCount('acreditaciones_ocupacion_legal', 1);
        $fila = $this->filaAcreditacion();
        $this->assertSame('arrendamiento', $fila['tipo']);
        $this->assertNull($fila['folio_rpp']);
        $this->assertNull($fila['numero_escritura']);
        $this->assertNull($fila['propietario_nombre']);
    }
}
