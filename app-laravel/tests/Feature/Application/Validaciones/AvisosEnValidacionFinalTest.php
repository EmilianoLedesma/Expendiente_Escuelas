<?php

namespace Tests\Feature\Application\Validaciones;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\ValidarVigenciaDocumentos;
use App\Application\Validaciones\DTO\FilaValidacion;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Application\Validaciones\UltimaValidacionFinal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\TestCase;

/**
 * Metadatos de los refactors documentales que llegan a los consumidores (spec 2026-10-20 §6.2): las observaciones
 * de ValidarVigenciaDocumentos (antes solo visibles en la pantalla de documentos) entran al reporte de la validación
 * final como «advertencia» —SEDEQ las ve en el reporte guardado— y nunca bloquean el envío. La vigencia mínima del
 * contrato está normativamente sin resolver: un contrato cuya vigencia declarada ya pasó es una observación, no un
 * incumplimiento.
 */
class AvisosEnValidacionFinalTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use RefreshDatabase;

    private function contrato(?string $fin, bool $indeterminada = false): DatosDocumento
    {
        return new DatosDocumento(
            tipoAcreditacion: 'arrendamiento',
            arrendadorComodante: 'Inmobiliaria Sur SA',
            arrendatarioComodatario: 'Colegio Norte SA de CV',
            fechaContrato: now()->subYears(2)->toDateString(),
            fechaInicioVigencia: now()->subYears(2)->toDateString(),
            vigenciaContrato: $fin,
            vigenciaIndeterminada: $indeterminada,
            usoAutorizado: 'Prestación de servicio educativo',
        );
    }

    /** @param  list<FilaValidacion>  $filas */
    private function advertencias(array $filas): array
    {
        return array_values(array_filter($filas, fn (FilaValidacion $f) => $f->estado === 'advertencia'));
    }

    public function test_un_contrato_con_vigencia_declarada_ya_pasada_es_un_aviso_y_no_una_violacion(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela, reemplazos: ['escritura_inmueble' => $this->contrato(now()->subMonth()->toDateString())]);

        $vigencia = app(ValidarVigenciaDocumentos::class);
        $this->assertSame([], $vigencia->ejecutar($escuela->id), 'no es una caducidad que obligue a volver a subir');
        $this->assertArrayHasKey('escritura_inmueble', $vigencia->avisos($escuela->id));
        $this->assertStringContainsString('SEDEQ', $vigencia->avisos($escuela->id)['escritura_inmueble']);
    }

    public function test_un_contrato_vigente_o_de_vigencia_indeterminada_no_avisa(): void
    {
        $vigente = $this->crearEscuelaConPlantel();
        $this->completarTramite($vigente, reemplazos: ['escritura_inmueble' => $this->contrato(now()->addYear()->toDateString())]);
        $this->assertArrayNotHasKey('escritura_inmueble', app(ValidarVigenciaDocumentos::class)->avisos($vigente->id));

        $indeterminado = $this->crearEscuelaConPlantel();
        $this->completarTramite($indeterminado, reemplazos: ['escritura_inmueble' => $this->contrato(null, indeterminada: true)]);
        $this->assertArrayNotHasKey('escritura_inmueble', app(ValidarVigenciaDocumentos::class)->avisos($indeterminado->id));
    }

    public function test_las_observaciones_de_vigencia_llegan_al_reporte_final_como_advertencia_sin_bloquear(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela, reemplazos: ['escritura_inmueble' => $this->contrato(now()->subMonth()->toDateString())]);

        $validacion = app(EjecutarValidacionFinal::class)->ejecutar($escuela->id);
        $advertencias = $this->advertencias($validacion->filas);

        $this->assertTrue($validacion->listaParaEnvio, 'una observación no bloquea el envío');
        $this->assertCount(1, $advertencias);
        $this->assertSame('observacion_vigencia.escritura_inmueble', $advertencias[0]->clave);
        $this->assertArrayHasKey('escritura_inmueble', $advertencias[0]->documentos);

        // Se guarda con la evaluación: el reporte almacenado (el que verá SEDEQ) la conserva igual.
        $guardada = app(UltimaValidacionFinal::class)->paraEscuela($escuela->id);
        $this->assertNotNull($guardada);
        $this->assertSame('observacion_vigencia.escritura_inmueble', $this->advertencias($guardada->filas)[0]->clave);
    }

    public function test_sin_observaciones_el_reporte_no_agrega_filas(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);

        $this->assertSame([], $this->advertencias(app(EjecutarValidacionFinal::class)->evaluar($escuela->id)->filas));
    }
}
