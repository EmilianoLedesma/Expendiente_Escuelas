<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Revision\DecidirPaso;
use App\Application\Revision\ResultadosCambiados;
use App\Application\Validaciones\DTO\SeccionCapacidad;
use App\Application\Validaciones\DTO\ValidacionFinal;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Models\AulaNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\EvaluacionValidacion;
use Database\Seeders\ReglasValidacionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

/** Motor real sobre un trámite completo: el aviso compara la última evaluación guardada contra la evaluación de ahora. */
class ResultadosCambiadosTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private Escuela $escuela;

    private EscuelaNivel $nivel;

    /** Trámite completo en revisión con una evaluación guardada donde aulas (infraestructura) y predio (inmueble) cumplen. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->escuela = $this->crearEscuelaConPlantel();
        $this->nivel = $this->completarTramite($this->escuela);
        (new ReglasValidacionSeeder)->run();
        // 25 alumnos: aulas piden 22.50 m² (declarados 30) y el predio 62.50 m² (declarados 100).
        AulaNivel::create(['escuela_nivel_id' => $this->nivel->id, 'numero_aulas' => 1, 'superficie_m2' => 30]);
        $this->escuela->plantel->update(['metros_totales' => 100]);
        DB::table('escuela_niveles')->where('id', $this->nivel->id)->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $validacion = app(EjecutarValidacionFinal::class);
        $validacion->guardar($this->escuela->id, $validacion->evaluar($this->escuela->id));
    }

    private function aceptar(string $paso): void
    {
        app(DecidirPaso::class)->ejecutar($this->supervisor()->id, $this->nivel->id, $paso, 'aceptado');
    }

    private function resultado(): array
    {
        return app(ResultadosCambiados::class)->ejecutar($this->supervisor()->id, $this->nivel->id);
    }

    public function test_una_correccion_que_rompe_un_paso_aceptado_se_marca(): void
    {
        $this->aceptar('infraestructura');
        // Corrección del solicitante: baja la superficie de aulas por debajo de lo requerido (10 < 22.50).
        AulaNivel::where('escuela_nivel_id', $this->nivel->id)->update(['superficie_m2' => 10]);

        $resultado = $this->resultado();

        $this->assertSame(['infraestructura'], array_keys($resultado));
        $this->assertCount(1, $resultado['infraestructura']);
        $this->assertStringContainsString('Superficie de aulas', $resultado['infraestructura'][0]);
        $this->assertStringContainsString('No cumple', $resultado['infraestructura'][0]);
    }

    public function test_una_regla_sin_mensaje_no_deja_punto_colgando(): void
    {
        $this->aceptar('infraestructura');
        AulaNivel::where('escuela_nivel_id', $this->nivel->id)->update(['superficie_m2' => 10]);
        $real = app(EjecutarValidacionFinal::class)->evaluar($this->escuela->id, exigirCompleto: false);
        $sinMensaje = fn (SeccionCapacidad $s) => SeccionCapacidad::desdeArreglo(['filas' => array_map(fn (array $f) => ['mensaje' => ''] + $f, $s->aArreglo()['filas'])] + $s->aArreglo());
        $this->mock(EjecutarValidacionFinal::class)->shouldReceive('evaluar')
            ->andReturn(new ValidacionFinal(null, now(), false, $real->filas, array_map($sinMensaje, $real->capacidad), $real->niveles));

        $this->assertSame('Superficie de aulas: antes cumplía y ahora «No cumple».', $this->resultado()['infraestructura'][0]);
    }

    public function test_un_envio_hermano_que_cambia_la_capacidad_se_marca(): void
    {
        $this->aceptar('inmueble');
        // Otra escuela del mismo plantel envía 40 alumnos más: el predio debe cubrir 65 × 2.50 = 162.50 m² y hay 100.
        $hermana = Escuela::create(['plantel_id' => $this->escuela->plantel_id, 'solicitante_id' => $this->escuela->solicitante_id]);
        $nivelHermano = $this->elegirNivel($hermana->id, 'primaria');
        $grado = (int) DB::table('grados')->where('nivel_educativo_id', $nivelHermano->nivel_educativo_id)->where('orden', 1)->value('id');
        DB::table('matricula_grados')->insert(['escuela_nivel_id' => $nivelHermano->id, 'grado_id' => $grado, 'grupo' => 'A', 'cantidad_alumnos' => 40]);

        $resultado = $this->resultado();

        $this->assertSame(['inmueble'], array_keys($resultado));
        $this->assertStringContainsString('Superficie total del predio', $resultado['inmueble'][0]);
    }

    public function test_sin_cambios_devuelve_vacio(): void
    {
        $this->aceptar('infraestructura');
        $this->aceptar('inmueble');

        $this->assertSame([], $this->resultado());
    }

    public function test_solo_pasos_aceptados_se_marcan(): void
    {
        // Se rompe infraestructura, pero ese paso está pendiente; plantel aceptado no tiene filas afectadas.
        $this->aceptar('plantel');
        AulaNivel::where('escuela_nivel_id', $this->nivel->id)->update(['superficie_m2' => 10]);
        $this->assertSame([], $this->resultado());

        // Rechazado tampoco: la decisión vigente ya no es aceptado.
        app(DecidirPaso::class)->ejecutar($this->supervisor()->id, $this->nivel->id, 'infraestructura', 'rechazado', 'Corrige las aulas');
        $this->assertSame([], $this->resultado());

        // Al aceptarlo de nuevo, el cambio sí se marca.
        $this->aceptar('infraestructura');
        $this->assertSame(['infraestructura'], array_keys($this->resultado()));
    }

    public function test_acceso_de_otro_revisor_lanza_authorization(): void
    {
        $this->expectException(AuthorizationException::class);

        app(ResultadosCambiados::class)->ejecutar($this->revisor()->id, $this->nivel->id);
    }

    public function test_motor_roto_devuelve_vacio_sin_romper_la_pagina(): void
    {
        $this->aceptar('infraestructura');
        AulaNivel::where('escuela_nivel_id', $this->nivel->id)->update(['superficie_m2' => 10]);
        $this->mock(EjecutarValidacionFinal::class)->shouldReceive('evaluar')->andThrow(new \RuntimeException('motor roto'));

        $this->assertSame([], $this->resultado());
    }

    public function test_sin_evaluacion_guardada_devuelve_vacio(): void
    {
        $this->aceptar('infraestructura');
        AulaNivel::where('escuela_nivel_id', $this->nivel->id)->update(['superficie_m2' => 10]);
        EvaluacionValidacion::query()->delete();

        $this->assertSame([], $this->resultado());
    }

    public function test_solo_lectura_no_escribe_evaluaciones_ni_decisiones(): void
    {
        $this->aceptar('infraestructura');
        AulaNivel::where('escuela_nivel_id', $this->nivel->id)->update(['superficie_m2' => 10]);
        $evaluaciones = EvaluacionValidacion::count();
        $decisiones = DB::table('decisiones_revision')->get()->all();

        $this->resultado();

        $this->assertSame($evaluaciones, EvaluacionValidacion::count());
        $this->assertEquals($decisiones, DB::table('decisiones_revision')->get()->all());
    }
}
