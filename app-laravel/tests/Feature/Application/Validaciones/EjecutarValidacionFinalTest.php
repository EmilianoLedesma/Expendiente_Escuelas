<?php

namespace Tests\Feature\Application\Validaciones;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\ResumenTramite;
use App\Application\Validaciones\DTO\FilaValidacion;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Application\Validaciones\ReporteValidacionGuardado;
use App\Application\Validaciones\UltimaValidacionFinal;
use App\Models\EvaluacionValidacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
