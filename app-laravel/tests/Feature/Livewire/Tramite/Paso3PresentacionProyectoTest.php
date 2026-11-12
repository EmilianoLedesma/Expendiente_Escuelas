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
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
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

    /** Guarda en la base (por el caso de uso) los campos dados, completando con vacío el resto. */
    private function sembrar(EscuelaNivel $nivel, array $campos): void
    {
        app(GuardarPresentacionProyecto::class)->ejecutar($nivel->id, new DatosPresentacionProyecto(...$campos));
    }

    /** @return array<string, string> */
    private function pasosUnoYDos(): array
    {
        return array_diff_key($this->completo(), ['sintesis_factibilidad' => 1]);
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

    public function test_el_paso_inicial_depende_de_lo_guardado(): void
    {
        $nivel = $this->nivel();
        $this->componente($nivel)->assertSet('paso', 1);

        $this->sembrar($nivel, ['nombre_institucion' => 'Solo nombre']);
        $this->componente($nivel)->assertSet('paso', 1);

        $this->sembrar($nivel, array_merge($this->pasosUnoYDos(), ['modalidad' => null, 'ciclo_escolar' => null, 'duracion' => null]));
        $this->componente($nivel)->assertSet('paso', 2);

        $this->sembrar($nivel, $this->pasosUnoYDos());
        $this->componente($nivel)->assertSet('paso', 3);

        $this->sembrar($nivel, $this->completo());
        $this->componente($nivel)->assertSet('paso', 1);
    }

    public function test_solo_se_pinta_el_paso_actual_con_las_etiquetas_del_dueno(): void
    {
        $nivel = $this->nivel();
        $ruta = route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $nivel->id, 'seccion' => self::SLUG]);

        $this->get($ruta)->assertOk()
            ->assertSee('Datos Generales')
            ->assertSee('Fecha de entrega')
            ->assertSee('Nombre completo de la institución educativa')
            ->assertSee('Calle, número, colonia, municipio, estado, C.P.')
            ->assertSee('Nombre completo del representante')
            ->assertSee('correo@ejemplo.com')
            ->assertSee('(442) 000-0000')
            ->assertSee('https://www.ejemplo.com')
            ->assertSee('Siguiente')
            ->assertDontSee('Síntesis (500 a 1000 palabras)')
            ->assertDontSee('Presencial')
            ->assertDontSee('Contenido por definir');

        $this->sembrar($nivel, $this->pasosUnoYDos());
        $this->get($ruta)->assertOk()
            ->assertSee('Síntesis (500 a 1000 palabras)')
            ->assertSee('Anterior')
            ->assertSee('Guardar')
            ->assertDontSee('Nombre completo de la institución educativa')
            ->assertDontSee('Presencial');

        $this->sembrar($nivel, array_merge($this->pasosUnoYDos(), ['modalidad' => null]));
        $this->componente($nivel)->assertSet('paso', 2)->assertSee('Presencial')->assertSee('Semestral')->assertSee('3 años')
            ->assertDontSee('Nombre completo de la institución educativa')
            ->assertDontSee('Síntesis (500 a 1000 palabras)');
    }

    public function test_el_indicador_muestra_los_tres_pasos(): void
    {
        $this->componente($this->nivel())
            ->assertSee('Paso 1 de 3')
            ->assertSee('Datos Generales')
            ->assertSee('Modalidad, Ciclo Escolar y Duración')
            ->assertSee('Síntesis de los Resultados del Estudio de Factibilidad');
    }

    public function test_las_otras_secciones_siguen_con_el_marcador(): void
    {
        $nivel = $this->nivel();
        $otras = array_slice(array_keys(SeccionesProyectoEducativo::todas()), 11); // 1 a 11 son formularios
        $this->assertCount(9, $otras);

        foreach ([$otras[0], $otras[8]] as $slug) {
            $this->get(route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $nivel->id, 'seccion' => $slug]))
                ->assertOk()->assertSee('Contenido por definir')->assertDontSee('Datos Generales');
        }
    }

    public function test_siguiente_bloquea_por_los_campos_del_paso_actual_y_no_por_los_de_otros(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->call('siguiente')
            ->assertSet('paso', 1)
            ->assertHasErrors(['fecha_entrega', 'nombre_institucion', 'fecha_apertura', 'domicilio_completo', 'representante_legal', 'correo', 'telefono'])
            ->assertHasNoErrors(['modalidad', 'ciclo_escolar', 'duracion', 'sintesis_factibilidad'])
            ->assertDispatched('toast', tipo: 'error');

        $this->componente($nivel)->set($this->pasosUnoYDos())->set('correo', 'mal')->call('siguiente')
            ->assertSet('paso', 1)->assertHasErrors(['correo'])->assertHasNoErrors(['nombre_institucion', 'modalidad']);
    }

    public function test_siguiente_avanza_y_guarda_el_borrador(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set($this->pasosUnoYDos())->set('modalidad', '')->set('ciclo_escolar', '')->set('duracion', '')
            ->call('siguiente')
            ->assertHasNoErrors()
            ->assertSet('paso', 2);

        $fila = DB::table('proyecto_educativo_presentacion')->where('escuela_nivel_id', $nivel->id)->first();
        $this->assertSame('4421234567', $fila->telefono);
        $this->assertSame('Instituto Ejemplo', $fila->nombre_institucion);

        // El paso 2 exige lo suyo (y no la síntesis) y "otro" pide su texto.
        $this->componente($nivel)->call('siguiente')->assertSet('paso', 2)
            ->assertHasErrors(['modalidad', 'ciclo_escolar', 'duracion'])->assertHasNoErrors(['sintesis_factibilidad']);
        $this->componente($nivel)->set(['modalidad' => 'virtual', 'ciclo_escolar' => 'otro', 'duracion' => '2_anios'])->call('siguiente')
            ->assertSet('paso', 2)->assertHasErrors(['ciclo_escolar_otro']);
        $this->componente($nivel)->set(['modalidad' => 'virtual', 'ciclo_escolar' => 'otro', 'ciclo_escolar_otro' => 'Tetra', 'duracion' => '2_anios'])->call('siguiente')
            ->assertHasNoErrors()->assertSet('paso', 3);
        $this->assertSame('Tetra', DB::table('proyecto_educativo_presentacion')->value('ciclo_escolar_otro'));
    }

    public function test_anterior_no_valida_y_conserva_lo_tecleado(): void
    {
        $nivel = $this->nivel();
        $this->sembrar($nivel, $this->pasosUnoYDos());

        $this->componente($nivel)->assertSet('paso', 3)
            ->set('sintesis_factibilidad', 'texto a medias')
            ->call('anterior')->assertSet('paso', 2)->assertHasNoErrors()->assertSet('sintesis_factibilidad', 'texto a medias')
            ->set('modalidad', '')
            ->call('anterior')->assertSet('paso', 1)->assertHasNoErrors()
            ->call('anterior')->assertSet('paso', 1)
            ->assertSet('nombre_institucion', 'Instituto Ejemplo');
    }

    public function test_guardar_en_el_paso_final_valida_la_sintesis_y_avisa(): void
    {
        $nivel = $this->nivel();
        $this->sembrar($nivel, $this->pasosUnoYDos());

        $this->componente($nivel)->set('sintesis_factibilidad', trim(str_repeat('a ', 499)))->call('guardar')
            ->assertHasErrors(['sintesis_factibilidad'])->assertDispatched('toast', tipo: 'error');
        $this->assertNull(DB::table('proyecto_educativo_presentacion')->value('sintesis_factibilidad'));

        $this->componente($nivel)->set('sintesis_factibilidad', trim(str_repeat('a ', 1001)))->call('guardar')
            ->assertHasErrors(['sintesis_factibilidad']);

        $this->componente($nivel)->set('sintesis_factibilidad', trim(str_repeat('palabra ', 500)))->call('guardar')
            ->assertHasNoErrors()->assertDispatched('toast', tipo: 'success', titulo: 'Sección guardada');
        $this->assertSame(500, str_word_count((string) DB::table('proyecto_educativo_presentacion')->value('sintesis_factibilidad')));
    }

    public function test_el_guardado_final_exige_todo_y_lleva_al_primer_paso_con_errores(): void
    {
        $nivel = $this->nivel();
        $this->sembrar($nivel, $this->pasosUnoYDos());

        $this->componente($nivel)->assertSet('paso', 3)
            ->set('sintesis_factibilidad', trim(str_repeat('palabra ', 500)))
            ->set('nombre_institucion', '')
            ->call('guardar')
            ->assertHasErrors(['nombre_institucion'])
            ->assertSet('paso', 1)
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $this->assertNull(DB::table('proyecto_educativo_presentacion')->value('sintesis_factibilidad'));
    }

    public function test_los_campos_del_paso_uno_llevan_las_restricciones_del_lado_del_cliente(): void
    {
        $html = $this->componente($this->nivel())->html();

        foreach ([
            'id="nombre_institucion"' => ['maxlength="255"', 'required', 'autocomplete="organization"'],
            'id="domicilio_completo"' => ['maxlength="500"', 'required', 'autocomplete="street-address"'],
            'id="representante_legal"' => ['maxlength="255"', 'required', 'autocomplete="name"'],
            'id="correo"' => ['type="email"', 'maxlength="255"', 'required', 'autocomplete="email"'],
            'id="telefono"' => ['type="tel"', 'inputmode="tel"', 'maxlength="20"', 'required', 'autocomplete="tel-national"'],
            'id="celular"' => ['type="tel"', 'inputmode="tel"', 'maxlength="20"', 'autocomplete="tel-national"'],
            'id="pagina_web"' => ['type="url"', 'maxlength="255"', 'placeholder="https://www.ejemplo.com"'],
            'id="fecha_entrega"' => ['type="date"', 'min="1900-01-01"', 'required'],
            'id="fecha_apertura"' => ['type="date"', 'min="1900-01-01"', 'max="'.now()->toDateString().'"', 'required'],
        ] as $ancla => $atributos) {
            $inicio = strpos($html, $ancla);
            $this->assertNotFalse($inicio, $ancla);
            $etiqueta = substr($html, strrpos(substr($html, 0, $inicio), '<input'), 700);
            $etiqueta = substr($etiqueta, 0, strpos($etiqueta, '>') + 1);
            foreach ($atributos as $atributo) {
                $this->assertStringContainsString($atributo, $etiqueta, "{$ancla}: {$atributo}");
            }
        }
    }

    public function test_los_campos_del_paso_dos_y_tres_llevan_sus_restricciones(): void
    {
        $nivel = $this->nivel();
        $this->sembrar($nivel, $this->pasosUnoYDos());
        $html = $this->componente($nivel)->html();

        $this->assertStringContainsString('x-on:input="recortar()"', $html);
        $this->assertStringContainsString('max: 1000', $html);
        $this->assertStringContainsString('min: 500', $html);
        $this->assertStringContainsString('required', $html);
    }

    public function test_guardar_fuera_del_paso_final_no_hace_nada(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set($this->completo())->call('guardar')->assertSet('paso', 1);

        $this->assertSame(0, DB::table('proyecto_educativo_presentacion')->count());
    }

    public function test_el_paso_no_se_puede_fijar_desde_el_cliente(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->componente($this->nivel())->set('paso', 3);
    }

    public function test_telefono_con_lada_52_se_guarda_normalizado_y_vuelve_formateado(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)->set($this->pasosUnoYDos())->set('telefono', '+52 442 123 4567')->call('siguiente')
            ->assertHasNoErrors()->assertSet('telefono', '(442) 123-4567');

        $this->assertSame('4421234567', DB::table('proyecto_educativo_presentacion')->value('telefono'));
    }

    public function test_el_telefono_guardado_vuelve_formateado_al_abrir(): void
    {
        $nivel = $this->nivel();
        $this->sembrar($nivel, ['nombre_institucion' => 'Solo nombre', 'telefono' => '4421234567']);

        $this->componente($nivel)->assertSet('nombre_institucion', 'Solo nombre')->assertSet('telefono', '(442) 123-4567');
    }

    public function test_los_errores_del_caso_de_uso_llegan_a_cada_campo(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)
            ->set(['correo' => 'mal', 'telefono' => '123', 'pagina_web' => 'www.x.com', 'fecha_apertura' => now()->addDay()->toDateString()])
            ->call('siguiente')
            ->assertHasErrors(['correo', 'telefono', 'pagina_web', 'fecha_apertura'])
            ->assertDispatched('toast');

        $this->assertSame(0, DB::table('proyecto_educativo_presentacion')->count());
    }

    public function test_tramite_enviado_no_guarda(): void
    {
        $nivel = $this->nivel();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->componente($nivel)->set($this->completo())->call('siguiente')
            ->assertSet('paso', 1)
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

        $this->sembrar($nivel, $this->completo());
        $html = $this->get($ruta)->getContent();
        $this->assertSame(19, substr_count($html, 'Pendiente'));
        $this->assertSame(1, substr_count($html, 'Completa'));
    }
}
