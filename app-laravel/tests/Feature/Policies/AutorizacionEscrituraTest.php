<?php

namespace Tests\Feature\Policies;

use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * WS-1.4: páginas que escriben se autorizan con `update` (solo dueño); `view`
 * queda para lectura pura y podrá abrirse a revisores SEDEQ sin abrir escrituras.
 */
class AutorizacionEscrituraTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuelaNivel(Solicitante $dueno): EscuelaNivel
    {
        (new CatalogoMinimoSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $dueno->id]);

        return EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
    }

    public function test_update_es_solo_para_el_dueno_en_ambas_policies(): void
    {
        $dueno = Solicitante::factory()->create();
        $otro = Solicitante::factory()->create();
        $escuelaNivel = $this->crearEscuelaNivel($dueno);
        $escuela = Escuela::findOrFail($escuelaNivel->escuela_id);

        $this->assertTrue(Gate::forUser($dueno->user)->allows('update', $escuela));
        $this->assertFalse(Gate::forUser($otro->user)->allows('update', $escuela));
        $this->assertTrue(Gate::forUser($dueno->user)->allows('update', $escuelaNivel));
        $this->assertFalse(Gate::forUser($otro->user)->allows('update', $escuelaNivel));
    }

    /** @return array<string, array{string, string}> */
    public static function rutas(): array
    {
        return [
            'paso2 responsable' => ['tramite.paso2', 'can:update,escuela'],
            'paso2 documentos' => ['tramite.paso2-documentos', 'can:update,escuela'],
            'paso2.4 documentos por nivel' => ['tramite.paso2-nivel-documentos', 'can:update,escuelaNivel'],
            'paso3 inmueble' => ['tramite.paso3-inmueble', 'can:update,escuelaNivel'],
            'paso3 infraestructura' => ['tramite.paso3-infraestructura', 'can:update,escuelaNivel'],
            'paso3 mobiliario' => ['tramite.paso3-mobiliario', 'can:update,escuelaNivel'],
            'paso3 plan de estudios' => ['tramite.paso3-plan-estudios', 'can:update,escuelaNivel'],
            'paso3 plantilla docente' => ['tramite.paso3-plantilla', 'can:update,escuelaNivel'],
            'paso3 matricula' => ['tramite.paso3-matricula', 'can:update,escuelaNivel'],
            'validacion final' => ['tramite.validacion', 'can:update,escuela'],
            'reporte de validacion (solo lectura)' => ['tramite.validacion.reporte', 'can:view,escuela'],
            'paso3 proximos pasos (solo lectura)' => ['tramite.paso3-proximos-pasos', 'can:view,escuelaNivel'],
            'descarga documento (solo lectura)' => ['tramite.paso2-documentos.descargar', 'can:view,escuela'],
            'formato solicitud pdf por nivel (solo lectura)' => ['tramite.paso2-nivel-documentos.formato-solicitud', 'can:view,escuelaNivel'],
            'descarga documento por nivel (solo lectura)' => ['tramite.paso2-nivel-documentos.descargar', 'can:view,escuelaNivel'],
            'resumen del trámite (solo lectura)' => ['tramite.resumen', 'can:view,escuela'],
        ];
    }

    #[DataProvider('rutas')]
    public function test_cada_ruta_usa_la_habilidad_correcta(string $nombre, string $middleware): void
    {
        $this->assertContains($middleware, Route::getRoutes()->getByName($nombre)->gatherMiddleware());
    }
}
