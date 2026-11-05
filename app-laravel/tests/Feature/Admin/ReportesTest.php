<?php

namespace Tests\Feature\Admin;

use App\Application\Revision\RolesSedeq;
use App\Filament\Pages\Reportes;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolesSeeder)->run();
        $this->actingAs(User::factory()->create()->assignRole(RolesSedeq::ADMIN));
    }

    public function test_el_admin_ve_las_secciones(): void
    {
        Livewire::test(Reportes::class)->assertOk()
            ->assertSee('Niveles por estado')->assertSee('Trámites activos y archivados')
            ->assertSee('Trámites activos por municipio')->assertSee('Esperando al solicitante')
            ->assertSee('Carga de revisión')->assertSee('Descargar CSV');
    }

    public function test_muestra_una_pestana_a_la_vez_y_se_cambia_de_pestana(): void
    {
        Livewire::test(Reportes::class)
            ->assertSee('Niveles por estado')->assertDontSee('Devuelto el')
            ->call('elegir', 'esperando')->assertSet('seccion', 'esperando')->assertSet('pagina', 1)
            ->assertSee('Sin datos por ahora');
    }

    public function test_pagina_las_filas_de_la_pestana(): void
    {
        foreach (range(1, 12) as $i) {
            $p = Plantel::create(['calle' => 'Hidalgo', 'colonia' => 'Centro', 'municipio' => sprintf('Municipio %02d', $i), 'codigo_postal' => '76000']);
            Escuela::create(['plantel_id' => $p->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
        }

        Livewire::test(Reportes::class)->call('elegir', 'por_municipio')
            ->assertSee('Municipio 01')->assertDontSee('Municipio 12')->assertSee('Página 1 de 2')
            ->call('siguiente')->assertSee('Municipio 12')->assertDontSee('Municipio 01')->assertSee('Página 2 de 2')
            ->call('siguiente')->assertSet('pagina', 2)
            ->call('anterior')->assertSet('pagina', 1)
            ->set('porPagina', 25)->assertSee('Municipio 12')->assertSet('pagina', 1);
    }

    public function test_descarga_el_csv_de_una_seccion(): void
    {
        Livewire::test(Reportes::class)->call('descargar', 'por_estado')
            ->assertFileDownloaded('reporte-por_estado-'.now()->format('Y-m-d').'.csv');
    }

    public function test_una_seccion_desconocida_da_404(): void
    {
        Livewire::test(Reportes::class)->call('descargar', 'no_existe')->assertNotFound();
    }

    public function test_quien_no_es_admin_no_entra(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/reportes')->assertForbidden();
    }
}
