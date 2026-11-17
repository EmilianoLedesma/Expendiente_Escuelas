<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\DTO\DatosBibliografiaProyecto;
use App\Application\ProyectoEducativo\GuardarBibliografiaProyecto;
use App\Livewire\Tramite\Paso3\ProyectoEducativoSeccion;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3BibliografiaProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private const SLUG = 'bibliografia-del-proyecto';

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

    /** @param  array<string, string>  $cambios */
    private function ref(array $cambios = []): array
    {
        return array_merge(['autores' => 'Pérez, J.', 'anio' => '2020', 'titulo' => 'Aprender a aprender', 'fuente' => 'Editorial Trillas', 'url' => ''], $cambios);
    }

    public function test_la_pagina_abre_con_una_referencia_vacia_y_el_boton_para_agregar(): void
    {
        $html = $this->get($this->ruta($this->nivel()))->assertOk()
            ->assertSee('Bibliografía del Proyecto')
            ->assertSee('formato APA')
            ->assertSee('Referencia 1')
            ->assertSee('Autor(es)')
            ->assertSee('Año')
            ->assertSee('Título')
            ->assertSee('Fuente')
            ->assertSee('URL o DOI')
            ->assertSee('Agregar referencia')
            ->assertSee('Guardar')
            ->assertDontSee('Quitar esta referencia')
            ->assertDontSee('Contenido por definir')
            ->getContent();

        $this->assertSame(0, substr_count($html, 'Referencia 2'));
    }

    public function test_agregar_suma_una_referencia_vacia_y_aparece_quitar(): void
    {
        $componente = $this->componente($this->nivel());

        $componente->call('agregarReferencia')
            ->assertCount('referencias', 2)
            ->assertSee('Referencia 2')
            ->assertSee('Quitar esta referencia');
        $componente->call('agregarReferencia')->assertCount('referencias', 3);
    }

    public function test_no_se_pasa_de_cincuenta_referencias(): void
    {
        $componente = $this->componente($this->nivel());

        foreach (range(1, 60) as $_) {
            $componente->call('agregarReferencia');
        }

        $componente->assertCount('referencias', 50)->assertDontSee('Agregar referencia');
    }

    public function test_quitar_saca_esa_referencia_y_siempre_queda_una(): void
    {
        $componente = $this->componente($this->nivel());
        $componente->set('referencias', [$this->ref(['autores' => 'Uno']), $this->ref(['autores' => 'Dos']), $this->ref(['autores' => 'Tres'])]);

        $componente->call('quitarReferencia', 1)
            ->assertCount('referencias', 2)
            ->assertSet('referencias.0.autores', 'Uno')
            ->assertSet('referencias.1.autores', 'Tres');
        $componente->call('quitarReferencia', 0)->call('quitarReferencia', 0)->assertCount('referencias', 1);
    }

    public function test_la_vista_previa_muestra_la_referencia_en_formato_apa_con_el_titulo_en_cursiva(): void
    {
        $this->componente($this->nivel())
            ->set('referencias', [$this->ref(['autores' => 'Pérez, J., & Gómez, L.', 'url' => 'https://doi.org/10.1/x'])])
            ->assertSee('Vista previa APA')
            ->assertSee('Pérez, J., &amp; Gómez, L. (2020).', false)
            ->assertSeeHtml('<em>Aprender a aprender.</em>')
            ->assertSee('Editorial Trillas. https://doi.org/10.1/x');
    }

    public function test_guardar_completo_avisa_y_deja_la_lista_en_orden_alfabetico(): void
    {
        $nivel = $this->nivel();

        $this->componente($nivel)
            ->set('referencias', [$this->ref(['autores' => 'Zapata, M.']), $this->ref(['autores' => 'Álvarez, C.'])])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', titulo: 'Sección completa')
            ->assertSet('referencias.0.autores', 'Álvarez, C.')
            ->assertSet('referencias.1.autores', 'Zapata, M.');

        $this->assertCount(2, DB::table('proyecto_educativo_bibliografia')->where('escuela_nivel_id', $nivel->id)->get());
    }

    public function test_guardar_con_errores_los_marca_por_referencia_y_no_escribe(): void
    {
        $this->componente($this->nivel())
            ->set('referencias', [$this->ref(), $this->ref(['titulo' => '', 'anio' => 'x'])])
            ->call('guardar')
            ->assertHasErrors(['referencias.1.titulo', 'referencias.1.anio'])
            ->assertHasNoErrors(['referencias.0.autores'])
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');

        $this->assertSame(0, DB::table('proyecto_educativo_bibliografia')->count());
    }

    public function test_abre_con_lo_guardado(): void
    {
        $nivel = $this->nivel();
        app(GuardarBibliografiaProyecto::class)->ejecutar($nivel->id, new DatosBibliografiaProyecto([$this->ref(['autores' => 'B']), $this->ref(['autores' => 'A', 'url' => 'https://a.com'])]));

        $this->componente($nivel)
            ->assertCount('referencias', 2)
            ->assertSet('referencias.0.autores', 'A')
            ->assertSet('referencias.0.url', 'https://a.com')
            ->assertSet('referencias.1.autores', 'B');
    }

    public function test_un_valor_manipulado_no_rompe_la_pagina(): void
    {
        $this->componente($this->nivel())
            ->set('referencias', ['no soy una lista de referencias'])
            ->call('guardar')
            ->assertHasErrors()
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');
    }

    public function test_tramite_enviado_no_guarda(): void
    {
        $nivel = $this->nivel();
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->componente($nivel)->set('referencias', [$this->ref()])->call('guardar')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'El trámite ya se envió a revisión y no puede modificarse.');

        $this->assertSame(0, DB::table('proyecto_educativo_bibliografia')->count());
    }

    public function test_el_indice_marca_completa_la_seccion_veinte(): void
    {
        $nivel = $this->nivel();
        $indice = route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $nivel->id]);

        $this->assertSame(20, substr_count($this->get($indice)->assertOk()->getContent(), 'Pendiente'));

        app(GuardarBibliografiaProyecto::class)->ejecutar($nivel->id, new DatosBibliografiaProyecto([$this->ref()]));
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
