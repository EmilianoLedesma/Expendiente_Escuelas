<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\EnviarTramite;
use App\Application\Tramite\TramiteEditable;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\EvaluacionValidacion;
use App\Models\NivelEducativo;
use Database\Seeders\CatalogoMinimoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\TestCase;

/** WS-7a §4, §8: the send re-evaluates inside its own transaction and never trusts a stored row. */
class EnviarTramiteTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use ConNivelParaResponsables;
    use RefreshDatabase;

    private function estadoDe(EscuelaNivel $nivel): string
    {
        return (string) DB::table('estados_expediente')->where('id', $nivel->fresh()->estado_id)->value('clave');
    }

    private function usuario(Escuela $escuela): int
    {
        return (int) $escuela->solicitante->user->id;
    }

    private function regla(string $clave, string $tipoRegla, string $tipoCalculo, string $ambito, float $valor, string $concepto, ?string $cargo = null): void
    {
        $nivel = DB::table('niveles_educativos')->where('clave', 'primaria')->value('id');
        DB::table('reglas_validacion')->insert([
            'clave' => $clave, 'nivel_educativo_id' => $nivel,
            'tipo_regla' => $tipoRegla, 'tipo_calculo' => $tipoCalculo, 'ambito' => $ambito, 'redondeo' => 'na',
            'concepto' => $concepto, 'valor_numerico' => $valor, 'unidad' => 'prueba', 'fuente' => 'prueba',
            'cargo_puesto_id' => $cargo === null ? null : DB::table('cargos_puestos')->where('nivel_educativo_id', $nivel)->where('nombre', $cargo)->value('id'),
        ]);
    }

    /** @param callable(): mixed $enviar */
    private function rechaza(callable $enviar, string $etapa, string $fragmento): void
    {
        try {
            $enviar();
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame($etapa, $e->etapaFaltante);
            $this->assertStringContainsString($fragmento, $e->getMessage());
        }
    }

    public function test_envia_todos_los_niveles_a_revision_con_su_historial_y_el_reporte(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);

        $validacion = app(EnviarTramite::class)->ejecutar($escuela->id, $this->usuario($escuela));

        $this->assertSame('en_revision', $this->estadoDe($nivel));
        $historial = DB::table('historial_estados_expediente')->where('escuela_nivel_id', $nivel->id)->sole();
        $this->assertSame($this->usuario($escuela), (int) $historial->usuario_sedeq_id);
        $this->assertSame('Enviado por el solicitante', $historial->comentario);
        $this->assertSame((int) DB::table('estados_expediente')->where('clave', 'en_revision')->value('id'), (int) $historial->estado_id);
        $this->assertSame(now()->toDateString(), substr((string) $historial->fecha, 0, 10));

        $evaluacion = EvaluacionValidacion::sole();
        $this->assertSame($validacion->evaluacionId, $evaluacion->id);
        $this->assertTrue($evaluacion->lista_para_envio);
        $this->assertSame(EjecutarValidacionFinal::REGLA_ENVIO, $evaluacion->resultados['regla_envio']);
        Storage::disk('documentos')->assertExists($evaluacion->archivo_path);
        $this->assertFalse(app(TramiteEditable::class)->esEditable($escuela->id));
    }

    public function test_el_reporte_se_guarda_despues_del_historial(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $inserts = [];
        DB::listen(function ($query) use (&$inserts) {
            if (str_starts_with($query->sql, 'insert into')) {
                $inserts[] = $query->sql;
            }
        });

        app(EnviarTramite::class)->ejecutar($escuela->id, $this->usuario($escuela));

        $historial = array_key_first(array_filter($inserts, fn (string $sql) => str_contains($sql, '"historial_estados_expediente"')));
        $evaluacion = array_key_first(array_filter($inserts, fn (string $sql) => str_contains($sql, '"evaluaciones_validacion"')));
        $this->assertNotNull($historial);
        $this->assertNotNull($evaluacion);
        $this->assertLessThan($evaluacion, $historial);
    }

    public function test_bloquea_los_niveles_y_luego_la_escuela(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $bloqueos = [];
        DB::listen(function ($query) use (&$bloqueos) {
            if (str_ends_with($query->sql, 'for update')) {
                $bloqueos[] = $query->sql;
            }
        });

        app(EnviarTramite::class)->ejecutar($escuela->id, $this->usuario($escuela));

        $this->assertStringContainsString('from "escuela_niveles"', $bloqueos[0]);
        $this->assertStringContainsString('from "escuelas"', $bloqueos[1]);
    }

    /**
     * Review M1: a nivel committed between the nivel lock and the escuela lock must be
     * seen. Simulated by inserting it right after the first nivel lock: the check runs
     * on the set re-read after both locks, so the (sent) newcomer stops the send.
     */
    public function test_los_niveles_se_vuelven_a_leer_despues_de_tomar_los_bloqueos(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $insertado = false;
        DB::listen(function ($query) use (&$insertado, $escuela) {
            if (! $insertado && str_contains($query->sql, 'from "escuela_niveles"') && str_ends_with($query->sql, 'for update')) {
                $insertado = true;
                $this->nivelEn($escuela, 'secundaria', 'en_revision');
            }
        });

        $this->rechaza(fn () => app(EnviarTramite::class)->ejecutar($escuela->id, $this->usuario($escuela)), TramiteEditable::ENVIADO, 'El trámite ya fue enviado.');
        $this->assertSame('en_captura', $this->estadoDe($nivel));
    }

    public function test_si_falla_antes_de_guardar_no_deja_reporte_ni_cambia_estados(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);

        try {
            // usuario_sedeq_id is NOT NULL REFERENCES users(id): an unknown id fails the history insert, before guardar().
            app(EnviarTramite::class)->ejecutar($escuela->id, 999999);
            $this->fail('Se esperaba QueryException.');
        } catch (QueryException) {
        }

        $this->assertSame('en_captura', $this->estadoDe($nivel));
        $this->assertSame(0, DB::table('historial_estados_expediente')->count());
        $this->assertSame(0, EvaluacionValidacion::count());
        $this->assertSame([], Storage::disk('documentos')->allFiles('validaciones'));
    }

    public function test_enviar_dos_veces_no_duplica_historial_ni_reporte(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        app(EnviarTramite::class)->ejecutar($escuela->id, $this->usuario($escuela));

        $this->rechaza(fn () => app(EnviarTramite::class)->ejecutar($escuela->id, $this->usuario($escuela)), TramiteEditable::ENVIADO, 'El trámite ya fue enviado.');

        $this->assertSame(1, DB::table('historial_estados_expediente')->count());
        $this->assertSame(1, EvaluacionValidacion::count());
    }

    public function test_un_tramite_con_niveles_en_estados_mezclados_no_se_envia(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'secundaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);

        $this->rechaza(fn () => app(EnviarTramite::class)->ejecutar($escuela->id, $this->usuario($escuela)), TramiteEditable::ENVIADO, 'El trámite ya fue enviado.');
        $this->assertSame('en_captura', $this->estadoDe($nivel));
    }

    public function test_la_capacidad_que_no_se_cumple_impide_enviar(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        // Fixture plantilla: Director Técnico + Docente Titular; this rule asks for a Docente de Educación Física.
        $this->regla('primaria.personal.docente_ef_prueba', 'personal', 'personal_obligatorio', 'escuela', 1, 'Docente de Educación Física (prueba)', 'Docente de Educación Física');

        $this->rechaza(fn () => app(EnviarTramite::class)->ejecutar($escuela->id, $this->usuario($escuela)), EjecutarValidacionFinal::ETAPA, 'Capacidad instalada · Primaria: Docente de Educación Física (prueba)');
        $this->assertSame('en_captura', $this->estadoDe($nivel));
        $this->assertSame(0, EvaluacionValidacion::count());
    }

    public function test_una_captura_faltante_impide_enviar(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $this->regla('primaria.superficie.aulas', 'superficie', 'ratio_por_alumno', 'aula', 0.90, 'Superficie de aulas');

        $this->rechaza(fn () => app(EnviarTramite::class)->ejecutar($escuela->id, $this->usuario($escuela)), EjecutarValidacionFinal::ETAPA, 'Capacidad instalada · Primaria: Superficie de aulas');
    }

    public function test_las_reglas_estructurales_solas_no_impiden_enviar(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $this->regla('primaria.infraestructura.altura_aulas', 'infraestructura', 'minimo_fijo', 'aula', 2.70, 'Altura de aulas');

        app(EnviarTramite::class)->ejecutar($escuela->id, $this->usuario($escuela));

        $this->assertSame('en_revision', $this->estadoDe($nivel));
    }

    public function test_un_documento_que_no_coincide_impide_enviar(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela, reemplazos: [
            'ine' => new DatosDocumento(identidadNombre: 'PEREZ GOMEZ JUAN', identidadCurp: 'PEGJ800101HQTRML08'),
        ]);

        $this->rechaza(fn () => app(EnviarTramite::class)->ejecutar($escuela->id, $this->usuario($escuela)), EjecutarValidacionFinal::ETAPA, 'CURP');
    }

    /** Review Focus 1: a stored "lista" evaluation is not enough when the data changed after it. */
    public function test_una_evaluacion_guardada_como_lista_no_basta_si_los_datos_cambiaron(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $this->assertTrue(app(EjecutarValidacionFinal::class)->ejecutar($escuela->id)->listaParaEnvio);
        DB::table('credenciales_ine')->update(['curp' => 'PEGJ800101HQTRML08']);

        $this->rechaza(fn () => app(EnviarTramite::class)->ejecutar($escuela->id, $this->usuario($escuela)), EjecutarValidacionFinal::ETAPA, 'CURP');

        $this->assertSame('en_captura', $this->estadoDe($nivel));
        $this->assertSame(1, EvaluacionValidacion::count());
    }

    public function test_un_tramite_incompleto_no_se_envia(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        (new CatalogoMinimoSeeder)->run();
        $this->registrarResponsable($escuela);

        $this->rechaza(fn () => app(EnviarTramite::class)->ejecutar($escuela->id, $this->usuario($escuela)), EjecutarValidacionFinal::ETAPA, 'Completa todas las secciones');
    }

    public function test_sin_niveles_no_se_envia(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        (new CatalogoMinimoSeeder)->run();
        $this->registrarResponsable($escuela);
        $this->subirTodosConDatos($escuela);

        $this->rechaza(fn () => app(EnviarTramite::class)->ejecutar($escuela->id, $this->usuario($escuela)), EjecutarValidacionFinal::ETAPA, 'Completa todas las secciones');
        $this->assertSame(0, DB::table('historial_estados_expediente')->count());
    }

    public function test_registra_el_envio_en_el_log_tras_el_commit(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        Log::spy();

        app(EnviarTramite::class)->ejecutar($escuela->id, $this->usuario($escuela));

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $mensaje, array $contexto) => $mensaje === 'Trámite enviado a SEDEQ' && $contexto['escuela_id'] === $escuela->id && $contexto['niveles'] === 1)
            ->once();
    }

    /**
     * The use case does not check ownership: the caller gates it on EscuelaPolicy::update.
     * A responsable del nivel may write its nivel (EscuelaNivelPolicy::update) but never
     * holds update on the escuela, so the send is owner-only (ADR-015).
     */
    public function test_solo_el_dueno_tiene_la_habilidad_que_protege_el_envio(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $responsable = $this->responsableDe($nivel);

        $this->assertTrue($escuela->solicitante->user->can('update', $escuela));
        $this->assertTrue($responsable->can('update', $nivel));
        $this->assertFalse($responsable->can('update', $escuela));
    }
}
