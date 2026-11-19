<?php

namespace Tests\Feature;

use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

/** Página pública con el marco legal del trámite (leyes, acuerdos y procedimientos), sin iniciar sesión. */
class MarcoLegalTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    public function test_un_visitante_sin_sesion_ve_el_marco_legal(): void
    {
        $this->get(route('marco-legal'))
            ->assertOk()
            ->assertSee('Marco legal del trámite')
            ->assertSee('Ley de Educación del Estado de Querétaro')
            ->assertSee('Ley General de Educación')
            ->assertSee('Acuerdo número 243')
            ->assertSee('Acuerdo número 450')
            ->assertSee('Última verificación')
            ->assertSee('No sustituye el texto oficial');
    }

    public function test_la_ruta_es_publica_y_se_ve_igual_con_sesion(): void
    {
        $this->actingAs(Solicitante::factory()->create()->user)->get(route('marco-legal'))->assertOk()->assertSee('Marco legal del trámite');
        $this->actingAs($this->supervisor())->get(route('marco-legal'))->assertOk()->assertSee('Marco legal del trámite');
    }

    public function test_cada_entrada_de_la_configuracion_esta_completa(): void
    {
        $grupos = config('marco_legal.grupos');
        $this->assertNotEmpty($grupos);
        foreach ($grupos as $grupo) {
            $this->assertNotSame('', $grupo['titulo']);
            $this->assertNotEmpty($grupo['entradas'], $grupo['titulo']);
            foreach ($grupo['entradas'] as $entrada) {
                $this->assertNotSame('', $entrada['titulo']);
                $this->assertNotSame('', $entrada['rige'], $entrada['titulo']);
                $this->assertNotEmpty($entrada['articulos'], $entrada['titulo']);
                $this->assertTrue($entrada['url'] === null || str_starts_with($entrada['url'], 'https://'), $entrada['titulo'].': la fuente oficial va por https');
            }
        }
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', config('marco_legal.verificado_el'));
    }

    public function test_los_enlaces_externos_abren_en_pestana_nueva_sin_opener(): void
    {
        $html = $this->get(route('marco-legal'))->getContent();
        $url = config('marco_legal.grupos.0.entradas.0.url');

        $this->assertNotNull($url);
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(e($url), '#').'"[^>]*target="_blank"[^>]*rel="noopener noreferrer"#', $html);
    }

    public function test_describe_el_procedimiento_posterior_a_la_aprobacion_documental(): void
    {
        $this->get(route('marco-legal'))
            ->assertOk()
            ->assertSee('visita de inspección')
            ->assertSee('treinta días hábiles')
            ->assertSee('acuerdo de incorporación');
    }

    public function test_el_pie_de_toda_pagina_enlaza_el_marco_legal(): void
    {
        $enlace = 'href="'.route('marco-legal').'"';

        $this->get(route('login'))->assertOk()->assertSee($enlace, false);
        $this->get(route('marco-legal'))->assertSee($enlace, false);
        $this->actingAs(Solicitante::factory()->create()->user)->get(route('tramite.index'))->assertOk()->assertSee($enlace, false);
    }

    public function test_el_aviso_de_que_no_hay_acuerdo_remite_al_marco_legal(): void
    {
        $dueno = Solicitante::factory()->create();
        $this->nivelDe($dueno, 'primaria', 'en_revision');

        $this->actingAs($dueno->user)->get(route('tramite.index'))
            ->assertSee('Consulta el marco legal')
            ->assertSee('href="'.route('marco-legal').'"', false);
    }
}
