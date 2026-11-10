<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\DTO\DatosPresentacionProyecto;
use App\Application\ProyectoEducativo\GuardarPresentacionProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Livewire\Tramite\Paso3\ProyectoEducativoSeccion;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3PresentacionProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private const SLUG = 'presentacion-del-proyecto-academico';

    public function test_el_slug_de_la_seccion_uno_sale_del_catalogo(): void
    {
        $this->assertSame(self::SLUG, SeccionesProyectoEducativo::presentacion());
    }

    /** @return array<string, string> */
    private function completo(): array
    {
        return [
            'fecha_entrega' => '2026-11-10',
            'nombre_institucion' => 'Instituto Ejemplo',
            'fecha_apertura' => '2020-08-01',
            'domicilio_completo' => 'Calle 1, 10, Centro, Querétaro',
            'representante_legal' => 'Ana Pérez',
            'correo' => 'ana@ejemplo.com',
            'telefono' => '(442) 123-4567',
            'celular' => '',
            'pagina_web' => '',
            'modalidad' => 'presencial',
            'ciclo_escolar' => 'semestral',
            'ciclo_escolar_otro' => '',
            'duracion' => '3_anios',
            'duracion_otro' => '',
            'sintesis_factibilidad' => trim(str_repeat('palabra ', 500)),
        ];
    }

    private function nivel(): EscuelaNivel
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->actingAs($nivel->escuela->solicitante->user);

        return $nivel;
    }

    private function componente(EscuelaNivel $nivel, string $seccion = self::SLUG)
    {
        return Livewire::test(ProyectoEducativoSeccion::class, ['escuelaNivel' => $nivel, 'seccion' => $seccion]);
    }

    public function test_la_seccion_uno_muestra_el_formulario_con_las_etiquetas_del_dueno(): void
    {
        $nivel = $this->nivel();

        $this->get(route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $nivel->id, 'seccion' => self::SLUG]))
            ->assertOk()
            ->assertSee('Datos Generales')
            ->assertSee('Fecha de entrega')
            ->assertSee('Nombre completo de la institución educativa')
            ->assertSee('Calle, número, colonia, municipio, estado, C.P.')
            ->assertSee('Nombre completo del representante')
            ->assertSee('correo@ejemplo.com')
            ->assertSee('(442) 000-0000')
            ->assertSee('https://www.ejemplo.com')
            ->assertSee('Modalidad, Ciclo Escolar y Duración')
            ->assertSee('Síntesis de los Resultados del Estudio de Factibilidad')
            ->assertSee('Síntesis (500 a 1000 palabras)')
            ->assertDontSee('Contenido por definir');
    }

    public function test_las_otras_secciones_siguen_con_el_marcador(): void
    {
        $nivel = $this->nivel();
        $otras = array_slice(array_keys(SeccionesProyectoEducativo::todas()), 1);
        $this->assertCount(19, $otras);

        foreach ([$otras[0], $otras[18]] as $slug) {
            $this->get(route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $nivel->id, 'seccion' => $slug]))
                ->assertOk()->assertSee('Contenido por definir')->assertDontSee('Datos Generales');
        }
    }

    public function test_guardar_completo_persiste_con_telefono_en_digitos_y_avisa(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set($this->completo())->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $fila = DB::table('proyecto_educativo_presentacion')->where('escuela_nivel_id', $nivel->id)->first();
        $this->assertSame('4421234567', $fila->telefono);
        $this->assertNull($fila->celular);
    }

    public function test_telefono_con_lada_52_se_guarda_normalizado(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set('telefono', '+52 442 123 4567')->call('guardar')->assertHasNoErrors()->assertSet('telefono', '(442) 123-4567');

        $this->assertSame('4421234567', DB::table('proyecto_educativo_presentacion')->value('telefono'));
    }

    public function test_borrador_incompleto_se_guarda_y_el_telefono_vuelve_formateado(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set('nombre_institucion', 'Solo nombre')->set('telefono', '4421234567')->call('guardar')->assertHasNoErrors();

        $this->componente($nivel)
            ->assertSet('nombre_institucion', 'Solo nombre')
            ->assertSet('telefono', '(442) 123-4567');
    }

    public function test_los_errores_del_caso_de_uso_llegan_a_cada_campo(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)
            ->set(['correo' => 'mal', 'telefono' => '123', 'pagina_web' => 'www.x.com', 'fecha_apertura' => now()->addDay()->toDateString(), 'sintesis_factibilidad' => trim(str_repeat('a ', 1001))])
            ->call('guardar')
            ->assertHasErrors(['correo', 'telefono', 'pagina_web', 'fecha_apertura', 'sintesis_factibilidad'])
            ->assertDispatched('toast');

        $this->assertSame(0, DB::table('proyecto_educativo_presentacion')->count());
    }

    public function test_tramite_enviado_no_guarda(): void
    {
        $nivel = $this->nivel();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->componente($nivel)->set($this->completo())->call('guardar')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'El trámite ya se envió a revisión y no puede modificarse.');

        $this->assertSame(0, DB::table('proyecto_educativo_presentacion')->count());
    }

    public function test_otro_solicitante_recibe_403(): void
    {
        $nivel = $this->nivel();

        $this->actingAs(Solicitante::factory()->create()->user)
            ->get(route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $nivel->id, 'seccion' => self::SLUG]))
            ->assertForbidden();
    }

    public function test_primaria_da_404(): void
    {
        $nivel = $this->nivelListoPara('primaria', 'matricula');
        $this->actingAs($nivel->escuela->solicitante->user);

        $this->get(route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $nivel->id, 'seccion' => self::SLUG]))->assertNotFound();
    }

    public function test_indice_marca_pendiente_y_luego_completa_solo_la_seccion_uno(): void
    {
        $nivel = $this->nivel();
        $ruta = route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $nivel->id]);

        $this->assertSame(20, substr_count($this->get($ruta)->assertOk()->getContent(), 'Pendiente'));

        $this->componente($nivel)->set('nombre_institucion', 'Solo nombre')->call('guardar');
        $this->assertSame(20, substr_count($this->get($ruta)->getContent(), 'Pendiente'));

        $datos = $this->completo();
        app(GuardarPresentacionProyecto::class)->ejecutar($nivel->id, new DatosPresentacionProyecto(...$datos));
        $html = $this->get($ruta)->getContent();
        $this->assertSame(19, substr_count($html, 'Pendiente'));
        $this->assertSame(1, substr_count($html, 'Completa'));
    }
}
