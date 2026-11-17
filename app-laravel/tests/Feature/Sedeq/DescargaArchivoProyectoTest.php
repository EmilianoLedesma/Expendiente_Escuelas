<?php

namespace Tests\Feature\Sedeq;

use App\Application\Revision\RolesSedeq;
use App\Models\EscuelaNivel;
use App\Models\ProyectoEducativoMapaCurricular;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

/** Ruta de descarga de los PDF del proyecto educativo: evaluador y supervisor sí, revisor y solicitante no. */
class DescargaArchivoProyectoTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private EscuelaNivel $nivel;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolesSeeder)->run();
        Storage::fake('documentos');
        Storage::disk('documentos')->put('proyecto_educativo/1/mapa.pdf', '%PDF-1.4 prueba');

        $this->nivel = $this->nivelDe(Solicitante::factory()->create(), 'media_superior', 'en_revision');
        ProyectoEducativoMapaCurricular::create(['escuela_nivel_id' => $this->nivel->id, 'descripcion' => 'x', 'archivo_path' => 'proyecto_educativo/1/mapa.pdf', 'archivo_nombre' => 'mapa.pdf', 'archivo_paginas' => 1]);
    }

    private function url(string $clave = 'mapa_curricular'): string
    {
        return route('sedeq.proyecto-archivo', ['escuelaNivel' => $this->nivel->id, 'clave' => $clave]);
    }

    public function test_evaluador_y_supervisor_descargan_el_pdf(): void
    {
        $evaluador = User::factory()->create()->assignRole(RolesSedeq::EVALUADOR_PROYECTO);

        foreach ([$evaluador, $this->supervisor()] as $actor) {
            $this->actingAs($actor)->get($this->url())
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf')
                ->assertHeader('X-Content-Type-Options', 'nosniff');
        }
    }

    public function test_el_revisor_no_puede(): void
    {
        $this->actingAs($this->revisor())->get($this->url())->assertForbidden();
    }

    public function test_un_solicitante_no_puede(): void
    {
        $this->actingAs(User::factory()->create())->get($this->url())->assertForbidden();
    }

    public function test_clave_desconocida_o_sin_archivo_es_404(): void
    {
        $evaluador = User::factory()->create()->assignRole(RolesSedeq::EVALUADOR_PROYECTO);

        $this->actingAs($evaluador)->get($this->url('otra'))->assertNotFound();
        $this->actingAs($evaluador)->get($this->url('programas_estudios'))->assertNotFound();
    }
}
