<?php

namespace Tests\Feature\Application\Documentos;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\Documentos\ValidarVigenciaDocumentos;
use App\Models\DocumentoEscuelaNivel;
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
use Tests\TestCase;

class ValidarVigenciaDocumentosTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuela(): Escuela
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        // WS-2.4b: RegistrarDocumento ahora exige responsable legal capturado antes de escribir.
        ResponsableLegal::create(['escuela_id' => $escuela->id, 'tipo_persona' => 'fisica']);

        return $escuela;
    }

    public function test_sin_violaciones_cuando_no_hay_documentos(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();

        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id));
    }

    public function test_detecta_dictamen_de_uso_de_suelo_vencido(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        (new RegistrarDocumento)->ejecutar($escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'), new DatosDocumento(
            fechaEmision: now()->subDays(60)->toDateString(),
        ));

        $violaciones = (new ValidarVigenciaDocumentos)->ejecutar($escuela->id);

        $this->assertNotEmpty($violaciones);
        $this->assertStringContainsString('Dictamen de Uso de Suelo', $violaciones['dictamen_uso_suelo']);
    }

    public function test_acepta_dictamen_de_uso_de_suelo_vigente(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        (new RegistrarDocumento)->ejecutar($escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'), new DatosDocumento(
            fechaEmision: now()->subDays(5)->toDateString(),
        ));

        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id));
    }

    public function test_detecta_perito_registro_ano_distinto_de_emision(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        (new RegistrarDocumento)->ejecutar($escuela->id, 'constancia_seguridad_estructural', UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'), new DatosDocumento(
            fechaEmision: '2026-01-15',
            peritoRegistroVigencia: '2025-01-01',
        ));

        $violaciones = (new ValidarVigenciaDocumentos)->ejecutar($escuela->id);

        $this->assertNotEmpty($violaciones);
        $this->assertStringContainsString('Constancia de Seguridad Estructural', $violaciones['constancia_seguridad_estructural']);
    }

    /** Estructural: ningún documento por nivel sembrado tiene vigencia hoy; se inyecta uno. */
    public function test_un_documento_por_nivel_vencido_es_una_violacion(): void
    {
        (new TiposDocumentosSeeder)->run();
        $tipoId = DB::table('tipos_documentos')->insertGetId([
            'clave' => 'documento_nivel_con_vigencia', 'nombre' => 'Documento con vigencia', 'aplica_persona' => 'ambas',
            'ambito' => 'escuela_nivel', 'vigencia_max_dias' => 30, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $this->crearEscuela()->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
        $documento = DocumentoEscuelaNivel::create([
            'escuela_nivel_id' => $escuelaNivel->id, 'tipo_documento_id' => $tipoId,
            'archivo_path' => 'x.pdf', 'fecha_vigencia' => now()->toDateString(),
        ]);

        // Control de no vacuidad: vigente hasta hoy no es violación.
        $this->assertSame([], (new ValidarVigenciaDocumentos)->paraEscuelaNivel($escuelaNivel->id));

        $documento->update(['fecha_vigencia' => now()->subDay()->toDateString()]);

        $this->assertArrayHasKey('documento_nivel_con_vigencia', (new ValidarVigenciaDocumentos)->paraEscuelaNivel($escuelaNivel->id));
    }

    /** Revisión M2: un documento vencido de una clave que no aplica al nivel no bloquea (se ignora, como en la completitud). */
    public function test_un_documento_vencido_que_no_aplica_al_nivel_no_es_violacion(): void
    {
        (new TiposDocumentosSeeder)->run();
        $tipoId = DB::table('tipos_documentos')->insertGetId([
            'clave' => 'documento_secundaria_con_vigencia', 'nombre' => 'Documento de Secundaria con vigencia', 'aplica_persona' => 'ambas',
            'ambito' => 'escuela_nivel', 'nivel_educativo_id' => NivelEducativo::where('clave', 'secundaria')->value('id'),
            'vigencia_max_dias' => 30, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $primaria = EscuelaNivel::create([
            'escuela_id' => $this->crearEscuela()->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
        DocumentoEscuelaNivel::create([
            'escuela_nivel_id' => $primaria->id, 'tipo_documento_id' => $tipoId,
            'archivo_path' => 'x.pdf', 'fecha_vigencia' => now()->subDay()->toDateString(),
        ]);

        $this->assertSame([], (new ValidarVigenciaDocumentos)->paraEscuelaNivel($primaria->id));
    }
}
