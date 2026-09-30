<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Livewire\Tramite\ValidacionFinal;
use App\Models\AulaNivel;
use App\Models\EscuelaNivel;
use App\Models\EvaluacionValidacion;
use App\Models\ReciboPagoDerechos;
use App\Models\Solicitante;
use Database\Seeders\ReglasValidacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\TestCase;

class ValidacionFinalTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use RefreshDatabase;

    public function test_con_el_tramite_incompleto_regresa_al_resumen(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela);
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(ValidacionFinal::class, ['escuela' => $escuela])
            ->assertRedirect(route('tramite.resumen', ['escuela' => $escuela->id]));

        $this->assertSame(0, EvaluacionValidacion::count());
    }

    public function test_expediente_consistente_queda_listo_y_ofrece_el_pdf(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $this->actingAs($escuela->solicitante->user);

        $componente = Livewire::test(ValidacionFinal::class, ['escuela' => $escuela]);
        $evaluacion = EvaluacionValidacion::sole();

        $componente
            ->assertSee('Validación final')
            ->assertSee('Tu expediente no tiene errores que impidan enviarlo')
            ->assertSee(route('tramite.validacion.reporte', ['escuela' => $escuela->id, 'evaluacion' => $evaluacion->id]))
            ->assertDontSee('Debe corregirse');
    }

    public function test_senala_el_documento_incorrecto_con_enlace_para_corregirlo(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela, reemplazos: [
            'ine' => new DatosDocumento(identidadNombre: 'PEREZ GOMEZ JUAN', identidadCurp: 'PEGJ800101HQTRML08'),
        ]);
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(ValidacionFinal::class, ['escuela' => $escuela])
            ->assertSee('Hay errores que debes corregir antes de enviar')
            ->assertSee('Debe corregirse')
            ->assertSee('Credencial de elector (INE): PEGJ800101HQTRML08 — no coincide')
            ->assertSee(route('tramite.paso2-documentos', ['escuela' => $escuela->id, 'corregir' => 'ine']), false);
    }

    /** WS-5b: each level's Paso 2.4 documents get their own section, linked to that level's page. */
    public function test_un_folio_reutilizado_se_senala_en_la_seccion_del_nivel_con_enlace_a_sus_documentos(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $otra = $this->crearEscuelaConPlantel();
        $this->completarTramite($otra);
        $escuelaNivel = EscuelaNivel::where('escuela_id', $escuela->id)->sole();
        $folioAjeno = ReciboPagoDerechos::where('folio', '!=', "F-{$escuelaNivel->id}")->value('folio');
        ReciboPagoDerechos::where('folio', "F-{$escuelaNivel->id}")->update(['folio' => $folioAjeno]);
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(ValidacionFinal::class, ['escuela' => $escuela])
            ->assertSee('Hay errores que debes corregir antes de enviar')
            ->assertSee('1 revisión no se cumple')
            ->assertSee('Documentos del nivel: Primaria')
            ->assertSee('Recibo de pago no usado en otro nivel')
            ->assertSee("Folio del recibo: {$folioAjeno}")
            ->assertSee(route('tramite.paso2-nivel-documentos', ['escuelaNivel' => $escuelaNivel->id]), false)
            ->assertSee('Corregir: Recibo de pago de derechos');
    }

    public function test_las_alertas_se_muestran_sin_bloquear(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela, reemplazos: [
            'ine' => new DatosDocumento(identidadNombre: 'PEREZ GOMEZ JUAN CARLOS', identidadCurp: self::CURP_TITULAR),
        ]);
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(ValidacionFinal::class, ['escuela' => $escuela])
            ->assertSee('Tu expediente no tiene errores que impidan enviarlo')
            ->assertSee('Alerta')
            ->assertSee('Credencial de elector (INE): PEREZ GOMEZ JUAN CARLOS — no coincide');
    }

    public function test_validar_de_nuevo_guarda_otra_evaluacion(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(ValidacionFinal::class, ['escuela' => $escuela])->call('validarDeNuevo');

        $this->assertSame(2, EvaluacionValidacion::count());
    }

    public function test_un_no_dueno_recibe_403(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $this->actingAs(Solicitante::factory()->create()->user);

        $this->get(route('tramite.validacion', ['escuela' => $escuela->id]))->assertForbidden();
    }

    public function test_el_pdf_se_descarga_solo_desde_su_escuela_y_por_su_dueno(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $otra = $this->crearEscuelaConPlantel();
        $this->actingAs($escuela->solicitante->user);
        $this->get(route('tramite.validacion', ['escuela' => $escuela->id]))->assertOk();
        $evaluacion = EvaluacionValidacion::sole();

        $this->get(route('tramite.validacion.reporte', ['escuela' => $escuela->id, 'evaluacion' => $evaluacion->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($otra->solicitante->user);
        $this->get(route('tramite.validacion.reporte', ['escuela' => $otra->id, 'evaluacion' => $evaluacion->id]))->assertNotFound();
        $this->get(route('tramite.validacion.reporte', ['escuela' => $escuela->id, 'evaluacion' => $evaluacion->id]))->assertForbidden();
    }

    public function test_el_resumen_completo_lleva_a_la_validacion_final(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $this->actingAs($escuela->solicitante->user);

        $this->get(route('tramite.resumen', ['escuela' => $escuela->id]))
            ->assertOk()
            ->assertSee('Validación final')
            ->assertSee(route('tramite.validacion', ['escuela' => $escuela->id]), false);
    }

    public function test_muestra_la_capacidad_instalada_por_nivel_con_enlace_para_corregir(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $escuelaNivel = $this->completarTramite($escuela);
        (new ReglasValidacionSeeder)->run();
        AulaNivel::create(['escuela_nivel_id' => $escuelaNivel->id, 'numero_aulas' => 1, 'superficie_m2' => 10]);
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(ValidacionFinal::class, ['escuela' => $escuela])
            ->assertSee('Capacidad instalada · Primaria')
            ->assertSee('Superficie de aulas')
            ->assertSee('Requerido: 22.50 m² · Declarado: 10 m²')
            ->assertSee(route('tramite.paso3-infraestructura', ['escuelaNivel' => $escuelaNivel->id]), false)
            ->assertSee('Tu expediente no tiene errores que impidan enviarlo');
    }
}
