<?php

namespace Tests\Feature\Application\PlanEstudios;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\PlanEstudios\DTO\DatosPlanEstudios;
use App\Application\PlanEstudios\RegistrarPlanEstudios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class RegistrarPlanEstudiosTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    public function test_guarda_la_modalidad_y_el_plan_y_completa_el_paso(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plan_estudios');

        app(RegistrarPlanEstudios::class)->ejecutar($escuelaNivel->id, new DatosPlanEstudios(
            modalidad: 'escolarizada', planEstudiosReferencia: 'Plan de estudio 2022 (SEP)',
        ));

        $escuelaNivel->refresh();
        $this->assertSame('escolarizada', $escuelaNivel->modalidad);
        $this->assertSame('Plan de estudio 2022 (SEP)', $escuelaNivel->plan_estudios_referencia);
        $this->assertNull($escuelaNivel->plataforma_educativa_tipo);
        $this->assertTrue($this->pasoCompletado($escuelaNivel, 'plan_estudios'));
    }

    public function test_fuera_de_la_modalidad_escolarizada_exige_la_plataforma(): void
    {
        $escuelaNivel = $this->nivelListoPara('secundaria', 'plan_estudios');

        try {
            app(RegistrarPlanEstudios::class)->ejecutar($escuelaNivel->id, new DatosPlanEstudios(modalidad: 'mixta'));
            $this->fail('Se esperaba DatosInvalidos');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('plataformaEducativaTipo', $e->errores);
        }

        app(RegistrarPlanEstudios::class)->ejecutar($escuelaNivel->id, new DatosPlanEstudios(modalidad: 'mixta', plataformaEducativaTipo: 'propia'));
        $this->assertSame('propia', $escuelaNivel->refresh()->plataforma_educativa_tipo);
    }

    public function test_en_modalidad_escolarizada_descarta_la_plataforma(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plan_estudios');

        app(RegistrarPlanEstudios::class)->ejecutar($escuelaNivel->id, new DatosPlanEstudios(modalidad: 'escolarizada', plataformaEducativaTipo: 'rentada'));

        $this->assertNull($escuelaNivel->refresh()->plataforma_educativa_tipo);
    }

    public function test_rechaza_valores_fuera_del_catalogo(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plan_estudios');

        try {
            app(RegistrarPlanEstudios::class)->ejecutar($escuelaNivel->id, new DatosPlanEstudios(modalidad: 'a_distancia'));
            $this->fail('Se esperaba DatosInvalidos');
        } catch (DatosInvalidos $e) {
            $this->assertSame(['modalidad'], array_keys($e->errores));
        }

        $this->assertFalse($this->pasoCompletado($escuelaNivel, 'plan_estudios'));
    }

    public function test_no_se_puede_saltar_el_orden_del_wizard(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'mobiliario');

        $this->expectException(PrecondicionIncumplida::class);

        app(RegistrarPlanEstudios::class)->ejecutar($escuelaNivel->id, new DatosPlanEstudios(modalidad: 'escolarizada'));
    }

    public function test_no_toca_el_turno_ni_el_tipo_de_alumnado_de_paso_2_4(): void
    {
        // WS-5b: they belong to Paso 2.4, where changing them discards the
        // uploaded Formato de Solicitud (owner decision). Plan de estudios
        // must never write them, or it would bypass that rule.
        $escuelaNivel = $this->nivelListoPara('primaria', 'plan_estudios');
        $escuelaNivel->refresh();
        $turno = $escuelaNivel->turno;
        $tipoAlumnado = $escuelaNivel->tipo_alumnado;
        $this->assertNotNull($turno);

        app(RegistrarPlanEstudios::class)->ejecutar($escuelaNivel->id, new DatosPlanEstudios(modalidad: 'escolarizada'));

        $escuelaNivel->refresh();
        $this->assertSame($turno, $escuelaNivel->turno);
        $this->assertSame($tipoAlumnado, $escuelaNivel->tipo_alumnado);
    }
}
