<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Revision\ArchivoRevisionProyecto;
use App\Application\Revision\ContenidoSeccionProyecto;
use App\Application\Revision\RolesSedeq;
use App\Models\EscuelaNivel;
use App\Models\ProyectoEducativoFormacionPolitica;
use App\Models\ProyectoEducativoMapaCurricular;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

/** Descarga para revisión de los PDF del proyecto educativo: solo evaluador y supervisor, nunca la ruta de disco. */
class ArchivoRevisionProyectoTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private User $evaluador;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolesSeeder)->run();
        $this->evaluador = User::factory()->create()->assignRole(RolesSedeq::EVALUADOR_PROYECTO);
    }

    private function media(): EscuelaNivel
    {
        return $this->nivelDe(Solicitante::factory()->create(), 'media_superior', 'en_revision');
    }

    private function conMapa(EscuelaNivel $nivel): void
    {
        ProyectoEducativoMapaCurricular::create(['escuela_nivel_id' => $nivel->id, 'descripcion' => 'x', 'archivo_path' => 'proyecto_educativo/1/mapa.pdf', 'archivo_nombre' => 'mapa.pdf', 'archivo_paginas' => 2]);
    }

    public function test_el_evaluador_y_el_supervisor_obtienen_la_ruta(): void
    {
        $nivel = $this->media();
        $this->conMapa($nivel);

        foreach ([$this->evaluador, $this->supervisor()] as $actor) {
            $this->assertSame('proyecto_educativo/1/mapa.pdf', app(ArchivoRevisionProyecto::class)->ruta($actor->id, $nivel->id, 'mapa_curricular'));
        }
    }

    public function test_el_revisor_se_rechaza(): void
    {
        $nivel = $this->media();
        $this->conMapa($nivel);

        $this->expectException(AuthorizationException::class);
        app(ArchivoRevisionProyecto::class)->ruta($this->revisor()->id, $nivel->id, 'mapa_curricular');
    }

    public function test_sin_archivo_guardado_devuelve_null(): void
    {
        $this->assertNull(app(ArchivoRevisionProyecto::class)->ruta($this->evaluador->id, $this->media()->id, 'mapa_curricular'));
    }

    public function test_clave_desconocida_es_dato_invalido(): void
    {
        $this->expectException(DatosInvalidos::class);
        app(ArchivoRevisionProyecto::class)->ruta($this->evaluador->id, $this->media()->id, 'otra');
    }

    public function test_la_planeacion_de_formacion_sale_de_la_fila_continua(): void
    {
        $nivel = $this->media();
        ProyectoEducativoFormacionPolitica::create(['escuela_nivel_id' => $nivel->id, 'tipo' => 'inicial', 'politica' => 'p']);
        ProyectoEducativoFormacionPolitica::create(['escuela_nivel_id' => $nivel->id, 'tipo' => 'continua', 'planeacion_path' => 'x/continua.pdf', 'planeacion_nombre' => 'c.pdf', 'planeacion_paginas' => 1]);

        $this->assertSame('x/continua.pdf', app(ArchivoRevisionProyecto::class)->ruta($this->evaluador->id, $nivel->id, 'formacion_planeacion'));
    }

    public function test_el_contenido_ofrece_descargas_solo_de_los_archivos_guardados_sin_rutas(): void
    {
        $nivel = $this->media();
        $this->conMapa($nivel);

        $c = app(ContenidoSeccionProyecto::class)->ejecutar($this->evaluador->id, $nivel->id, SeccionesProyectoEducativo::mapaCurricular());
        $this->assertSame([['clave' => 'mapa_curricular', 'etiqueta' => 'Mapa curricular']], $c['descargas']);
        $this->assertStringNotContainsString('proyecto_educativo/', json_encode($c));

        $vacia = app(ContenidoSeccionProyecto::class)->ejecutar($this->evaluador->id, $nivel->id, SeccionesProyectoEducativo::programasEstudios());
        $this->assertSame([], $vacia['descargas']);
    }
}
