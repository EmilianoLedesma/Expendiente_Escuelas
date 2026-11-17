<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\DTO\DatosCriteriosProyecto;
use App\Application\ProyectoEducativo\GuardarCriteriosProyecto;
use App\Livewire\Tramite\Paso3\ProyectoEducativoSeccion;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3CriteriosProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private const SLUG = 'criterios-de-acreditacion-promocion-y-certificacion';

    private const BLOQUE_1 = ['criterios_acreditacion', 'opciones_acreditacion', 'criterios_promocion'];

    private const BLOQUE_2 = ['criterios_titulacion', 'servicios_control_escolar'];

    private function palabras(int $n): string
    {
        return trim(str_repeat('palabra ', $n));
    }

    private function nivel(): EscuelaNivel
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->actingAs($nivel->escuela->solicitante->user);

        return $nivel;
    }

    private function componente(EscuelaNivel $nivel)
    {
        return Livewire::test(ProyectoEducativoSeccion::class, ['escuelaNivel' => $nivel, 'seccion' => self::SLUG]);
    }

    private function ruta(EscuelaNivel $nivel): string
    {
        return route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $nivel->id, 'seccion' => self::SLUG]);
    }

    /** @param  list<string>  $campos */
    private function llenar($componente, array $campos, int $n = 100)
    {
        return $componente->set(array_fill_keys($campos, $this->palabras($n)));
    }

    public function test_el_primer_bloque_muestra_sus_tres_campos(): void
    {
        $this->get($this->ruta($this->nivel()))->assertOk()
            ->assertSee('Criterios de acreditación de asignaturas (100 a 200 palabras)')
            ->assertSee('Opciones para acreditar asignaturas (100 a 200 palabras)')
            ->assertSee('Criterios para la promoción de grado (100 a 200 palabras)')
            ->assertSee('Describa los criterios que debe cumplir un estudiante para acreditar cada asignatura...')
            ->assertSee('Guardar y continuar')
            ->assertDontSee('Contenido por definir')
            ->assertDontSee('Criterios para la titulación (100 a 200 palabras)')
            ->assertDontSee('Servicios de control escolar (100 a 200 palabras)');
    }

    public function test_guardar_el_primer_bloque_con_error_se_queda_y_no_escribe(): void
    {
        $componente = $this->componente($this->nivel());
        $this->llenar($componente, self::BLOQUE_1);

        $componente->set('opciones_acreditacion', 'corto')->call('guardar')
            ->assertHasErrors(['opciones_acreditacion'])
            ->assertHasNoErrors(['criterios_acreditacion', 'criterios_promocion'])
            ->assertSet('bloque', 1)
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $this->assertSame(0, DB::table('proyecto_educativo_criterios')->count());
    }

    public function test_guardar_el_primer_bloque_escribe_y_pasa_al_segundo(): void
    {
        $nivel = $this->nivel();
        $componente = $this->componente($nivel);
        $this->llenar($componente, self::BLOQUE_1, 120);

        $componente->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('bloque', 2)
            ->assertDispatched('toast', tipo: 'success', titulo: 'Paso guardado')
            ->assertSee('Criterios para la titulación (100 a 200 palabras)')
            ->assertSee('Servicios de control escolar (100 a 200 palabras)')
            ->assertSee('Describa los servicios de control escolar que ofrece la institución...')
            ->assertDontSee('Criterios de acreditación de asignaturas (100 a 200 palabras)')
            ->assertDontSee('Guardar y continuar');

        $fila = DB::table('proyecto_educativo_criterios')->where('escuela_nivel_id', $nivel->id)->first();
        $this->assertSame($this->palabras(120), $fila->criterios_acreditacion);
        $this->assertNull($fila->criterios_titulacion);
    }

    public function test_anterior_vuelve_sin_validar_ni_perder_lo_tecleado(): void
    {
        $componente = $this->componente($this->nivel());
        $this->llenar($componente, self::BLOQUE_1);
        $componente->call('guardar')
            ->set('criterios_titulacion', 'borrador')
            ->call('anteriorCriterios')
            ->assertSet('bloque', 1)->assertHasNoErrors()
            ->assertSet('criterios_titulacion', 'borrador')
            ->assertSet('criterios_acreditacion', $this->palabras(100));
    }

    public function test_guardar_el_segundo_bloque_completa_la_seccion(): void
    {
        $nivel = $this->nivel();
        $componente = $this->componente($nivel);
        $this->llenar($componente, self::BLOQUE_1);
        $componente->call('guardar');
        $this->llenar($componente, self::BLOQUE_2)->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('bloque', 2)
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa');

        $fila = DB::table('proyecto_educativo_criterios')->where('escuela_nivel_id', $nivel->id)->first();
        $this->assertSame($this->palabras(100), $fila->servicios_control_escolar);
    }

    public function test_guardar_el_segundo_bloque_con_error_no_escribe_ese_bloque(): void
    {
        $nivel = $this->nivel();
        $componente = $this->componente($nivel);
        $this->llenar($componente, self::BLOQUE_1);
        $componente->call('guardar');

        $componente->set(['criterios_titulacion' => $this->palabras(201), 'servicios_control_escolar' => ''])->call('guardar')
            ->assertHasErrors(['criterios_titulacion', 'servicios_control_escolar'])
            ->assertSet('bloque', 2)
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $this->assertNull(DB::table('proyecto_educativo_criterios')->where('escuela_nivel_id', $nivel->id)->value('criterios_titulacion'));
    }

    public function test_abre_con_lo_guardado_y_siempre_en_el_primer_bloque(): void
    {
        $nivel = $this->nivel();
        $guardar = app(GuardarCriteriosProyecto::class);
        $guardar->ejecutar($nivel->id, 1, new DatosCriteriosProyecto($this->palabras(110), $this->palabras(120), $this->palabras(130)));
        $guardar->ejecutar($nivel->id, 2, new DatosCriteriosProyecto(null, null, null, $this->palabras(140), $this->palabras(150)));

        $this->componente($nivel)
            ->assertSet('bloque', 1)
            ->assertSet('criterios_acreditacion', $this->palabras(110))
            ->assertSet('opciones_acreditacion', $this->palabras(120))
            ->assertSet('criterios_promocion', $this->palabras(130))
            ->assertSet('criterios_titulacion', $this->palabras(140))
            ->assertSet('servicios_control_escolar', $this->palabras(150));
    }

    public function test_tramite_enviado_no_guarda(): void
    {
        $nivel = $this->nivel();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);
        $componente = $this->componente($nivel);
        $this->llenar($componente, self::BLOQUE_1);

        $componente->call('guardar')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'El trámite ya se envió a revisión y no puede modificarse.');

        $this->assertSame(0, DB::table('proyecto_educativo_criterios')->count());
    }

    public function test_el_indice_marca_completa_la_seccion_trece_solo_con_los_dos_bloques(): void
    {
        $nivel = $this->nivel();
        $indice = route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $nivel->id]);
        $guardar = app(GuardarCriteriosProyecto::class);
        $todos = new DatosCriteriosProyecto(...array_fill_keys([...self::BLOQUE_1, ...self::BLOQUE_2], $this->palabras(100)));

        $this->assertSame(20, substr_count($this->get($indice)->assertOk()->getContent(), 'Pendiente'));

        $guardar->ejecutar($nivel->id, 1, $todos);
        $this->assertSame(20, substr_count($this->get($indice)->getContent(), 'Pendiente'));

        $guardar->ejecutar($nivel->id, 2, $todos);
        $html = $this->get($indice)->getContent();
        $this->assertSame(19, substr_count($html, 'Pendiente'));
        $this->assertSame(1, substr_count($html, 'Completa'));
    }

    public function test_otro_solicitante_recibe_403_y_primaria_404(): void
    {
        $nivel = $this->nivel();
        $this->actingAs(Solicitante::factory()->create()->user)->get($this->ruta($nivel))->assertForbidden();

        $primaria = $this->nivelListoPara('primaria', 'matricula');
        $this->actingAs($primaria->escuela->solicitante->user)->get(route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $primaria->id, 'seccion' => self::SLUG]))->assertNotFound();
    }
}
