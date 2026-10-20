<?php

namespace Tests\Feature\Application\Documentos;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\Documentos\ValidarVigenciaDocumentos;
use App\Models\DocumentoEscuelaNivel;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\ResponsableLegal;
use App\Models\Solicitante;
use App\Models\VistoBuenoProteccionCivil;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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

    /**
     * El dictamen vence cuando lo dice el documento (cédula TR-170100-017: "la que
     * indique el documento"), no por antigüedad calculada.
     *
     * @param  array<string, mixed>  $campos
     */
    private function registrarDictamen(Escuela $escuela, array $campos): void
    {
        Storage::fake('documentos');
        (new RegistrarDocumento)->ejecutar($escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'), new DatosDocumento(...[
            'autoridadExpide' => 'Dirección de Desarrollo Urbano',
            'folioDictamen' => 'DUS-2026-001',
            'giroAutorizado' => 'Escuela de educación básica',
            ...$campos,
        ]));
    }

    public function test_detecta_dictamen_con_fecha_de_vencimiento_declarada_ya_pasada(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $vencimiento = now()->subDay();
        $this->registrarDictamen($escuela, [
            'fechaEmision' => now()->subDays(60)->toDateString(),
            'tipoVigencia' => 'fecha_expresa',
            'fechaVencimientoDocumento' => $vencimiento->toDateString(),
        ]);

        $violaciones = (new ValidarVigenciaDocumentos)->ejecutar($escuela->id);

        $this->assertSame(['dictamen_uso_suelo'], array_keys($violaciones));
        $this->assertStringContainsString('Dictamen de Uso de Suelo', $violaciones['dictamen_uso_suelo']);
        $this->assertStringContainsString($vencimiento->format('d/m/Y'), $violaciones['dictamen_uso_suelo']);
        $this->assertStringNotContainsString('vigencia máxima', $violaciones['dictamen_uso_suelo']);
    }

    public function test_acepta_dictamen_emitido_hace_mucho_con_vencimiento_declarado_futuro(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarDictamen($escuela, [
            'fechaEmision' => now()->subDays(400)->toDateString(),
            'tipoVigencia' => 'fecha_expresa',
            'fechaVencimientoDocumento' => now()->addYear()->toDateString(),
        ]);

        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id));
    }

    public function test_acepta_dictamen_sin_fecha_expresa_aunque_se_emitio_hace_mucho(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarDictamen($escuela, [
            'fechaEmision' => now()->subDays(400)->toDateString(),
            'tipoVigencia' => 'sin_fecha_expresa',
        ]);

        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id));
    }

    public function test_un_dictamen_con_vencimiento_hoy_sigue_vigente(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarDictamen($escuela, [
            'fechaEmision' => now()->subDays(60)->toDateString(),
            'tipoVigencia' => 'fecha_expresa',
            'fechaVencimientoDocumento' => now()->toDateString(),
        ]);

        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id));
    }

    public function test_un_dictamen_anterior_sin_vigencia_capturada_no_se_marca_ni_vigente_ni_vencido(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarDictamen($escuela, ['fechaEmision' => now()->subDays(60)->toDateString()]);

        $this->assertSame('no_capturada', DB::table('dictamenes_uso_suelo')->value('tipo_vigencia'));
        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id));
    }

    public function test_el_dictamen_no_aplicable_al_tipo_de_persona_no_se_evalua(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarDictamen($escuela, [
            'fechaEmision' => now()->subDays(60)->toDateString(),
            'tipoVigencia' => 'fecha_expresa',
            'fechaVencimientoDocumento' => now()->subDay()->toDateString(),
        ]);
        DB::table('tipos_documentos')->where('clave', 'dictamen_uso_suelo')->update(['aplica_persona' => 'moral']);

        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Visto Bueno de Protección Civil: captura completa del Municipio de Querétaro (ejercicio 2026) salvo
     * lo que cada prueba cambie. La fecha "de hoy" de la evaluación se fija con setTestNow.
     *
     * @param  array<string, mixed>  $campos
     */
    private function registrarProteccionCivil(Escuela $escuela, array $campos = []): void
    {
        Storage::fake('documentos');
        (new RegistrarDocumento)->ejecutar($escuela->id, 'visto_bueno_proteccion_civil', UploadedFile::fake()->create('pc.pdf', 10, 'application/pdf'), new DatosDocumento(...[
            'fechaEmision' => '2026-02-10',
            'tipoInstrumento' => 'visto_bueno',
            'autoridadEmisora' => 'Coordinación Municipal de Protección Civil',
            'jurisdiccionEmisora' => 'municipio_queretaro',
            'clasificacionRiesgo' => 'alto',
            'ejercicio' => 2026,
            'tipoVigencia' => 'sin_fecha_expresa',
            ...$campos,
        ]));
    }

    private function hoy(string $fecha): void
    {
        Carbon::setTestNow(Carbon::parse("{$fecha} 12:00:00"));
    }

    public function test_el_visto_bueno_de_queretaro_ya_no_vence_a_los_30_dias(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarProteccionCivil($escuela);

        $this->hoy('2026-06-01');

        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id), 'a los 111 días de la emisión sigue vigente: vale el año calendario');
    }

    public function test_el_visto_bueno_de_queretaro_vigente_el_31_de_diciembre_y_vencido_al_dia_siguiente(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarProteccionCivil($escuela);

        $this->hoy('2026-12-31');
        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id), 'último día de vigencia');

        $this->hoy('2027-01-01');
        $violaciones = (new ValidarVigenciaDocumentos)->ejecutar($escuela->id);
        $this->assertSame(['visto_bueno_proteccion_civil'], array_keys($violaciones));
        $this->assertStringContainsString('Protección Civil', $violaciones['visto_bueno_proteccion_civil']);
        $this->assertStringContainsString('31/12/2026', $violaciones['visto_bueno_proteccion_civil']);
        $this->assertStringContainsString('art. 46', $violaciones['visto_bueno_proteccion_civil']);
    }

    public function test_un_visto_bueno_del_ano_anterior_no_se_acepta_por_prorroga_en_el_periodo_de_renovacion(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarProteccionCivil($escuela, ['fechaEmision' => '2025-03-10', 'ejercicio' => 2025]);

        $this->hoy('2026-03-01');

        $this->assertArrayHasKey('visto_bueno_proteccion_civil', (new ValidarVigenciaDocumentos)->ejecutar($escuela->id), 'enero a mayo es el plazo para renovar, no una prórroga automática');
    }

    public function test_el_vencimiento_declarado_por_el_documento_bloquea_solo_despues_de_su_fecha(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarProteccionCivil($escuela, ['jurisdiccionEmisora' => 'otra', 'ejercicio' => null, 'tipoVigencia' => 'fecha_expresa', 'fechaVencimientoDocumento' => '2026-11-30']);

        $this->hoy('2026-11-30');
        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id), 'último día');

        $this->hoy('2026-12-01');
        $violaciones = (new ValidarVigenciaDocumentos)->ejecutar($escuela->id);
        $this->assertSame(['visto_bueno_proteccion_civil'], array_keys($violaciones));
        $this->assertStringContainsString('30/11/2026', $violaciones['visto_bueno_proteccion_civil']);
    }

    public function test_un_documento_de_otra_autoridad_sin_fecha_expresa_no_vence_ni_se_inventa_fecha(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarProteccionCivil($escuela, ['jurisdiccionEmisora' => 'otra', 'ejercicio' => null, 'clasificacionRiesgo' => 'medio']);

        $this->hoy('2030-06-01');

        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id));
        $avisos = (new ValidarVigenciaDocumentos)->avisos($escuela->id);
        $this->assertStringContainsString('no está determinado', $avisos['visto_bueno_proteccion_civil']);
        $this->assertStringNotContainsString('art. 48', $avisos['visto_bueno_proteccion_civil'], 'la comprobación de riesgo no se aplica fuera del Municipio de Querétaro');
    }

    public function test_un_visto_bueno_de_queretaro_sin_ejercicio_se_conserva_y_pide_revision(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarProteccionCivil($escuela, ['ejercicio' => null]);

        $this->hoy('2030-06-01');

        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id));
        $this->assertStringContainsString('año del Visto Bueno', (new ValidarVigenciaDocumentos)->avisos($escuela->id)['visto_bueno_proteccion_civil']);
    }

    public function test_un_documento_historico_con_la_fecha_calculada_a_30_dias_no_vence_y_pide_revision(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarProteccionCivil($escuela, ['tipoInstrumento' => null, 'autoridadEmisora' => null, 'jurisdiccionEmisora' => null, 'clasificacionRiesgo' => null, 'ejercicio' => null, 'tipoVigencia' => null, 'fechaEmision' => '2026-03-01']);
        $documentoId = DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->value('id');
        VistoBuenoProteccionCivil::where('documento_plantel_id', $documentoId)->update(['fecha_calculada_30_dias_historica' => '2026-03-31']);

        $this->hoy('2026-09-01');

        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id), 'la fecha a 30 días ya no es un vencimiento');
        $this->assertStringContainsString('anterior', (new ValidarVigenciaDocumentos)->avisos($escuela->id)['visto_bueno_proteccion_civil']);
    }

    public function test_riesgo_distinto_de_alto_en_un_documento_de_queretaro_avisa_con_su_fundamento_sin_bloquear(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarProteccionCivil($escuela, ['clasificacionRiesgo' => 'medio']);

        $this->hoy('2026-06-01');

        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id));
        $aviso = (new ValidarVigenciaDocumentos)->avisos($escuela->id)['visto_bueno_proteccion_civil'];
        $this->assertStringContainsString('riesgo medio', $aviso);
        $this->assertStringContainsString('art. 48, fr. I, inciso m)', $aviso);
        $this->assertStringContainsString('Municipio de Querétaro', $aviso);
    }

    public function test_riesgo_alto_o_no_indicado_en_un_documento_de_queretaro_no_avisa(): void
    {
        (new TiposDocumentosSeeder)->run();
        $alto = $this->crearEscuela();
        $noIndicado = $this->crearEscuela();
        $this->registrarProteccionCivil($alto, ['clasificacionRiesgo' => 'alto']);
        $this->registrarProteccionCivil($noIndicado, ['clasificacionRiesgo' => 'no_indicado']);

        $this->hoy('2026-06-01');

        $this->assertSame([], (new ValidarVigenciaDocumentos)->avisos($alto->id));
        $this->assertSame([], (new ValidarVigenciaDocumentos)->avisos($noIndicado->id));
    }

    public function test_sin_visto_bueno_capturado_no_hay_violacion_ni_aviso(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();

        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id));
        $this->assertSame([], (new ValidarVigenciaDocumentos)->avisos($escuela->id));
    }

    public function test_el_aviso_de_proteccion_civil_y_el_de_la_constancia_conviven(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarProteccionCivil($escuela, ['clasificacionRiesgo' => 'bajo']);
        $this->registrarConstancia($escuela, ['peritoRegistroVencimiento' => '2026-02-28']);

        $this->assertEqualsCanonicalizing(['visto_bueno_proteccion_civil', 'constancia_seguridad_estructural'], array_keys((new ValidarVigenciaDocumentos)->avisos($escuela->id)));
    }

    public function test_detecta_constancia_de_seguridad_estructural_con_vigencia_vencida(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        (new RegistrarDocumento)->ejecutar($escuela->id, 'constancia_seguridad_estructural', UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'), new DatosDocumento(
            fechaEmision: now()->subYear()->toDateString(),
            peritoNombre: 'Ing. Juan Pérez',
            peritoRegistroDro: 'DRO-100',
            vigenciaConstancia: now()->subDay()->toDateString(),
        ));

        $violaciones = (new ValidarVigenciaDocumentos)->ejecutar($escuela->id);

        $this->assertSame(['constancia_seguridad_estructural'], array_keys($violaciones));
        $this->assertStringContainsString('ha superado su vigencia máxima', $violaciones['constancia_seguridad_estructural']);
    }

    public function test_acepta_constancia_de_seguridad_estructural_con_vigencia_futura(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        (new RegistrarDocumento)->ejecutar($escuela->id, 'constancia_seguridad_estructural', UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'), new DatosDocumento(
            fechaEmision: now()->toDateString(),
            peritoNombre: 'Ing. Juan Pérez',
            peritoRegistroDro: 'DRO-100',
            vigenciaConstancia: now()->addYear()->toDateString(),
        ));

        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id));
    }

    /**
     * @param  array<string, mixed>  $campos
     */
    private function registrarConstancia(Escuela $escuela, array $campos): void
    {
        Storage::fake('documentos');
        (new RegistrarDocumento)->ejecutar($escuela->id, 'constancia_seguridad_estructural', UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'), new DatosDocumento(...[
            'fechaEmision' => '2026-05-10',
            'tipoVigencia' => 'sin_fecha_expresa',
            'peritoNombre' => 'Ing. Juan Pérez',
            'calidadProfesional' => 'corresponsable_seguridad_estructural',
            'peritoRegistroDro' => 'CSE-100',
            ...$campos,
        ]));
    }

    /**
     * Regresión de la regla retirada "año del registro = año de emisión" (sin artículo que la respalde;
     * COMPENDIO: Documentado, no contrastado). Falso positivo: registro vigente que cruza de año.
     */
    public function test_un_registro_vigente_que_cruza_de_anio_ya_no_bloquea(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarConstancia($escuela, ['peritoRegistroVencimiento' => '2027-03-31']);

        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id));
        $this->assertSame([], (new ValidarVigenciaDocumentos)->avisos($escuela->id));
    }

    /** Falso negativo de la regla retirada: mismo año, pero el registro ya había vencido al emitir. */
    public function test_un_registro_vencido_antes_de_la_emision_en_el_mismo_anio_avisa_sin_bloquear(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarConstancia($escuela, ['peritoRegistroVencimiento' => '2026-02-28']);

        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id), 'no bloquea: sin fundamento confirmado ni aplicable a todo municipio');
        $avisos = (new ValidarVigenciaDocumentos)->avisos($escuela->id);
        $this->assertSame(['constancia_seguridad_estructural'], array_keys($avisos));
        $this->assertStringContainsString('28/02/2026', $avisos['constancia_seguridad_estructural']);
    }

    public function test_el_registro_que_vence_el_mismo_dia_de_la_emision_no_avisa(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarConstancia($escuela, ['peritoRegistroVencimiento' => '2026-05-10']);

        $this->assertSame([], (new ValidarVigenciaDocumentos)->avisos($escuela->id));
    }

    public function test_el_registro_que_venció_el_dia_anterior_avisa(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarConstancia($escuela, ['peritoRegistroVencimiento' => '2026-05-09']);

        $this->assertArrayHasKey('constancia_seguridad_estructural', (new ValidarVigenciaDocumentos)->avisos($escuela->id));
    }

    public function test_sin_vencimiento_del_registro_no_hay_aviso(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarConstancia($escuela, []);

        $this->assertSame([], (new ValidarVigenciaDocumentos)->avisos($escuela->id));
    }

    /** perito_registro_vigencia es histórico y su semántica no se pudo determinar: no se evalúa. */
    public function test_el_dato_historico_del_registro_no_se_evalua(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarConstancia($escuela, ['tipoVigencia' => null, 'calidadProfesional' => null, 'peritoRegistroVigencia' => '2025-01-01', 'fechaEmision' => '2026-01-15']);

        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id));
        $this->assertSame([], (new ValidarVigenciaDocumentos)->avisos($escuela->id));
    }

    public function test_una_constancia_sin_fecha_expresa_no_vence(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        $this->registrarConstancia($escuela, ['fechaEmision' => now()->subYears(8)->toDateString(), 'tipoVigencia' => 'sin_fecha_expresa']);

        $this->assertSame([], (new ValidarVigenciaDocumentos)->ejecutar($escuela->id), 'no se supone un plazo de cinco años que el documento no declara');
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

    /** WS-7b-1 U4: un documento vencido que dejó de aplicar al tipo de persona no bloquea. */
    public function test_un_documento_vencido_que_ya_no_aplica_se_ignora(): void
    {
        (new TiposDocumentosSeeder)->run();
        DB::table('tipos_documentos')->insert([
            'clave' => 'solo_moral_con_vigencia', 'nombre' => 'Documento solo moral',
            'aplica_persona' => 'moral', 'ambito' => 'plantel', 'vigencia_max_dias' => 30,
        ]);
        $escuela = $this->crearEscuela();
        ResponsableLegal::where('escuela_id', $escuela->id)->update(['tipo_persona' => 'moral']);
        DB::table('documentos_plantel')->insert([
            'plantel_id' => $escuela->plantel_id,
            'tipo_documento_id' => DB::table('tipos_documentos')->where('clave', 'solo_moral_con_vigencia')->value('id'),
            'archivo_path' => 'plantel/x/solo-moral.pdf',
            'fecha_emision' => now()->subDays(60)->toDateString(),
            'fecha_vigencia' => now()->subDays(30)->toDateString(),
            'estado_validacion' => 'pendiente',
        ]);

        // Control: como moral, el vencimiento sí cuenta.
        $this->assertArrayHasKey('solo_moral_con_vigencia', (new ValidarVigenciaDocumentos)->ejecutar($escuela->id));

        ResponsableLegal::where('escuela_id', $escuela->id)->update(['tipo_persona' => 'fisica']);

        $this->assertArrayNotHasKey('solo_moral_con_vigencia', (new ValidarVigenciaDocumentos)->ejecutar($escuela->id));
    }
}
