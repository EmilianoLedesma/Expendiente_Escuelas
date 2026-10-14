<?php

namespace Tests\Feature\Application\EscuelaNiveles;

use App\Application\EscuelaNiveles\RegistrarNivelesSeleccionados;
use App\Application\EscuelaNiveles\ResumenEliminacionNivel;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ResponsablesNivel\InvitarResponsableNivel;
use App\Models\AulaNivel;
use App\Models\DocumentoEscuelaNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\ResponsableNivel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\CompletaPaso2;
use Tests\Concerns\CompletaPaso24;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\TestCase;

class RegistrarNivelesSeleccionadosTest extends TestCase
{
    use CompletaPaso2;
    use CompletaPaso24;
    use ConNivelParaResponsables;
    use RefreshDatabase;

    private function crearEscuela(bool $conPaso2 = true): Escuela
    {
        (new CatalogoMinimoSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        if ($conPaso2) {
            $this->completarPaso2($escuela->id);
        }

        return $escuela;
    }

    private function id(string $clave): int
    {
        return (int) NivelEducativo::where('clave', $clave)->value('id');
    }

    private function registrar(): RegistrarNivelesSeleccionados
    {
        return app(RegistrarNivelesSeleccionados::class);
    }

    public function test_crea_un_escuela_nivel_por_cada_nivel_seleccionado(): void
    {
        $escuela = $this->crearEscuela();
        $estadoEnCaptura = DB::table('estados_expediente')->where('clave', 'en_captura')->first();

        $this->registrar()->ejecutar($escuela->id, agregar: [$this->id('preescolar'), $this->id('primaria')]);

        $this->assertDatabaseCount('escuela_niveles', 2);
        foreach (['preescolar', 'primaria'] as $clave) {
            $this->assertDatabaseHas('escuela_niveles', [
                'escuela_id' => $escuela->id,
                'nivel_educativo_id' => $this->id($clave),
                'tipo_tramite' => 'alta_nueva',
                'estado_id' => $estadoEnCaptura->id,
            ]);
        }
    }

    /** Reescrito (WS-7b-1): las dos listas vacías siguen siendo un error. */
    public function test_rechaza_agregar_y_quitar_vacios(): void
    {
        $escuela = $this->crearEscuela();

        $this->expectException(InvalidArgumentException::class);

        $this->registrar()->ejecutar($escuela->id, agregar: [], quitar: []);
    }

    public function test_rechaza_un_nivel_fuera_de_educacion_basica(): void
    {
        $escuela = $this->crearEscuela();

        try {
            $this->registrar()->ejecutar($escuela->id, agregar: [$this->id('media_superior')]);
            $this->fail('Se esperaba InvalidArgumentException.');
        } catch (InvalidArgumentException) {
        }

        $this->assertDatabaseCount('escuela_niveles', 0);
    }

    /** Reescrito (WS-7b-1): agregar un nivel ya elegido no lo duplica. */
    public function test_agregar_un_nivel_ya_elegido_no_lo_duplica(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar()->ejecutar($escuela->id, agregar: [$this->id('preescolar')]);
        $this->registrar()->ejecutar($escuela->id, agregar: [$this->id('preescolar')]);

        $this->assertDatabaseCount('escuela_niveles', 1);
    }

    public function test_agregar_conserva_los_niveles_existentes(): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar()->ejecutar($escuela->id, agregar: [$this->id('preescolar')]);
        $preescolar = EscuelaNivel::where('escuela_id', $escuela->id)->value('id');

        $this->registrar()->ejecutar($escuela->id, agregar: [$this->id('primaria')]);

        $this->assertDatabaseCount('escuela_niveles', 2);
        $this->assertDatabaseHas('escuela_niveles', ['id' => $preescolar]);
    }

    public function test_quitar_un_nivel_sin_datos(): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar()->ejecutar($escuela->id, agregar: [$this->id('preescolar'), $this->id('primaria')]);

        $this->registrar()->ejecutar($escuela->id, agregar: [], quitar: [$this->id('primaria')]);

