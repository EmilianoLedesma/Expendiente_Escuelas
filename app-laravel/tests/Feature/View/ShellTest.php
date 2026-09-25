<?php

namespace Tests\Feature\View;

use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_paginas_de_acceso_usan_el_shell_institucional(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Saltar al contenido')
            ->assertSee('href="#contenido"', false)
            ->assertSee('id="contenido"', false)
            ->assertSee('Secretaría de Educación del Estado de Querétaro')
            ->assertSee('Trámite de Incorporación de Escuelas Particulares')
            ->assertSee('Sistema de Incorporación · versión MVP');
    }

    public function test_las_paginas_del_tramite_usan_el_shell_con_la_cuenta(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->actingAs($solicitante->user)
            ->get(route('tramite.preregistro'))
            ->assertOk()
            ->assertSee('Saltar al contenido')
            ->assertSee('id="contenido"', false)
            ->assertSee('Trámite de Incorporación de Escuelas Particulares')
            ->assertSee($solicitante->user->name)
            ->assertSee('Cerrar sesión')
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('Sistema de Incorporación · versión MVP');
    }

    /** Las pruebas de round-trip toman el primer wire:snapshot: el shell no debe montar Livewire antes del contenido. */
    public function test_el_shell_no_monta_componentes_livewire_antes_del_contenido(): void
    {
        $html = $this->actingAs(Solicitante::factory()->create()->user)
            ->get(route('tramite.preregistro'))
            ->getContent();

        $this->assertLessThan(strpos($html, 'wire:snapshot'), strpos($html, 'id="contenido"'));
    }

    public function test_el_css_global_define_foco_visible_y_movimiento_reducido(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString(':focus-visible', $css);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css);
    }

    /** Queja del dueño (2026-09-25): los puntos del asistente aparecían en Mis trámites. */
    public function test_mis_tramites_no_muestra_recorrido_ni_puntos_del_asistente(): void
    {
        $this->actingAs(Solicitante::factory()->create()->user)
            ->get(route('tramite.index'))
            ->assertOk()
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('data-estado=', false)
            ->assertDontSee('Secciones del trámite');
    }
}
