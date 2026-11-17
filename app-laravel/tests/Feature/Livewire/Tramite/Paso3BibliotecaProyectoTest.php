<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\DTO\DatosBibliotecaProyecto;
use App\Application\ProyectoEducativo\GuardarBibliotecaProyecto;
use App\Livewire\Tramite\Paso3\ProyectoEducativoSeccion;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3BibliotecaProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private const SLUG = 'servicios-de-biblioteca-y-acervo-bibliografico';

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

    /** Bloque 1 válido: biblioteca física, sin encargado, con préstamo. */
    private function llenarBloque1($componente)
    {
        return $componente->set(['biblioteca_tipo' => 'fisica', 'biblioteca_tiene_encargado' => 'no', 'biblioteca_prestamo' => $this->palabras(80)]);
    }

    /** Bloque 2 válido: sin programa de lectura, con otros programas y las tres partes del acervo. */
    private function llenarBloque2($componente)
    {
        return $componente->set([
            'biblioteca_tiene_programa_lectura' => 'no', 'biblioteca_otros_programas' => $this->palabras(80),
            'acervo_clasificacion' => $this->palabras(80), 'acervo_titulos_basicos' => $this->palabras(80), 'acervo_titulos_lectura' => $this->palabras(80),
        ]);
    }

    public function test_el_primer_paso_muestra_datos_generales_y_perfiles_y_oculta_lo_condicional(): void
    {
        $this->get($this->ruta($this->nivel()))->assertOk()
            ->assertSee('Paso 1 de 2')
            ->assertSee('Su biblioteca es...')
            ->assertSee('Física')->assertSee('Virtual')->assertSee('Mixta')
            ->assertSee('¿Cuenta con personal encargado de biblioteca?')
            ->assertSee('Mecanismos y tipos de préstamo de materiales (80 a 250 palabras)')
            ->assertSee('Describa los mecanismos de préstamo disponibles...')
            ->assertSee('Guardar y continuar')
            ->assertDontSee('La biblioteca virtual es...')
            ->assertDontSee('Perfil de la persona encargada')
            ->assertDontSee('¿Cuenta con programa de lectura?')
            ->assertDontSee('Contenido por definir');
    }

    public function test_la_biblioteca_virtual_o_mixta_muestra_su_tipo_y_la_fisica_no(): void
    {
        $componente = $this->componente($this->nivel());

        $componente->set('biblioteca_tipo', 'fisica')->assertDontSee('La biblioteca virtual es...');
        foreach (['virtual', 'mixta'] as $tipo) {
            $componente->set('biblioteca_tipo', $tipo)->assertSee('La biblioteca virtual es...')->assertSee('Propia')->assertSee('De dominio público');
        }
    }

    public function test_con_encargado_aparece_el_perfil(): void
    {
        $componente = $this->componente($this->nivel());

        $componente->set('biblioteca_tiene_encargado', 'no')->assertDontSee('Perfil de la persona encargada');
        $componente->set('biblioteca_tiene_encargado', 'si')
            ->assertSee('Perfil de la persona encargada (80 a 250 palabras)')
            ->assertSee('Describa el perfil profesional de la persona encargada de la biblioteca...');
    }

    public function test_guardar_el_paso_uno_con_error_se_queda_y_no_escribe(): void
    {
        $componente = $this->componente($this->nivel());

        $componente->call('guardar')
            ->assertHasErrors(['biblioteca_tipo', 'biblioteca_tiene_encargado', 'biblioteca_prestamo'])
            ->assertSet('bloque', 1)
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $this->assertSame(0, DB::table('proyecto_educativo_biblioteca')->count());
    }

    public function test_guardar_el_paso_uno_escribe_y_pasa_al_dos(): void
    {
        $nivel = $this->nivel();
        $componente = $this->llenarBloque1($this->componente($nivel));

        $componente->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('bloque', 2)
            ->assertSee('Paso 2 de 2')
            ->assertDispatched('toast', tipo: 'success', titulo: 'Paso guardado')
            ->assertSee('¿Cuenta con programa de lectura?')
            ->assertSee('Clasificación (80 a 250 palabras)')
            ->assertSee('Describa el sistema de clasificación del acervo bibliográfico...')
            ->assertDontSee('Su biblioteca es...')
            ->assertDontSee('Guardar y continuar');

        $fila = DB::table('proyecto_educativo_biblioteca')->where('escuela_nivel_id', $nivel->id)->first();
        $this->assertSame('fisica', $fila->biblioteca_tipo);
        $this->assertNull($fila->acervo_clasificacion);
    }

    public function test_con_programa_de_lectura_aparece_su_texto(): void
    {
        $componente = $this->llenarBloque1($this->componente($this->nivel()))->call('guardar');

        $componente->set('biblioteca_tiene_programa_lectura', 'no')->assertDontSee('Propósitos, implementación, promoción y justificación');
        $componente->set('biblioteca_tiene_programa_lectura', 'si')
            ->assertSee('Propósitos, implementación, promoción y justificación del programa de lectura (80 a 250 palabras)')
            ->assertSee('Describa el programa de lectura de la institución...');
    }

    public function test_anterior_vuelve_sin_validar_ni_perder_lo_tecleado(): void
    {
        $componente = $this->llenarBloque1($this->componente($this->nivel()))->call('guardar');

        $componente->set('acervo_clasificacion', 'borrador')->call('anteriorBiblioteca')
            ->assertSet('bloque', 1)->assertHasNoErrors()
            ->assertSet('acervo_clasificacion', 'borrador')->assertSet('biblioteca_tipo', 'fisica');
    }

    public function test_guardar_el_segundo_paso_completa_la_seccion(): void
    {
        $nivel = $this->nivel();
        $componente = $this->llenarBloque1($this->componente($nivel))->call('guardar');

        $this->llenarBloque2($componente)->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('bloque', 2)
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa');

        $this->assertSame($this->palabras(80), DB::table('proyecto_educativo_biblioteca')->where('escuela_nivel_id', $nivel->id)->value('acervo_titulos_lectura'));
    }

    public function test_guardar_el_segundo_paso_con_error_no_escribe_ese_bloque(): void
    {
        $nivel = $this->nivel();
        $componente = $this->llenarBloque1($this->componente($nivel))->call('guardar');

        $componente->set(['biblioteca_tiene_programa_lectura' => 'si', 'biblioteca_programa_lectura' => ''])->call('guardar')
            ->assertHasErrors(['biblioteca_programa_lectura', 'biblioteca_otros_programas', 'acervo_clasificacion'])
            ->assertSet('bloque', 2)
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $this->assertNull(DB::table('proyecto_educativo_biblioteca')->where('escuela_nivel_id', $nivel->id)->value('acervo_clasificacion'));
    }

    public function test_abre_con_lo_guardado_y_siempre_en_el_primer_paso(): void
    {
        $nivel = $this->nivel();
        $guardar = app(GuardarBibliotecaProyecto::class);
        $guardar->ejecutar($nivel->id, 1, new DatosBibliotecaProyecto('virtual', 'propia', 'si', $this->palabras(90), $this->palabras(95)));
        $guardar->ejecutar($nivel->id, 2, new DatosBibliotecaProyecto(null, null, null, null, null, 'si', $this->palabras(100), $this->palabras(105), $this->palabras(110), $this->palabras(115), $this->palabras(120)));

        $this->componente($nivel)
            ->assertSet('bloque', 1)
            ->assertSet('biblioteca_tipo', 'virtual')
            ->assertSet('biblioteca_virtual_tipo', 'propia')
            ->assertSet('biblioteca_tiene_encargado', 'si')
            ->assertSet('biblioteca_perfil_encargado', $this->palabras(90))
            ->assertSet('biblioteca_prestamo', $this->palabras(95))
            ->assertSet('biblioteca_tiene_programa_lectura', 'si')
            ->assertSet('biblioteca_programa_lectura', $this->palabras(100))
            ->assertSet('acervo_titulos_lectura', $this->palabras(120));
    }

    public function test_tramite_enviado_no_guarda(): void
    {
        $nivel = $this->nivel();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);
        $componente = $this->llenarBloque1($this->componente($nivel));

        $componente->call('guardar')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'El trámite ya se envió a revisión y no puede modificarse.');

        $this->assertSame(0, DB::table('proyecto_educativo_biblioteca')->count());
    }

    public function test_el_indice_marca_completa_la_seccion_diecinueve_solo_con_los_dos_pasos(): void
    {
        $nivel = $this->nivel();
        $indice = route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $nivel->id]);

        $this->assertSame(20, substr_count($this->get($indice)->assertOk()->getContent(), 'Pendiente'));

        $componente = $this->llenarBloque1($this->componente($nivel))->call('guardar');
        $this->assertSame(20, substr_count($this->get($indice)->getContent(), 'Pendiente'));

        $this->llenarBloque2($componente)->call('guardar');
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
