<?php

namespace Tests\Feature\Policies;

use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use Database\Seeders\CatalogoMinimoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\TestCase;

/**
 * WS-7a §5.2: after the send every page that writes (and every Livewire action on it,
 * since Livewire re-applies the route's can: middleware) answers 403 — for the owner and
 * for a responsable del nivel (ADR-015); the Resumen and the downloads stay on `view`.
 */
class TramiteEnviadoPolicyTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use ConNivelParaResponsables;
    use RefreshDatabase;

    private function cambiarEstado(Escuela $escuela, string $clave): void
    {
        EscuelaNivel::where('escuela_id', $escuela->id)->update(['estado_id' => DB::table('estados_expediente')->where('clave', $clave)->value('id')]);
    }

    public function test_update_solo_mientras_todo_esta_en_captura_en_ambas_policies(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $dueno = $escuela->solicitante->user;

        $this->assertTrue($dueno->can('update', $escuela));
        $this->assertTrue($dueno->can('update', $nivel));

        $this->cambiarEstado($escuela, 'en_revision');

        $this->assertFalse($dueno->can('update', $escuela));
        $this->assertFalse($dueno->can('update', $nivel->fresh()));
        $this->assertTrue($dueno->can('view', $escuela));
        $this->assertTrue($dueno->can('view', $nivel->fresh()));
        $this->assertTrue($dueno->can('delete', $escuela), 'delete sigue siendo de la policy; EliminarTramite rechaza lo enviado.');
    }

    public function test_tras_el_envio_el_responsable_pierde_update_y_el_dueno_inmueble_y_responsables(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $dueno = $escuela->solicitante->user;
        $responsable = $this->responsableDe($nivel);

        $this->assertTrue($responsable->can('update', $nivel));
        $this->assertTrue($dueno->can('updateInmueble', $nivel));
        $this->assertTrue($dueno->can('gestionarResponsables', $nivel));

        $this->cambiarEstado($escuela, 'en_revision');
        $nivel = $nivel->fresh();

        $this->assertFalse($responsable->can('update', $nivel));
        $this->assertFalse($dueno->can('updateInmueble', $nivel));
        $this->assertFalse($dueno->can('gestionarResponsables', $nivel), 'un nivel enviado no toma ni pierde responsables');
        $this->assertTrue($responsable->can('view', $nivel));
        $this->assertTrue($responsable->can('verResumen', $escuela));
    }

    public function test_sin_niveles_el_dueno_sigue_pudiendo_escribir(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        (new CatalogoMinimoSeeder)->run();

        $this->assertTrue($escuela->solicitante->user->can('update', $escuela));
    }

    public function test_un_estado_mezclado_no_permite_escribir(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'secundaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);

        $this->assertFalse($escuela->solicitante->user->can('update', $escuela));
        $this->assertFalse($escuela->solicitante->user->can('update', $nivel));
    }

    /** @return array<string, array{string, string}> */
    public static function rutasQueEscriben(): array
    {
        return [
            'paso2 responsable' => ['tramite.paso2', 'escuela'],
            'paso2 documentos' => ['tramite.paso2-documentos', 'escuela'],
            'validacion final' => ['tramite.validacion', 'escuela'],
            'paso2.4 documentos por nivel' => ['tramite.paso2-nivel-documentos', 'escuelaNivel'],
            'paso3 inmueble' => ['tramite.paso3-inmueble', 'escuelaNivel'],
            'paso3 infraestructura' => ['tramite.paso3-infraestructura', 'escuelaNivel'],
            'paso3 mobiliario' => ['tramite.paso3-mobiliario', 'escuelaNivel'],
            'paso3 plan de estudios' => ['tramite.paso3-plan-estudios', 'escuelaNivel'],
            'paso3 plantilla docente' => ['tramite.paso3-plantilla', 'escuelaNivel'],
            'paso3 matricula' => ['tramite.paso3-matricula', 'escuelaNivel'],
        ];
    }

    #[DataProvider('rutasQueEscriben')]
    public function test_tras_el_envio_cada_pagina_que_escribe_responde_403(string $ruta, string $parametro): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $url = route($ruta, [$parametro => $parametro === 'escuela' ? $escuela->id : $nivel->id]);
        $dueno = $escuela->solicitante->user;

        // Non-vacuity: the same page is reachable (200 or a wizard redirect) while en_captura.
        $this->assertNotSame(403, $this->actingAs($dueno)->get($url)->status());

        $this->cambiarEstado($escuela, 'en_revision');

        $this->actingAs($dueno)->get($url)->assertForbidden();
    }

    public function test_tras_el_envio_el_responsable_recibe_403_en_las_paginas_de_su_nivel(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $responsable = $this->responsableDe($nivel);
        $urls = collect(self::rutasQueEscriben())
            ->filter(fn (array $r) => $r[1] === 'escuelaNivel')
            ->map(fn (array $r) => route($r[0], ['escuelaNivel' => $nivel->id]));

        foreach ($urls as $url) {
            $this->assertNotSame(403, $this->actingAs($responsable)->get($url)->status(), "en captura: {$url}");
        }

        $this->cambiarEstado($escuela, 'en_revision');

        foreach ($urls as $url) {
            $this->assertSame(403, $this->actingAs($responsable)->get($url)->status(), "enviado: {$url}");
        }
        $this->assertNotSame(403, $this->actingAs($responsable)->get(route('tramite.resumen', ['escuela' => $escuela->id]))->status());
    }

    public function test_tras_el_envio_el_resumen_y_las_descargas_siguen_disponibles(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $this->cambiarEstado($escuela, 'en_revision');
        $dueno = $escuela->solicitante->user;

        $this->actingAs($dueno)->get(route('tramite.resumen', ['escuela' => $escuela->id]))->assertOk();
        $this->actingAs($dueno)->get(route('tramite.paso2-documentos.descargar', ['escuela' => $escuela->id, 'clave' => 'ine']))->assertOk();
    }
}
