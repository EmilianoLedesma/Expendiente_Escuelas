<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\EscuelaNiveles\RegistrarDatosNivel;
use App\Application\EscuelaNiveles\RegistrarNivelesSeleccionados;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Infraestructura\DTO\DatosInfraestructuraNivel;
use App\Application\Infraestructura\RegistrarInfraestructuraNivel;
use App\Application\Inmueble\DTO\DatosInmueble;
use App\Application\Inmueble\RegistrarDatosInmueble;
use App\Application\Matricula\DTO\DatosMatricula;
use App\Application\Matricula\RegistrarMatricula;
use App\Application\Mobiliario\RegistrarMobiliarioNivel;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Application\Tramite\EliminarTramite;
use App\Application\Tramite\TramiteEditable;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Livewire\Tramite\Paso2Documentos;
use App\Livewire\Tramite\Paso3\InfraestructuraNivel;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\EvaluacionValidacion;
use App\Models\NivelEducativo;
use Closure;
use Database\Seeders\MobiliarioConceptosSeeder;
use Database\Seeders\TiposEspaciosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\TestCase;

/**
 * WS-7a §5.3–5.4: every write use case refuses a sent trámite (one guard, inside the
 * writer's own transaction), and plantel-scope writes refuse while any escuela of the
 * plantel is sent.
 */
class GuardaTramiteEnviadoTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use RefreshDatabase;

    private function enviar(Escuela $escuela): void
    {
        EscuelaNivel::where('escuela_id', $escuela->id)->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);
    }

    private function pdf(string $nombre): UploadedFile
    {
        return UploadedFile::fake()->create($nombre, 10, 'application/pdf');
    }

    /** @return array<string, array{Closure(Escuela, EscuelaNivel): mixed}> */
    public static function escrituras(): array
    {
        $pdf = fn (string $nombre) => UploadedFile::fake()->create($nombre, 10, 'application/pdf');

        return [
            'MarcarPasoCompletado (cubre los seis Paso 3)' => [fn (Escuela $e, EscuelaNivel $n) => app(MarcarPasoCompletado::class)->ejecutar($n->id, 'plan_estudios')],
            'RegistrarResponsableLegal' => [fn (Escuela $e, EscuelaNivel $n) => app(RegistrarResponsableLegal::class)->ejecutar($e->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'))],
            'RegistrarNivelesSeleccionados' => [fn (Escuela $e, EscuelaNivel $n) => app(RegistrarNivelesSeleccionados::class)->ejecutar($e->id, [(int) NivelEducativo::where('clave', 'secundaria')->value('id')])],
            'RegistrarDocumento ámbito escuela' => [fn (Escuela $e, EscuelaNivel $n) => app(RegistrarDocumento::class)->ejecutar($e->id, 'acta_nacimiento', $pdf('acta.pdf'), new DatosDocumento)],
            'RegistrarDocumento ámbito nivel' => [fn (Escuela $e, EscuelaNivel $n) => app(RegistrarDocumento::class)->ejecutar($e->id, 'formato_solicitud', $pdf('formato.pdf'), new DatosDocumento, $n->id)],
            'RegistrarDocumento ámbito plantel' => [fn (Escuela $e, EscuelaNivel $n) => app(RegistrarDocumento::class)->ejecutar($e->id, 'plano_inmueble', $pdf('plano.pdf'), new DatosDocumento)],
            'RegistrarDatosNivel' => [fn (Escuela $e, EscuelaNivel $n) => app(RegistrarDatosNivel::class)->ejecutar($n->id, 'vespertino', 'mixto')],
            'EjecutarValidacionFinal::ejecutar' => [fn (Escuela $e, EscuelaNivel $n) => app(EjecutarValidacionFinal::class)->ejecutar($e->id)],
        ];
    }

    #[DataProvider('escrituras')]
    public function test_cada_escritura_pasa_mientras_el_tramite_esta_en_captura(Closure $escribir): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);

        $escribir($escuela, $nivel);

        $this->addToAssertionCount(1);
    }

    #[DataProvider('escrituras')]
    public function test_cada_escritura_rechaza_un_tramite_enviado(Closure $escribir): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $this->enviar($escuela);

        try {
            $escribir($escuela, $nivel);
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(TramiteEditable::ENVIADO, $e->etapaFaltante);
        }
    }

    public function test_un_paso3_de_un_tramite_enviado_se_revierte_completo(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $this->enviar($escuela);
        $primero = (int) DB::table('grados')->where('nivel_educativo_id', $nivel->nivel_educativo_id)->where('orden', 1)->value('id');

        try {
            app(RegistrarMatricula::class)->ejecutar($nivel->id, new DatosMatricula(grupos: [['gradoId' => $primero, 'grupo' => 'B', 'alumnos' => 99]]));
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame('El trámite ya se envió a SEDEQ y no puede modificarse.', $e->getMessage());
        }

        // The delete-and-recreate ran before the guard; the rollback restored the fixture's 25 alumnos.
        $this->assertSame(25, (int) DB::table('matricula_grados')->where('escuela_nivel_id', $nivel->id)->sum('cantidad_alumnos'));
    }

    public function test_validar_de_nuevo_no_guarda_otra_evaluacion_tras_el_envio(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        app(EjecutarValidacionFinal::class)->ejecutar($escuela->id);
        $this->enviar($escuela);

        try {
            app(EjecutarValidacionFinal::class)->ejecutar($escuela->id);
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida) {
        }

        $this->assertSame(1, EvaluacionValidacion::count());
    }

    public function test_la_guarda_toma_un_bloqueo_compartido_de_la_escuela(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $bloqueos = [];
        DB::listen(function ($query) use (&$bloqueos) {
            if (str_ends_with($query->sql, 'for share')) {
                $bloqueos[] = $query->sql;
            }
        });

        app(TramiteEditable::class)->asegurarEscuela($escuela->id);

        $this->assertCount(1, $bloqueos);
        $this->assertStringContainsString('from "escuelas"', $bloqueos[0]);
    }

    public function test_eliminar_bloquea_los_niveles_antes_que_la_escuela(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $bloqueos = [];
        DB::listen(function ($query) use (&$bloqueos) {
            if (str_ends_with($query->sql, 'for update')) {
                $bloqueos[] = $query->sql;
            }
        });

        app(EliminarTramite::class)->ejecutar($escuela->id);

        $this->assertStringContainsString('from "escuela_niveles"', $bloqueos[0]);
        $this->assertStringContainsString('from "escuelas"', $bloqueos[1]);
    }

    /** @return array<string, array{0: Closure(Escuela, EscuelaNivel): mixed, 1?: Closure(EscuelaNivel): mixed}> */
    public static function escriturasDelNivel(): array
    {
        return [
            'MarcarPasoCompletado' => [fn (Escuela $e, EscuelaNivel $n) => app(MarcarPasoCompletado::class)->ejecutar($n->id, 'plan_estudios')],
            'RegistrarInfraestructuraNivel' => [fn (Escuela $e, EscuelaNivel $n) => app(RegistrarInfraestructuraNivel::class)->ejecutar($e->plantel_id, $n->id, new DatosInfraestructuraNivel(espacios: [], sanitarios: [], numeroAulas: 6, superficieAulasM2: 240.0))],
            'RegistrarDatosInmueble' => [fn (Escuela $e, EscuelaNivel $n) => app(RegistrarDatosInmueble::class)->ejecutar($e->plantel_id, $n->id, new DatosInmueble(metrosTotales: 800.0, metrosConstruidos: 500.0))],
            'RegistrarMobiliarioNivel' => [
                fn (Escuela $e, EscuelaNivel $n) => app(RegistrarMobiliarioNivel::class)->ejecutar($n->id, [(int) DB::table('mobiliario_conceptos')->value('id') => 3]),
                // Mobiliario is Inicial-only: the fixture's primaria nivel is relabelled.
                function (EscuelaNivel $n) {
                    (new MobiliarioConceptosSeeder)->run();
                    $n->update(['nivel_educativo_id' => NivelEducativo::where('clave', 'inicial')->value('id')]);
                },
            ],
        ];
    }

    /**
     * Lock order (Order note 3): the nivel is locked before the first write or the
     * escuela guard, so a writer never holds child rows / the escuela while waiting
     * for a nivel that EliminarTramite or EnviarTramite already holds.
     */
    #[DataProvider('escriturasDelNivel')]
    public function test_cada_escritura_del_nivel_bloquea_el_nivel_antes_de_escribir(Closure $escribir, ?Closure $preparar = null): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        if ($preparar !== null) {
            $preparar($nivel);
        }
        $sql = [];
        $escuchar = false;
        DB::listen(function ($query) use (&$sql, &$escuchar) {
            if ($escuchar) {
                $sql[] = $query->sql;
            }
        });

        $escuchar = true;
        $escribir($escuela, $nivel->fresh());

        $primera = fn (Closure $cumple): ?int => array_key_first(array_filter($sql, $cumple));
        $bloqueoNivel = $primera(fn (string $s) => str_contains($s, 'from "escuela_niveles"') && preg_match('/for (share|update)$/', $s) === 1);
        $primeraEscritura = $primera(fn (string $s) => preg_match('/^(insert|update|delete)\b/', $s) === 1 || (str_contains($s, 'from "escuelas"') && str_ends_with($s, 'for share')));

        $this->assertNotNull($bloqueoNivel, 'El nivel nunca se bloqueó.');
        $this->assertNotNull($primeraEscritura);
        $this->assertLessThan($primeraEscritura, $bloqueoNivel, "Se escribió antes de bloquear el nivel: {$sql[$primeraEscritura]}");
    }

    public function test_la_guarda_del_plantel_toma_un_bloqueo_compartido_de_sus_escuelas(): void
    {
        [$escuela] = $this->plantelConHermanaEnviada();
        $bloqueos = [];
        DB::listen(function ($query) use (&$bloqueos) {
            if (str_ends_with($query->sql, 'for share')) {
                $bloqueos[] = $query->sql;
            }
        });

        try {
            app(TramiteEditable::class)->asegurarPlantel($escuela->plantel_id);
        } catch (PrecondicionIncumplida) {
        }

        $this->assertCount(1, $bloqueos);
        $this->assertStringContainsString('from "escuelas" where "plantel_id" = ?', $bloqueos[0]);
    }

    // --- Shared plantel (spec §5.4, ADR-009: same owner, several escuelas per plantel) ---

    /** @return array{0: Escuela, 1: EscuelaNivel} an editable escuela on the plantel of a sent sibling of the same owner */
    private function plantelConHermanaEnviada(): array
    {
        $hermana = $this->crearEscuelaConPlantel();
        $this->completarTramite($hermana);
        $escuela = Escuela::create(['plantel_id' => $hermana->plantel_id, 'solicitante_id' => $hermana->solicitante_id]);
        $nivel = $this->completarTramite($escuela);
        (new TiposEspaciosSeeder)->run();
        $this->enviar($hermana);

        return [$escuela, $nivel];
    }

    /** @return array{tipoEspacioId: int, cantidad: int|null, superficieM2: float|null, capacidadPromedio: int|null, ventilacionNatural: bool|null, iluminacionNatural: bool|null, destinadoA: string|null, campoFutbol: null, materialesBiblioteca: list<array<string, mixed>>} */
    private function areasVerdes(): array
    {
        return [
            'tipoEspacioId' => (int) DB::table('tipos_espacios')->where('clave', 'areas_verdes')->value('id'),
            'cantidad' => 1, 'superficieM2' => 120.0, 'capacidadPromedio' => null,
            'ventilacionNatural' => null, 'iluminacionNatural' => null, 'destinadoA' => null,
            'campoFutbol' => null, 'materialesBiblioteca' => [],
        ];
    }

    public function test_un_documento_del_plantel_no_se_reemplaza_si_otra_escuela_del_plantel_ya_se_envio(): void
    {
        [$escuela] = $this->plantelConHermanaEnviada();
        $tipo = DB::table('tipos_documentos')->where('clave', 'plano_inmueble')->value('id');
        $antes = DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->where('tipo_documento_id', $tipo)->value('archivo_path');

        try {
            app(RegistrarDocumento::class)->ejecutar($escuela->id, 'plano_inmueble', $this->pdf('plano-nuevo.pdf'), new DatosDocumento);
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(TramiteEditable::ENVIADO, $e->etapaFaltante);
            $this->assertStringContainsString('Un trámite de este plantel ya se envió a SEDEQ', $e->getMessage());
        }

        $this->assertSame($antes, DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->where('tipo_documento_id', $tipo)->value('archivo_path'));

        // Control: the escuela's own (not shared) documents can still change.
        app(RegistrarDocumento::class)->ejecutar($escuela->id, 'acta_nacimiento', $this->pdf('acta-nueva.pdf'), new DatosDocumento);
        $this->addToAssertionCount(1);
    }

    public function test_no_se_agregan_espacios_ni_sanitarios_al_plantel_pero_las_aulas_del_nivel_si(): void
    {
        [$escuela, $nivel] = $this->plantelConHermanaEnviada();
        $registrar = app(RegistrarInfraestructuraNivel::class);
        $sanitario = ['categoria' => 'alumnado_masculino', 'cantidadRetretes' => 4, 'cantidadMingitorios' => 3, 'cantidadLavabos' => 4, 'superficieM2' => 12.0, 'ventilacionNatural' => true, 'iluminacionNatural' => true, 'cantidadBacinicas' => null];

        foreach ([
            'espacio' => new DatosInfraestructuraNivel(espacios: [$this->areasVerdes()], sanitarios: [], numeroAulas: 6, superficieAulasM2: 240.0),
            'sanitario' => new DatosInfraestructuraNivel(espacios: [], sanitarios: [$sanitario], numeroAulas: 6, superficieAulasM2: 240.0),
        ] as $caso => $datos) {
            try {
                $registrar->ejecutar($escuela->plantel_id, $nivel->id, $datos);
                $this->fail("Se esperaba PrecondicionIncumplida ({$caso}).");
            } catch (PrecondicionIncumplida $e) {
                $this->assertSame(TramiteEditable::ENVIADO, $e->etapaFaltante, $caso);
            }
        }

        $this->assertDatabaseCount('instalaciones_espacios', 0);
        $this->assertDatabaseCount('sanitarios', 0);
        $this->assertDatabaseCount('aulas_nivel', 0);

        $registrar->ejecutar($escuela->plantel_id, $nivel->id, new DatosInfraestructuraNivel(espacios: [], sanitarios: [], numeroAulas: 6, superficieAulasM2: 240.0));

        $this->assertDatabaseHas('aulas_nivel', ['escuela_nivel_id' => $nivel->id, 'numero_aulas' => 6]);
    }

    public function test_la_pagina_de_infraestructura_muestra_el_error_del_plantel(): void
    {
        [$escuela, $nivel] = $this->plantelConHermanaEnviada();
        $verdes = $this->areasVerdes()['tipoEspacioId'];

        Livewire::actingAs($escuela->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $nivel])
            ->set("espacios.{$verdes}.superficieM2", 120)
            ->set('numeroAulas', 6)
            ->set('superficieAulasM2', 240)
            ->call('guardar')
            ->assertHasErrors('plantel')
            ->assertNoRedirect()
            ->assertSee('Un trámite de este plantel ya se envió a SEDEQ');
    }

    public function test_la_pagina_de_documentos_muestra_el_error_del_plantel(): void
    {
        [$escuela] = $this->plantelConHermanaEnviada();
        $this->actingAs($escuela->solicitante->user);

        Livewire::withQueryParams(['corregir' => 'plano_inmueble'])
            ->test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.plano_inmueble', $this->pdf('plano-nuevo.pdf'))
            ->call('guardarDocumentoSimple', 'plano_inmueble')
            ->assertHasErrors('archivos.plano_inmueble')
            ->assertNoRedirect()
            ->assertSee('Un trámite de este plantel ya se envió a SEDEQ');
    }
}
