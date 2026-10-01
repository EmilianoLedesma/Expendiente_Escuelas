<?php

namespace Tests\Feature\Application\Validaciones;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Validaciones\ConstruirContextoValidacion;
use App\Application\Validaciones\EjecutarValidacionDocumental;
use App\Domain\Validaciones\Documental\TipoHecho;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use App\Domain\Validaciones\Resultado\ReporteValidacion;
use App\Domain\Validaciones\Resultado\ResultadoRegla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\TestCase;

class EjecutarValidacionDocumentalTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use RefreshDatabase;

    private function validar(int $escuelaId): ReporteValidacion
    {
        return app(EjecutarValidacionDocumental::class)->ejecutar($escuelaId);
    }

    /** @return array<string, string> clave de regla => estado */
    private function estados(ReporteValidacion $reporte): array
    {
        return array_combine(
            array_map(fn (ResultadoRegla $r) => $r->clave, $reporte->resultados),
            array_map(fn (ResultadoRegla $r) => $r->estado->value, $reporte->resultados),
        );
    }

    public function test_expediente_consistente_de_persona_fisica_cumple_todas_las_reglas(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela);
        $this->subirTodosConDatos($escuela);

        $reporte = $this->validar($escuela->id);

        $this->assertSame([
            'documentos_requeridos_presentes' => 'cumple',
            'nombre_identidad_coincide' => 'cumple',
            'curp_coincide' => 'cumple',
            'nombre_fiscal_coincide' => 'cumple',
            'rfc_coincide' => 'cumple',
            'domicilio_coincide' => 'cumple',
        ], $this->estados($reporte));
    }

    public function test_cada_inconsistencia_senala_su_documento(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela);
        $this->subirTodosConDatos($escuela, reemplazos: [
            'ine' => new DatosDocumento(identidadNombre: 'LOPEZ MARIA', identidadCurp: 'LOMA800101MQTPRR01'),
            'constancia_situacion_fiscal' => new DatosDocumento(fiscalNombre: 'JUAN PEREZ GOMEZ', fiscalRfc: 'PEGJ800101AB2'),
            'certificado_numero_oficial' => new DatosDocumento(domicilioCalle: 'Hidalgo', domicilioNumeroExt: '12', domicilioColonia: 'Centro', domicilioMunicipio: 'Querétaro', domicilioCodigoPostal: '76000'),
        ]);

        $reporte = $this->validar($escuela->id);

        $this->assertSame(EstadoResultado::Advertencia, $reporte->resultado('nombre_identidad_coincide')?->estado);
        $this->assertSame(['ine'], $reporte->resultado('nombre_identidad_coincide')?->documentos);
        $this->assertSame(EstadoResultado::NoCumple, $reporte->resultado('curp_coincide')?->estado);
        $this->assertSame(['ine'], $reporte->resultado('curp_coincide')?->documentos);
        $this->assertSame(EstadoResultado::NoCumple, $reporte->resultado('rfc_coincide')?->estado);
        $this->assertSame(['constancia_situacion_fiscal'], $reporte->resultado('rfc_coincide')?->documentos);
        $this->assertSame(EstadoResultado::Advertencia, $reporte->resultado('domicilio_coincide')?->estado);
        $this->assertSame(EstadoResultado::Cumple, $reporte->resultado('documentos_requeridos_presentes')?->estado);
    }

    public function test_documentos_subidos_sin_datos_no_cumplen(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela);
        $this->subirTodosConDatos($escuela, reemplazos: ['ine' => new DatosDocumento, 'certificado_numero_oficial' => new DatosDocumento]);

        $reporte = $this->validar($escuela->id);

        $this->assertSame(EstadoResultado::NoCumple, $reporte->resultado('nombre_identidad_coincide')?->estado);
        $this->assertSame(['ine'], $reporte->resultado('curp_coincide')?->documentos);
        $this->assertSame(EstadoResultado::NoCumple, $reporte->resultado('domicilio_coincide')?->estado);
    }

    public function test_documentos_faltantes_no_cumplen_y_se_listan(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela);
        $this->subirDocumento($escuela, 'ine', $this->datosConsistentes('ine'));

        $resultado = $this->validar($escuela->id)->resultado('documentos_requeridos_presentes');

        $this->assertSame(EstadoResultado::NoCumple, $resultado?->estado);
        $this->assertContains('constancia_curp', $resultado?->documentos);
        $this->assertNotContains('ine', $resultado?->documentos);
    }

    public function test_con_gestor_la_identidad_es_la_del_gestor_y_lo_fiscal_del_titular(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela, 'fisica_con_gestor');
        $this->subirTodosConDatos($escuela, 'fisica_con_gestor');

        $contexto = app(ConstruirContextoValidacion::class)->ejecutar($escuela->id);

        $this->assertSame('Carlos Gómez Mora', $contexto->declarado(TipoHecho::NombreIdentidad)?->valor);
        $this->assertSame(self::CURP_GESTOR, $contexto->declarado(TipoHecho::Curp)?->valor);
        $this->assertSame('Juan Pérez Gómez', $contexto->declarado(TipoHecho::NombreFiscal)?->valor);
        $this->assertSame(self::RFC_TITULAR, $contexto->declarado(TipoHecho::Rfc)?->valor);
        $this->assertFalse($this->validar($escuela->id)->tieneNoCumplimientos());
    }

    public function test_con_gestor_la_ine_del_titular_no_coincide(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela, 'fisica_con_gestor');
        $this->subirTodosConDatos($escuela, 'fisica_con_gestor', [
            'ine' => new DatosDocumento(identidadNombre: 'PEREZ GOMEZ JUAN', identidadCurp: self::CURP_TITULAR),
        ]);

        $reporte = $this->validar($escuela->id);

        $this->assertSame(EstadoResultado::NoCumple, $reporte->resultado('curp_coincide')?->estado);
        $this->assertSame(['ine'], $reporte->resultado('curp_coincide')?->documentos);
    }

    public function test_persona_moral_usa_representante_y_razon_social(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela, 'moral');
        $this->subirTodosConDatos($escuela, 'moral');

        $contexto = app(ConstruirContextoValidacion::class)->ejecutar($escuela->id);
        $reporte = $this->validar($escuela->id);

        $this->assertSame('Juan Pérez Gómez', $contexto->declarado(TipoHecho::NombreIdentidad)?->valor);
        $this->assertNull($contexto->declarado(TipoHecho::Curp));
        $this->assertSame('Colegio Ejemplo A.C.', $contexto->declarado(TipoHecho::NombreFiscal)?->valor);
        $this->assertSame(EstadoResultado::Cumple, $reporte->resultado('nombre_fiscal_coincide')?->estado);
        // No declared CURP: INE and Constancia de CURP are compared with each other.
        $this->assertSame(EstadoResultado::Cumple, $reporte->resultado('curp_coincide')?->estado);
        // personas_morales has no RFC column and there is one fiscal source: nothing to compare.
        $this->assertSame(EstadoResultado::NoEvaluable, $reporte->resultado('rfc_coincide')?->estado);
    }

    public function test_el_domicilio_declarado_es_el_del_plantel(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela);

        $contexto = app(ConstruirContextoValidacion::class)->ejecutar($escuela->id);

        $this->assertSame('Av. Juárez', $contexto->declarado(TipoHecho::DomicilioCalle)?->valor);
        $this->assertSame('12', $contexto->declarado(TipoHecho::DomicilioNumeroExt)?->valor);
        $this->assertSame('76000', $contexto->declarado(TipoHecho::DomicilioCodigoPostal)?->valor);
    }

    public function test_resubir_un_documento_con_otros_datos_reemplaza_sus_hechos(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela);
        $this->subirDocumento($escuela, 'ine', new DatosDocumento(identidadNombre: 'LOPEZ MARIA', identidadCurp: 'LOMA800101MQTPRR01'));
        $this->subirDocumento($escuela, 'ine', $this->datosConsistentes('ine'));

        $contexto = app(ConstruirContextoValidacion::class)->ejecutar($escuela->id);

        $this->assertSame(self::CURP_TITULAR, $contexto->deDocumento(TipoHecho::Curp, 'ine')?->valor);
    }

    public function test_los_datos_de_otra_escuela_no_se_mezclan(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela);
        $this->subirDocumento($escuela, 'ine', $this->datosConsistentes('ine'));
        $otra = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($otra);

        $contexto = app(ConstruirContextoValidacion::class)->ejecutar($otra->id);

        $this->assertNull($contexto->deDocumento(TipoHecho::Curp, 'ine'));
    }

    public function test_el_certificado_del_plantel_aplica_a_cada_escuela_del_plantel(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela);
        $this->subirDocumento($escuela, 'certificado_numero_oficial', $this->datosConsistentes('certificado_numero_oficial'));
        $hermana = $this->crearEscuelaConPlantel($escuela->plantel_id);
        $this->registrarResponsable($hermana);

        $contexto = app(ConstruirContextoValidacion::class)->ejecutar($hermana->id);

        $this->assertSame('76000', $contexto->deDocumento(TipoHecho::DomicilioCodigoPostal, 'certificado_numero_oficial')?->valor);
    }

    public function test_sin_responsable_legal_no_hay_contexto(): void
    {
        $escuela = $this->crearEscuelaConPlantel();

        $this->expectException(PrecondicionIncumplida::class);

        $this->validar($escuela->id);
    }
}
