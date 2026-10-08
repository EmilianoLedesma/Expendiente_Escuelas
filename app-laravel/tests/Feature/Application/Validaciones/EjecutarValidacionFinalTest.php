<?php

namespace Tests\Feature\Application\Validaciones;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\ResumenTramite;
use App\Application\Validaciones\DTO\FilaValidacion;
use App\Application\Validaciones\DTO\SeccionCapacidad;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Application\Validaciones\ReporteValidacionGuardado;
use App\Application\Validaciones\UltimaValidacionFinal;
use App\Models\AulaNivel;
use App\Models\EvaluacionValidacion;
use Database\Seeders\ReglasValidacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\TestCase;

class EjecutarValidacionFinalTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use RefreshDatabase;

    /** @param list<FilaValidacion> $filas @return array<string, FilaValidacion> */
    private function porClave(array $filas): array
    {
        return array_combine(array_map(fn (FilaValidacion $f) => $f->clave, $filas), $filas);
    }

    public function test_el_fixture_deja_el_tramite_completo(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);

        $this->assertTrue(app(ResumenTramite::class)->paraEscuela($escuela->id)->completo);
    }

    public function test_expediente_consistente_queda_listo_para_envio_y_se_guarda_en_pdf(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);

        $validacion = app(EjecutarValidacionFinal::class)->ejecutar($escuela->id);

        $this->assertTrue($validacion->listaParaEnvio);
        $this->assertCount(6, $validacion->filas);
        $this->assertSame(['cumple'], array_values(array_unique(array_map(fn (FilaValidacion $f) => $f->estado, $validacion->filas))));

        $evaluacion = EvaluacionValidacion::sole();
        $this->assertSame($validacion->evaluacionId, $evaluacion->id);
        $this->assertSame($escuela->id, $evaluacion->escuela_id);
        $this->assertTrue($evaluacion->lista_para_envio);
        Storage::disk('documentos')->assertExists($evaluacion->archivo_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('documentos')->get($evaluacion->archivo_path));
    }

    public function test_un_no_cumple_bloquea_y_senala_el_documento_con_su_nombre(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela, reemplazos: [
            'ine' => new DatosDocumento(identidadNombre: 'PEREZ GOMEZ JUAN', identidadCurp: 'PEGJ800101HQTRML08'),
        ]);

        $validacion = app(EjecutarValidacionFinal::class)->ejecutar($escuela->id);
        $curp = $this->porClave($validacion->filas)['curp_coincide'];

        $this->assertFalse($validacion->listaParaEnvio);
        $this->assertSame('no_cumple', $curp->estado);
        $this->assertSame(['ine' => 'Credencial de elector (INE)'], $curp->documentos);
        $this->assertContains('Declarado: '.self::CURP_TITULAR, $curp->lineas);
        $this->assertContains('Credencial de elector (INE): PEGJ800101HQTRML08 — no coincide', $curp->lineas);
        $this->assertFalse(EvaluacionValidacion::sole()->lista_para_envio);
    }

    public function test_una_alerta_no_bloquea(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela, reemplazos: [
            'ine' => new DatosDocumento(identidadNombre: 'PEREZ GOMEZ JUAN CARLOS', identidadCurp: self::CURP_TITULAR),
        ]);

        $validacion = app(EjecutarValidacionFinal::class)->ejecutar($escuela->id);

        $this->assertTrue($validacion->listaParaEnvio);
        $this->assertSame('advertencia', $this->porClave($validacion->filas)['nombre_identidad_coincide']->estado);
    }

    public function test_el_domicilio_se_explica_parte_por_parte(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela, reemplazos: [
            'certificado_numero_oficial' => new DatosDocumento(domicilioCalle: 'Hidalgo', domicilioNumeroExt: '12', domicilioColonia: 'Centro', domicilioMunicipio: 'Querétaro', domicilioCodigoPostal: '76010'),
        ]);

        $domicilio = $this->porClave(app(EjecutarValidacionFinal::class)->ejecutar($escuela->id)->filas)['domicilio_coincide'];

        $this->assertSame('no_cumple', $domicilio->estado);
        $this->assertSame(['certificado_numero_oficial' => 'Certificado de número oficial'], $domicilio->documentos);
        $this->assertContains('Calle: plantel «Av. Juárez», certificado «Hidalgo» — no coincide', $domicilio->lineas);
        $this->assertContains('Código postal: plantel «76000», certificado «76010» — no coincide', $domicilio->lineas);
    }

    public function test_evaluar_no_escribe_nada_y_guardar_lo_guarda(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $servicio = app(EjecutarValidacionFinal::class);

        $evaluada = $servicio->evaluar($escuela->id);

        $this->assertNull($evaluada->evaluacionId);
        $this->assertTrue($evaluada->listaParaEnvio);
        $this->assertSame(0, EvaluacionValidacion::count());
        $this->assertSame([], Storage::disk('documentos')->allFiles('validaciones'));

        $guardada = $servicio->guardar($escuela->id, $evaluada);

        $evaluacion = EvaluacionValidacion::sole();
        $this->assertSame($evaluacion->id, $guardada->evaluacionId);
        $this->assertEquals($evaluada->filas, $guardada->filas);
        $this->assertSame($evaluada->listaParaEnvio, $evaluacion->lista_para_envio);
        Storage::disk('documentos')->assertExists($evaluacion->archivo_path);
    }

    public function test_no_corre_si_el_tramite_no_esta_completo(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela);

        try {
            app(EjecutarValidacionFinal::class)->ejecutar($escuela->id);
            $this->fail('Se esperaba PrecondicionIncumplida');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame('tramite', $e->etapaFaltante);
        }

        $this->assertSame(0, EvaluacionValidacion::count());
    }

    public function test_la_ultima_validacion_se_relee_igual_que_se_guardo(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela, reemplazos: [
            'ine' => new DatosDocumento(identidadNombre: 'PEREZ GOMEZ JUAN', identidadCurp: 'PEGJ800101HQTRML08'),
        ]);
        app(EjecutarValidacionFinal::class)->ejecutar($escuela->id);
        $segunda = app(EjecutarValidacionFinal::class)->ejecutar($escuela->id);

        $releida = app(UltimaValidacionFinal::class)->paraEscuela($escuela->id);

        $this->assertSame($segunda->evaluacionId, $releida?->evaluacionId);
        $this->assertEquals($segunda->filas, $releida?->filas);
        $this->assertSame($segunda->listaParaEnvio, $releida?->listaParaEnvio);
        $this->assertNull(app(UltimaValidacionFinal::class)->paraEscuela($this->crearEscuelaConPlantel()->id));
    }

    public function test_el_pdf_solo_se_entrega_a_su_escuela(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $validacion = app(EjecutarValidacionFinal::class)->ejecutar($escuela->id);
        $otra = $this->crearEscuelaConPlantel();

        $this->assertSame(EvaluacionValidacion::sole()->archivo_path, app(ReporteValidacionGuardado::class)->ruta($escuela->id, $validacion->evaluacionId));
        $this->assertNull(app(ReporteValidacionGuardado::class)->ruta($otra->id, $validacion->evaluacionId));
    }

    public function test_incluye_la_capacidad_instalada_por_nivel_y_bloquea_el_envio(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $escuelaNivel = $this->completarTramite($escuela);
        (new ReglasValidacionSeeder)->run();
        // 25 alumnos × 0.90 m² = 22.50 m² required, 10 declared
        AulaNivel::create(['escuela_nivel_id' => $escuelaNivel->id, 'numero_aulas' => 1, 'superficie_m2' => 10]);

        $validacion = app(EjecutarValidacionFinal::class)->ejecutar($escuela->id);

        $this->assertFalse($validacion->listaParaEnvio, 'WS-7a (ADR-010 P3): la capacidad que no se cumple bloquea el envío.');
        $this->assertContains('Capacidad instalada · Primaria: Superficie de aulas', $validacion->motivosBloqueo());
        $this->assertCount(1, $validacion->capacidad);
        $seccion = $validacion->capacidad[0];
        $this->assertSame('Primaria', $seccion->nivel);
        $this->assertSame($escuelaNivel->id, $seccion->escuelaNivelId);
        $aulas = $this->porClave($seccion->filas)['primaria.superficie.aulas'];
        $this->assertSame('no_cumple', $aulas->estado);
        $this->assertSame('Superficie de aulas', $aulas->titulo);
        $this->assertContains('Requerido: 22.50 m² · Declarado: 10 m²', $aulas->lineas);
        $this->assertSame('infraestructura', $aulas->pasoCorreccion);
        $this->assertSame('cumple', $this->porClave($seccion->filas)['primaria.personal.director_tecnico']->estado);
        $this->assertSame('plantilla_docente', $this->porClave($seccion->filas)['primaria.personal.director_tecnico']->pasoCorreccion);
    }

    public function test_la_capacidad_se_relee_igual_que_se_guardo(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        (new ReglasValidacionSeeder)->run();
        $guardada = app(EjecutarValidacionFinal::class)->ejecutar($escuela->id);

        $releida = app(UltimaValidacionFinal::class)->paraEscuela($escuela->id);

        $this->assertEquals($guardada->capacidad, $releida?->capacidad);
        $this->assertEquals($guardada->filas, $releida?->filas);
    }

    private function regla(string $clave, string $tipoRegla, string $tipoCalculo, string $ambito, float $valor, string $concepto): void
    {
        DB::table('reglas_validacion')->insert([
            'clave' => $clave,
            'nivel_educativo_id' => DB::table('niveles_educativos')->where('clave', 'primaria')->value('id'),
            'tipo_regla' => $tipoRegla, 'tipo_calculo' => $tipoCalculo, 'ambito' => $ambito, 'redondeo' => 'na',
            'concepto' => $concepto, 'valor_numerico' => $valor, 'unidad' => 'prueba', 'fuente' => 'prueba',
        ]);
    }

    public function test_una_captura_faltante_bloquea_y_las_reglas_sin_dato_no(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $escuelaNivel = $this->completarTramite($escuela);
        $this->regla('primaria.infraestructura.altura_aulas', 'infraestructura', 'minimo_fijo', 'aula', 2.70, 'Altura de aulas');
        $this->regla('primaria.superficie.aulas', 'superficie', 'ratio_por_alumno', 'aula', 0.90, 'Superficie de aulas');

        $sinAulas = app(EjecutarValidacionFinal::class)->ejecutar($escuela->id);

        $this->assertFalse($sinAulas->listaParaEnvio);
        $this->assertSame(['Capacidad instalada · Primaria: Superficie de aulas'], $sinAulas->motivosBloqueo());
        $this->assertSame('no_evaluable', $this->porClave($sinAulas->capacidad[0]->filas)['primaria.superficie.aulas']->estado);

        // 25 alumnos × 0.90 = 22.5 m² required; 30 declared.
        AulaNivel::create(['escuela_nivel_id' => $escuelaNivel->id, 'numero_aulas' => 1, 'superficie_m2' => 30]);
        $conAulas = app(EjecutarValidacionFinal::class)->ejecutar($escuela->id);

        $this->assertTrue($conAulas->listaParaEnvio, 'Solo queda la regla estructural: no bloquea.');
        $this->assertSame('no_verificable', $this->porClave($conAulas->capacidad[0]->filas)['primaria.infraestructura.altura_aulas']->estado);
        $this->assertSame(EjecutarValidacionFinal::REGLA_ENVIO, EvaluacionValidacion::latest('id')->first()?->resultados['regla_envio']);
    }

    public function test_el_pdf_dice_que_la_capacidad_bloquea_y_que_es_no_verificable(): void
    {
        $fila = fn (string $estado) => new FilaValidacion("primaria.{$estado}", "Regla {$estado}", $estado, 'mensaje', [], []);

        $html = view('pdf.reporte-validacion', [
            'escuela' => ['numero' => '0001', 'nombre' => null, 'domicilio' => 'Centro'],
            'listaParaEnvio' => false,
            'filas' => [],
            'capacidad' => [new SeccionCapacidad(1, 'Primaria', [$fila('no_cumple'), $fila('no_evaluable'), $fila('no_verificable')])],
            'niveles' => [],
            'generadaEn' => now(),
        ])->render();

        $this->assertStringContainsString('Bloquea el envío', $html);
        $this->assertStringContainsString('Falta capturar: bloquea el envío', $html);
        $this->assertStringContainsString('No verificable por el sistema', $html);
        $this->assertStringNotContainsString('no impiden enviar', $html);
        $this->assertStringNotContainsString('Observación', $html);
    }
}
