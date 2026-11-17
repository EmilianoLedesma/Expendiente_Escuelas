<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Revision\ConsultarRevisionProyecto;
use App\Application\Revision\ContenidoSeccionProyecto;
use App\Application\Revision\DecidirSeccionProyecto;
use App\Application\Revision\ListarColaProyecto;
use App\Application\Revision\RolesSedeq;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

/** Lectura de la revisión del proyecto educativo: cola, lista de las 20 secciones con su decisión y contenido de cada una. */
class RevisionProyectoEducativoTest extends TestCase
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

    private function media(string $estado = 'en_revision'): EscuelaNivel
    {
        return $this->nivelDe(Solicitante::factory()->create(), 'media_superior', $estado);
    }

    private function consultar(User $actor, EscuelaNivel $nivel): array
    {
        return app(ConsultarRevisionProyecto::class)->ejecutar($actor->id, $nivel->id);
    }

    private function contenido(User $actor, EscuelaNivel $nivel, string $slug): array
    {
        return app(ContenidoSeccionProyecto::class)->ejecutar($actor->id, $nivel->id, $slug);
    }

    // --- ConsultarRevisionProyecto

    public function test_lista_las_veinte_secciones_en_orden_con_su_clave_y_pendientes(): void
    {
        $nivel = $this->media();

        $r = $this->consultar($this->evaluador, $nivel);

        $this->assertCount(20, $r['secciones']);
        $this->assertSame(20, $r['total']);
        $this->assertSame(0, $r['revisadas']);
        $this->assertSame(['pe_01', 1, SeccionesProyectoEducativo::presentacion(), 'pendiente'], [$r['secciones'][0]['clave'], $r['secciones'][0]['numero'], $r['secciones'][0]['slug'], $r['secciones'][0]['estado']]);
        $this->assertSame('pe_20', $r['secciones'][19]['clave']);
        $this->assertSame(SeccionesProyectoEducativo::nombre(SeccionesProyectoEducativo::bibliografia()), $r['secciones'][19]['titulo']);
        $this->assertSame('en_revision', $r['estado']);
        $this->assertSame($nivel->escuela_id, $r['escuelaId']);
        $this->assertNotSame('', $r['nivel']);
    }

    public function test_refleja_las_decisiones_con_motivo_autor_y_conteo(): void
    {
        $nivel = $this->media();
        app(DecidirSeccionProyecto::class)->ejecutar($this->evaluador->id, $nivel->id, 'pe_01', 'aceptado');
        app(DecidirSeccionProyecto::class)->ejecutar($this->evaluador->id, $nivel->id, 'pe_03', 'rechazado', 'Falta el objetivo general.');

        $r = $this->consultar($this->evaluador, $nivel);

        $this->assertSame(2, $r['revisadas']);
        $this->assertSame('aceptado', $r['secciones'][0]['estado']);
        $this->assertSame('rechazado', $r['secciones'][2]['estado']);
        $this->assertSame('Falta el objetivo general.', $r['secciones'][2]['motivo']);
        $this->assertSame($this->evaluador->name, $r['secciones'][2]['por']);
        $this->assertSame('pendiente', $r['secciones'][1]['estado']);
    }

    public function test_el_evaluador_decide_solo_con_el_nivel_en_revision(): void
    {
        $this->assertTrue($this->consultar($this->evaluador, $this->media())['editable']);
        $this->assertFalse($this->consultar($this->evaluador, $this->media('con_observaciones'))['editable']);
        $this->assertFalse($this->consultar($this->evaluador, $this->media('aprobado'))['editable']);
    }

    public function test_el_supervisor_lee_pero_no_decide(): void
    {
        $r = $this->consultar($this->supervisor(), $this->media());

        $this->assertFalse($r['editable']);
        $this->assertCount(20, $r['secciones']);
    }

    public function test_el_revisor_en_captura_o_en_otro_nivel_se_rechaza(): void
    {
        $casos = [
            [$this->revisor(), $this->media()],
            [$this->evaluador, $this->media('en_captura')],
            [$this->evaluador, $this->nivelEnRevision()],
            [User::factory()->create(), $this->media()],
        ];

        foreach ($casos as [$actor, $nivel]) {
            try {
                $this->consultar($actor, $nivel);
                $this->fail('Debió lanzar AuthorizationException');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // --- ContenidoSeccionProyecto

    public function test_muestra_los_campos_de_una_seccion_de_texto(): void
    {
        $nivel = $this->media();
        DB::table('proyecto_educativo_objetivos')->insert(['escuela_nivel_id' => $nivel->id, 'objetivo_general' => 'Formar bachilleres.', 'objetivos_especificos' => 'Uno. Dos.']);

        $c = $this->contenido($this->evaluador, $nivel, SeccionesProyectoEducativo::objetivos());

        $this->assertSame('Objetivos Institucionales', $c['titulo']);
        $this->assertContains(['Objetivo General', 'Formar bachilleres.'], $c['filas']);
        $this->assertContains(['Objetivos Especificos', 'Uno. Dos.'], $c['filas']);
    }

    public function test_una_seccion_sin_captura_dice_sin_captura(): void
    {
        $nivel = $this->media();

        $c = $this->contenido($this->evaluador, $nivel, SeccionesProyectoEducativo::objetivos());

        $this->assertContains(['Objetivo General', 'Sin captura'], $c['filas']);
    }

    public function test_la_bibliografia_sale_como_lista_de_referencias(): void
    {
        $nivel = $this->media();
        DB::table('proyecto_educativo_bibliografia')->insert([
            ['escuela_nivel_id' => $nivel->id, 'orden' => 1, 'autores' => 'Pérez, J.', 'anio' => '2020', 'titulo' => 'Didáctica', 'fuente' => 'SEP', 'url' => null],
            ['escuela_nivel_id' => $nivel->id, 'orden' => 2, 'autores' => 'Soto, M.', 'anio' => '2021', 'titulo' => 'Evaluar', 'fuente' => 'UNAM', 'url' => 'https://ejemplo.mx'],
        ]);

        $c = $this->contenido($this->evaluador, $nivel, SeccionesProyectoEducativo::bibliografia());

        $this->assertCount(1, $c['listas']);
        $this->assertCount(2, $c['listas'][0]['filas']);
        $this->assertContains(['Autores', 'Pérez, J.'], $c['listas'][0]['filas'][0]);
        $this->assertContains(['Url', 'https://ejemplo.mx'], $c['listas'][0]['filas'][1]);
    }

    public function test_la_formacion_docente_junta_sus_dos_bloques(): void
    {
        $nivel = $this->media();

        $c = $this->contenido($this->evaluador, $nivel, SeccionesProyectoEducativo::formacion());

        $etiquetas = array_column($c['filas'], 0);
        $this->assertContains('Politica Inicial', $etiquetas);
        $this->assertContains('Politica Continua', $etiquetas);
    }

    public function test_cada_una_de_las_veinte_secciones_se_puede_leer_sin_captura(): void
    {
        $nivel = $this->media();

        foreach (array_keys(SeccionesProyectoEducativo::todas()) as $slug) {
            $c = $this->contenido($this->evaluador, $nivel, $slug);
            $this->assertSame(SeccionesProyectoEducativo::nombre($slug), $c['titulo'], $slug);
            $this->assertTrue($c['filas'] !== [] || $c['listas'] !== [], $slug);
        }
    }

    public function test_nunca_expone_rutas_de_disco(): void
    {
        $nivel = $this->media();
        $slug = SeccionesProyectoEducativo::mapaCurricular();
        DB::table('proyecto_educativo_mapa_curricular')->insert(['escuela_nivel_id' => $nivel->id, 'descripcion' => 'Mapa.', 'archivo_path' => 'secreto/ruta/mapa.pdf', 'archivo_nombre' => 'mapa.pdf', 'archivo_paginas' => 3]);

        $c = $this->contenido($this->evaluador, $nivel, $slug);

        $this->assertStringNotContainsString('secreto', json_encode($c));
        $this->assertContains(['Archivo Nombre', 'mapa.pdf'], $c['filas']);
    }

    public function test_contenido_rechaza_slug_desconocido_y_actores_sin_acceso(): void
    {
        $nivel = $this->media();

        try {
            $this->contenido($this->evaluador, $nivel, 'no-existe');
            $this->fail('Debió lanzar DatosInvalidos');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('seccion', $e->errores);
        }

        $this->expectException(AuthorizationException::class);
        $this->contenido($this->revisor(), $nivel, SeccionesProyectoEducativo::objetivos());
    }

    // --- ListarColaProyecto

    public function test_la_cola_trae_solo_niveles_de_media_superior_en_revision_con_su_avance(): void
    {
        $a = $this->media();
        $this->media('con_observaciones');
        $this->media('en_captura');
        $this->nivelEnRevision();
        app(DecidirSeccionProyecto::class)->ejecutar($this->evaluador->id, $a->id, 'pe_01', 'aceptado');

        $cola = app(ListarColaProyecto::class)->ejecutar($this->evaluador->id);

        $this->assertCount(1, $cola);
        $this->assertSame($a->id, $cola[0]['escuelaNivelId']);
        $this->assertSame($a->escuela_id, $cola[0]['escuelaId']);
        $this->assertSame(1, $cola[0]['revisadas']);
        $this->assertSame(20, $cola[0]['total']);
        $this->assertIsInt($cola[0]['diasEspera']);
        $this->assertNotSame('', $cola[0]['plantel']);
    }

    public function test_la_cola_es_del_evaluador_y_del_supervisor(): void
    {
        $this->media();

        $this->assertCount(1, app(ListarColaProyecto::class)->ejecutar($this->supervisor()->id));

        foreach ([$this->revisor(), User::factory()->create()] as $actor) {
            try {
                app(ListarColaProyecto::class)->ejecutar($actor->id);
                $this->fail('Debió rechazar al actor');
            } catch (PrecondicionIncumplida $e) {
                $this->assertSame('rol', $e->etapaFaltante);
            }
        }
    }
}