        $this->assertSame([$this->id('preescolar')], EscuelaNivel::where('escuela_id', $escuela->id)->pluck('nivel_educativo_id')->map(fn ($id) => (int) $id)->all());
    }

    /** Review Focus 1: una pestaña vieja solo quita lo que nombra; un nivel agregado en otra pestaña sobrevive. */
    public function test_solo_quita_los_niveles_indicados(): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar()->ejecutar($escuela->id, agregar: [$this->id('preescolar'), $this->id('primaria'), $this->id('secundaria')]);

        $this->registrar()->ejecutar($escuela->id, agregar: [], quitar: [$this->id('primaria')]);

        $this->assertEqualsCanonicalizing(
            [$this->id('preescolar'), $this->id('secundaria')],
            EscuelaNivel::where('escuela_id', $escuela->id)->pluck('nivel_educativo_id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    public function test_quitar_un_nivel_con_datos_borra_en_cascada_sus_archivos_y_la_cuenta_del_responsable(): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar()->ejecutar($escuela->id, agregar: [$this->id('preescolar'), $this->id('primaria')]);
        $preescolar = EscuelaNivel::where('escuela_id', $escuela->id)->where('nivel_educativo_id', $this->id('preescolar'))->firstOrFail();
        $this->completarPaso24($preescolar->id);
        AulaNivel::create(['escuela_nivel_id' => $preescolar->id, 'numero_aulas' => 3]);
        $responsable = $this->responsableDe($preescolar);
        $rutas = DocumentoEscuelaNivel::where('escuela_nivel_id', $preescolar->id)->pluck('archivo_path')->all();
        $this->assertNotEmpty($rutas);
        foreach ($rutas as $ruta) {
            Storage::disk('documentos')->assertExists($ruta);
        }
        $confirmado = app(ResumenEliminacionNivel::class)->ejecutar($escuela->id, [$this->id('preescolar')]);

        $this->assertGreaterThan(0, $confirmado[$this->id('preescolar')]['documentos']);
        $this->assertSame(1, $confirmado[$this->id('preescolar')]['recibos']);
        $this->assertSame(1, $confirmado[$this->id('preescolar')]['espacios']);
        $this->assertSame(1, $confirmado[$this->id('preescolar')]['responsables']);

        $this->registrar()->ejecutar($escuela->id, agregar: [], quitar: [$this->id('preescolar')], confirmado: $confirmado);

        $this->assertDatabaseMissing('escuela_niveles', ['id' => $preescolar->id]);
        $this->assertSame(0, DocumentoEscuelaNivel::where('escuela_nivel_id', $preescolar->id)->count());
        $this->assertDatabaseCount('recibos_pago_derechos', 0);
        $this->assertDatabaseCount('aulas_nivel', 0);
        $this->assertDatabaseCount('responsables_nivel', 0);
        $this->assertDatabaseMissing('users', ['id' => $responsable->id]);
        foreach ($rutas as $ruta) {
            Storage::disk('documentos')->assertMissing($ruta);
        }
    }

    /** Los archivos se borran solo si el borrado se confirma: una transacción exterior revertida los conserva. */
    public function test_si_la_transaccion_se_revierte_los_archivos_y_el_nivel_se_conservan(): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar()->ejecutar($escuela->id, agregar: [$this->id('preescolar'), $this->id('primaria')]);
        $preescolar = EscuelaNivel::where('escuela_id', $escuela->id)->where('nivel_educativo_id', $this->id('preescolar'))->firstOrFail();
        $this->completarPaso24($preescolar->id);
        $rutas = DocumentoEscuelaNivel::where('escuela_nivel_id', $preescolar->id)->pluck('archivo_path')->all();
        $this->assertNotEmpty($rutas);
        $confirmado = app(ResumenEliminacionNivel::class)->ejecutar($escuela->id, [$this->id('preescolar')]);

        try {
            DB::transaction(function () use ($escuela, $confirmado) {
                $this->registrar()->ejecutar($escuela->id, agregar: [], quitar: [$this->id('preescolar')], confirmado: $confirmado);

                throw new RuntimeException('revertir');
            });
            $this->fail('Se esperaba RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertSame('revertir', $e->getMessage());
        }

        $this->assertDatabaseHas('escuela_niveles', ['id' => $preescolar->id]);
        foreach ($rutas as $ruta) {
            Storage::disk('documentos')->assertExists($ruta);
        }
    }

    /**
     * Solo se borra la cuenta de un responsable invitado (rol responsable_nivel, sin fila de
     * solicitante), igual que la guarda de InvitarResponsableNivel.
     *
     * @return array<string, array{bool, bool}> [con solicitante, con rol]
     */
    public static function cuentasQueNoSonSoloDeResponsable(): array
    {
        return [
            'también es solicitante' => [true, true],
            'sin rol de responsable' => [false, false],
        ];
    }

    #[DataProvider('cuentasQueNoSonSoloDeResponsable')]
    public function test_una_cuenta_que_no_es_solo_de_responsable_se_conserva(bool $conSolicitante, bool $conRol): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar()->ejecutar($escuela->id, agregar: [$this->id('preescolar'), $this->id('primaria')]);
        $preescolar = EscuelaNivel::where('escuela_id', $escuela->id)->where('nivel_educativo_id', $this->id('preescolar'))->firstOrFail();
        $usuario = $this->responsableDe($preescolar);
        if ($conSolicitante) {
            Solicitante::factory()->create(['user_id' => $usuario->id]);
        }
        if (! $conRol) {
            $usuario->removeRole(InvitarResponsableNivel::ROL);
        }
        $confirmado = app(ResumenEliminacionNivel::class)->ejecutar($escuela->id, [$this->id('preescolar')]);

        $this->registrar()->ejecutar($escuela->id, agregar: [], quitar: [$this->id('preescolar')], confirmado: $confirmado);

        $this->assertDatabaseMissing('escuela_niveles', ['id' => $preescolar->id]);
        $this->assertDatabaseHas('users', ['id' => $usuario->id]);
    }

    /**
     * Datos guardados en la propia fila del nivel o en escuela_nivel_pasos también se borran:
     * cuentan como datos y piden confirmación.
     *
     * @return array<string, array{array<string, string>, bool}> [columnas del nivel, con paso completado]
     */
    public static function nivelesConPocosDatos(): array
    {
        return [
            'solo turno' => [['turno' => 'matutino'], false],
            'solo plan de estudios' => [['modalidad' => 'escolarizada', 'plan_estudios_referencia' => 'Plan 2022'], false],
            'solo un paso completado' => [[], true],
        ];
    }

    /** @param array<string, string> $columnas */
    #[DataProvider('nivelesConPocosDatos')]
    public function test_un_nivel_con_pocos_datos_pide_confirmacion(array $columnas, bool $conPaso): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar()->ejecutar($escuela->id, agregar: [$this->id('preescolar'), $this->id('primaria')]);
        $primaria = EscuelaNivel::where('escuela_id', $escuela->id)->where('nivel_educativo_id', $this->id('primaria'))->firstOrFail();
        if ($columnas !== []) {
            $primaria->update($columnas);
        }
        if ($conPaso) {
            (new PasosCapturaSeeder)->run();
            DB::table('escuela_nivel_pasos')->insert(['escuela_nivel_id' => $primaria->id, 'paso_captura_id' => DB::table('pasos_captura')->value('id'), 'estado' => 'completado']);
        }

        $this->assertTrue(ResumenEliminacionNivel::conDatos(app(ResumenEliminacionNivel::class)->ejecutar($escuela->id, [$this->id('primaria')])));

        try {
            $this->registrar()->ejecutar($escuela->id, agregar: [], quitar: [$this->id('primaria')]);
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(RegistrarNivelesSeleccionados::CONFIRMACION, $e->etapaFaltante);
        }

        $this->assertDatabaseHas('escuela_niveles', ['id' => $primaria->id]);
    }

    /** ADR-015 (enmendado): la cuenta se conserva si el responsable tiene otro nivel. */
    public function test_un_responsable_con_otro_nivel_conserva_su_cuenta(): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar()->ejecutar($escuela->id, agregar: [$this->id('preescolar'), $this->id('primaria')]);
        [$preescolar, $primaria] = EscuelaNivel::where('escuela_id', $escuela->id)->orderBy('nivel_educativo_id')->get()->all();
        $responsable = $this->responsableDe($preescolar, $primaria);
        $confirmado = app(ResumenEliminacionNivel::class)->ejecutar($escuela->id, [(int) $preescolar->nivel_educativo_id]);

        $this->registrar()->ejecutar($escuela->id, agregar: [], quitar: [(int) $preescolar->nivel_educativo_id], confirmado: $confirmado);

        $this->assertDatabaseHas('users', ['id' => $responsable->id]);
        $this->assertSame([$primaria->id], ResponsableNivel::where('user_id', $responsable->id)->pluck('escuela_nivel_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_conteos_confirmados_viejos_se_rechazan_sin_borrar(): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar()->ejecutar($escuela->id, agregar: [$this->id('preescolar'), $this->id('primaria')]);
        $primaria = EscuelaNivel::where('escuela_id', $escuela->id)->where('nivel_educativo_id', $this->id('primaria'))->firstOrFail();
        $confirmado = app(ResumenEliminacionNivel::class)->ejecutar($escuela->id, [$this->id('primaria')]);
        AulaNivel::create(['escuela_nivel_id' => $primaria->id, 'numero_aulas' => 6]);

        try {
            $this->registrar()->ejecutar($escuela->id, agregar: [], quitar: [$this->id('primaria')], confirmado: $confirmado);
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(RegistrarNivelesSeleccionados::CONFIRMACION, $e->etapaFaltante);
        }

        $this->assertDatabaseHas('escuela_niveles', ['id' => $primaria->id]);
    }

    /** Spec U3: quitar no exige Paso 2 completo (agregar sí). */
    public function test_quitar_no_exige_paso2_completo(): void
    {
        $escuela = $this->crearEscuela(conPaso2: false);
        $this->nivelEn($escuela, 'preescolar');
        $this->nivelEn($escuela, 'primaria');

        $this->registrar()->ejecutar($escuela->id, agregar: [], quitar: [$this->id('primaria')]);

        $this->assertDatabaseCount('escuela_niveles', 1);

        $this->expectException(PrecondicionIncumplida::class);
        $this->registrar()->ejecutar($escuela->id, agregar: [$this->id('secundaria')]);
    }

    public function test_quitar_el_ultimo_nivel_se_rechaza(): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar()->ejecutar($escuela->id, agregar: [$this->id('preescolar')]);

        try {
            $this->registrar()->ejecutar($escuela->id, agregar: [], quitar: [$this->id('preescolar')]);
            $this->fail('Se esperaba InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Eliminar trámite', $e->getMessage());
        }

        $this->assertDatabaseCount('escuela_niveles', 1);
    }

    /** Review Focus 3: un segundo "Sí, quitar" sobre un nivel que ya no está no falla. */
    public function test_quitar_dos_veces_el_mismo_nivel_es_un_no_op(): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar()->ejecutar($escuela->id, agregar: [$this->id('preescolar'), $this->id('primaria')]);

        $this->registrar()->ejecutar($escuela->id, agregar: [], quitar: [$this->id('primaria')]);
        $this->registrar()->ejecutar($escuela->id, agregar: [], quitar: [$this->id('primaria')]);

        $this->assertDatabaseCount('escuela_niveles', 1);
    }

    /**
     * Orden de bloqueo (spec U3, como EliminarTramite): niveles FOR UPDATE → escuela FOR SHARE
     * → documentos del nivel FOR UPDATE → borrado. Un RegistrarDocumento concurrente bloquea el
     * nivel primero, así que no puede dejar un archivo huérfano ni cruzar bloqueos (40P01).
     */
    public function test_bloquea_niveles_escuela_y_documentos_antes_de_borrar(): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar()->ejecutar($escuela->id, agregar: [$this->id('preescolar'), $this->id('primaria')]);
        $primaria = EscuelaNivel::where('escuela_id', $escuela->id)->where('nivel_educativo_id', $this->id('primaria'))->firstOrFail();
        $this->completarPaso24($primaria->id);
        $confirmado = app(ResumenEliminacionNivel::class)->ejecutar($escuela->id, [$this->id('primaria')]);
        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = $query->sql;
        });

        $this->registrar()->ejecutar($escuela->id, agregar: [], quitar: [$this->id('primaria')], confirmado: $confirmado);

        $primera = fn (callable $cumple): ?int => array_key_first(array_filter($sql, $cumple));
        $orden = [
            $primera(fn (string $s) => str_contains($s, 'from "escuela_niveles"') && str_ends_with($s, 'for update')),
            $primera(fn (string $s) => str_contains($s, 'from "escuelas"') && str_ends_with($s, 'for share')),
            $primera(fn (string $s) => str_contains($s, 'from "documentos_escuela_nivel"') && str_ends_with($s, 'for update')),
            $primera(fn (string $s) => str_starts_with($s, 'delete from "escuela_niveles"')),
        ];

        $this->assertNotContains(null, $orden, 'falta un paso: '.json_encode($orden));
        $this->assertSame($orden, array_values(array_unique($orden)));
        $ordenado = $orden;
        sort($ordenado);
        $this->assertSame($ordenado, $orden);
    }

    public function test_el_resumen_solo_cuenta_niveles_de_la_escuela(): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar()->ejecutar($escuela->id, agregar: [$this->id('preescolar')]);

        $resumen = app(ResumenEliminacionNivel::class)->ejecutar($escuela->id, [$this->id('preescolar'), $this->id('primaria')]);

        $this->assertSame([$this->id('preescolar')], array_keys($resumen));
        $this->assertSame(['datos', 'documentos', 'recibos', 'espacios', 'personal', 'matricula', 'mobiliario', 'pasos', 'responsables'], ResumenEliminacionNivel::CONCEPTOS);
        $this->assertSame(ResumenEliminacionNivel::CONCEPTOS, array_keys($resumen[$this->id('preescolar')]));
        $this->assertSame(ResumenEliminacionNivel::CONCEPTOS, array_keys(ResumenEliminacionNivel::ETIQUETAS));
        $this->assertFalse(ResumenEliminacionNivel::conDatos($resumen));
    }
}
