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

    public function test_guarda_modalidad_turno_y_alumnado_y_completa_el_paso(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plan_estudios');

        app(RegistrarPlanEstudios::class)->ejecutar($escuelaNivel->id, new DatosPlanEstudios(
            modalidad: 'escolarizada', turno: 'matutino', tipoAlumnado: 'mixto', planEstudiosReferencia: 'Plan de estudio 2022 (SEP)',
        ));

        $escuelaNivel->refresh();
        $this->assertSame('escolarizada', $escuelaNivel->modalidad);
        $this->assertSame('matutino', $escuelaNivel->turno);
        $this->assertSame('mixto', $escuelaNivel->tipo_alumnado);
        $this->assertSame('Plan de estudio 2022 (SEP)', $escuelaNivel->plan_estudios_referencia);
        $this->assertNull($escuelaNivel->plataforma_educativa_tipo);
        $this->assertTrue($this->pasoCompletado($escuelaNivel, 'plan_estudios'));
    }

    public function test_fuera_de_la_modalidad_escolarizada_exige_la_plataforma(): void
    {
        $escuelaNivel = $this->nivelListoPara('secundaria', 'plan_estudios');

        try {
            app(RegistrarPlanEstudios::class)->ejecutar($escuelaNivel->id, new DatosPlanEstudios(modalidad: 'mixta', turno: 'vespertino', tipoAlumnado: 'mixto'));
            $this->fail('Se esperaba DatosInvalidos');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('plataformaEducativaTipo', $e->errores);
        }

        app(RegistrarPlanEstudios::class)->ejecutar($escuelaNivel->id, new DatosPlanEstudios(modalidad: 'mixta', turno: 'vespertino', tipoAlumnado: 'mixto', plataformaEducativaTipo: 'propia'));
        $this->assertSame('propia', $escuelaNivel->refresh()->plataforma_educativa_tipo);
    }

    public function test_en_modalidad_escolarizada_descarta_la_plataforma(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plan_estudios');

        app(RegistrarPlanEstudios::class)->ejecutar($escuelaNivel->id, new DatosPlanEstudios(modalidad: 'escolarizada', turno: 'mixto', tipoAlumnado: 'femenino', plataformaEducativaTipo: 'rentada'));

        $this->assertNull($escuelaNivel->refresh()->plataforma_educativa_tipo);
    }

    public function test_rechaza_valores_fuera_del_catalogo(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plan_estudios');

        try {
            app(RegistrarPlanEstudios::class)->ejecutar($escuelaNivel->id, new DatosPlanEstudios(modalidad: 'a_distancia', turno: 'nocturno', tipoAlumnado: 'otro'));
            $this->fail('Se esperaba DatosInvalidos');
        } catch (DatosInvalidos $e) {
            $this->assertSame(['modalidad', 'turno', 'tipoAlumnado'], array_keys($e->errores));
        }

        $this->assertFalse($this->pasoCompletado($escuelaNivel, 'plan_estudios'));
    }

    public function test_no_se_puede_saltar_el_orden_del_wizard(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'mobiliario');

        $this->expectException(PrecondicionIncumplida::class);

        app(RegistrarPlanEstudios::class)->ejecutar($escuelaNivel->id, new DatosPlanEstudios(modalidad: 'escolarizada', turno: 'matutino', tipoAlumnado: 'mixto'));
    }
}
