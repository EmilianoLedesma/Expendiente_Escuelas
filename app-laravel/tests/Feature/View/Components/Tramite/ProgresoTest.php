<?php

namespace Tests\Feature\View\Components\Tramite;

use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProgresoTest extends TestCase
{
    use RefreshDatabase;

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

        $pendientesCount = substr_count($html, 'data-estado="pendiente"');
        $this->assertSame(5, $pendientesCount);
    }
}
