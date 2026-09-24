<?php

namespace Tests\Feature\View\Components\Tramite;

use App\Application\Preregistro\DTO\DatosPreregistro;
use App\Application\Preregistro\DTO\ResultadoPreregistro;
use App\Application\Preregistro\IniciarTramiteNuevo;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

class ProgresoTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    private function nuevoTramite(Solicitante $s): ResultadoPreregistro
    {
        return app(IniciarTramiteNuevo::class)->ejecutar(new DatosPreregistro(
            bifurcacion: 'nuevo',
            calle: 'Calle',
            colonia: 'Col',
            municipio: 'Querétaro',
            codigoPostal: '76000'
        ), $s->id);
    }

    public function test_renderiza_los_seis_pasos_en_orden(): void
    {
        (new PasosCapturaSeeder)->run();

        $html = (string) $this->blade('<x-tramite.progreso />');

        $pasos = DB::table('pasos_captura')->orderBy('orden')->pluck('nombre')->all();
        $posiciones = array_map(fn ($nombre) => strpos($html, $nombre), $pasos);

        $this->assertCount(6, $pasos);
        $this->assertSame($posiciones, collect($posiciones)->sort()->values()->all());
    }

    public function test_un_paso_completado_se_renderiza_distinto_de_uno_pendiente(): void
    {
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();

        $plantel = Plantel::create([
            'calle' => 'Calle 1',
            'colonia' => 'Centro',
            'municipio' => 'Querétaro',
            'codigo_postal' => '76000',
        ]);
        $solicitante = Solicitante::factory()->create();
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);

        $nivelId = DB::table('niveles_educativos')->orderBy('orden')->value('id');
        $estadoId = DB::table('estados_expediente')->orderBy('orden')->value('id');

        $escuelaNivelId = DB::table('escuela_niveles')->insertGetId([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => $nivelId,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);

        $pasoInmuebleId = DB::table('pasos_captura')->where('clave', 'inmueble')->value('id');

        DB::table('escuela_nivel_pasos')->insert([
            'escuela_nivel_id' => $escuelaNivelId,
            'paso_captura_id' => $pasoInmuebleId,
            'estado' => 'completado',
        ]);

        $html = (string) $this->blade(
            '<x-tramite.progreso :escuela-nivel-id="$id" />',
            ['id' => $escuelaNivelId]
        );

        $this->assertMatchesRegularExpression(
            '/data-estado="completado"[^>]*>\s*<span[^>]*bg-success/s',
            $html
        );

        // 5 de los 6 sub-pasos de Paso 3 + Preregistro/Responsable/Documentos: sin
        // escuelaId no hay contexto para evaluarlos como completados.
        $pendientesCount = substr_count($html, 'data-estado="pendiente"');
        $this->assertSame(8, $pendientesCount);
    }

    public function test_incluye_preregistro_responsable_y_documentos_antes_de_los_seis_de_paso3(): void
    {
        (new PasosCapturaSeeder)->run();

        $html = (string) $this->blade('<x-tramite.progreso />');

        $this->assertSame(9, substr_count($html, '<li'));
        $this->assertTrue(strpos($html, 'Preregistro') < strpos($html, 'Responsable legal'));
        $this->assertTrue(strpos($html, 'Responsable legal') < strpos($html, 'Documentos'));
        $this->assertTrue(strpos($html, 'Documentos') < strpos($html, 'Datos del inmueble'));
    }

    public function test_enlaza_preregistro_responsable_y_documentos_segun_el_contexto_disponible(): void
    {
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();
        (new TiposDocumentosSeeder)->run();

        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $solicitante = Solicitante::factory()->create();
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);

        DB::table('responsables_legales')->insert([
            'escuela_id' => $escuela->id,
            'tipo_persona' => 'fisica',
        ]);

        $sinEscuela = (string) $this->blade('<x-tramite.progreso />');
        $this->assertStringNotContainsString(route('tramite.paso2', ['escuela' => $escuela->id]), $sinEscuela);

        $conEscuela = (string) $this->blade('<x-tramite.progreso :escuela-id="$id" />', ['id' => $escuela->id]);
        $this->assertStringContainsString(route('tramite.paso2', ['escuela' => $escuela->id]), $conEscuela);
        $this->assertStringContainsString(route('tramite.paso2-documentos', ['escuela' => $escuela->id]), $conEscuela);
        $this->assertMatchesRegularExpression('/data-estado="completado"[^>]*>\s*<span[^>]*bg-success[^>]*><\/span>\s*<a[^>]*>\s*Responsable legal/s', $conEscuela);
    }

    /**
     * Antes de esto: el <a> de un dot clicable usaba la misma clase de color
     * (text-ink) que el texto plano de un dot no clicable — el enlace
     * existía en el DOM pero era visualmente indistinguible hasta el hover.
     */
    public function test_un_dot_clicable_se_distingue_visualmente_de_uno_de_solo_texto(): void
    {
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();

        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $solicitante = Solicitante::factory()->create();
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);

        $html = (string) $this->blade('<x-tramite.progreso :escuela-id="$id" />', ['id' => $escuela->id]);

        $this->assertMatchesRegularExpression('/<a href="[^"]*"\s+class="[^"]*text-primary/', $html);
    }

    // WS-2.2 — sin responsable, "Documentos" no debe enlazar: Paso2Documentos::mount()
    // redirige a Paso 2, y antes de WS-1 eso era un 404 (firstOrFail sin responsable).
    public function test_sin_responsable_documentos_no_es_enlace(): void
    {
        $this->seed(DatabaseSeeder::class);

        $s = Solicitante::factory()->create();
        $res = $this->nuevoTramite($s);

        $html = (string) $this->blade('<x-tramite.progreso :escuela-id="$id" />', ['id' => $res->escuelaId]);

        $this->assertStringNotContainsString(route('tramite.paso2-documentos', ['escuela' => $res->escuelaId]), $html);
    }

    // WS-2.2 — escuela_niveles existiendo (fila legacy) no basta para "completado":
    // el estado real depende de EstadoPaso2 (documentos completos y vigentes).
    public function test_con_escuela_niveles_pero_documentos_incompletos_no_es_completado(): void
    {
        $this->seed(DatabaseSeeder::class);

        $s = Solicitante::factory()->create();
        $res = $this->nuevoTramite($s);

        DB::table('responsables_legales')->insert([
            'escuela_id' => $res->escuelaId,
            'tipo_persona' => 'fisica',
        ]);

        $nivelId = DB::table('niveles_educativos')->orderBy('orden')->value('id');
        $estadoId = DB::table('estados_expediente')->orderBy('orden')->value('id');
        DB::table('escuela_niveles')->insert([
            'escuela_id' => $res->escuelaId,
            'nivel_educativo_id' => $nivelId,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);

        $html = (string) $this->blade('<x-tramite.progreso :escuela-id="$id" />', ['id' => $res->escuelaId]);

        $this->assertMatchesRegularExpression(
            '/Documentos.{0,200}/s',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/data-estado="completado"[^>]*>\s*<span[^>]*bg-success[^>]*><\/span>\s*<a[^>]*>\s*Documentos/s',
            $html
        );
    }

    // WS-2.2 — responsable + 6 documentos completos y vigentes: "Documentos" completado.
    public function test_con_documentos_completos_y_vigentes_documentos_es_completado(): void
    {
        $this->seed(DatabaseSeeder::class);

        $s = Solicitante::factory()->create();
        $res = $this->nuevoTramite($s);
        $this->completarPaso2($res->escuelaId);

        $html = (string) $this->blade('<x-tramite.progreso :escuela-id="$id" />', ['id' => $res->escuelaId]);

        $this->assertMatchesRegularExpression(
            '/data-estado="completado"[^>]*>\s*<span[^>]*bg-success[^>]*><\/span>\s*<a[^>]*>\s*Documentos/s',
            $html
        );
    }
}
