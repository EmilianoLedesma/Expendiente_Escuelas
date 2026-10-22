<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\Tramite\DTO\ResumenTramiteDTO;
use App\Application\Tramite\DTO\SeccionTramite;
use App\Application\Tramite\ResumenTramite;
use App\Models\EscuelaNivel;
use App\Models\Solicitante;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConTramiteDevuelto;
use Tests\TestCase;

/** El hub y «Mis trámites» frente a una devolución (HUD devolución, Task 9). */
class ResumenTramiteDevolucionTest extends TestCase
{
    use ConTramiteDevuelto;
    use RefreshDatabase;

    /** @return array{0: Solicitante, 1: EscuelaNivel, 2: EscuelaNivel} escuela con primaria y secundaria en revisión */
    private function escuela(): array
    {
        (new PasosCapturaSeeder)->run();
        $dueno = Solicitante::factory()->create();
        $primaria = $this->nivelDe($dueno, 'primaria', 'en_revision');
        $secundaria = $this->nivelEn($primaria->escuela, 'secundaria', 'en_revision');

        return [$dueno, $primaria, $secundaria];
    }

    /** @return array<string, SeccionTramite> */
    private function secciones(ResumenTramiteDTO $dto, int $nivelIndex): array
    {
        return collect($dto->niveles[$nivelIndex]->secciones)->keyBy('clave')->all();
    }

    public function test_resumen_marca_seccion_reabierta_con_motivo(): void
    {
        [$dueno, $primaria, $secundaria] = $this->escuela();
        $this->devolverTramite($primaria->escuela, [
            $primaria->id => ['inmueble' => 'Falta el croquis', 'plantel' => 'Teléfono inválido'],
            $secundaria->id => ['matricula' => 'Cifras inconsistentes'],
        ]);

        $dto = app(ResumenTramite::class)->paraEscuela($primaria->escuela_id, $dueno->user->id);

        $this->assertTrue($dto->conObservaciones);
        $this->assertTrue($dto->puedeReenviar);
        $generales = collect($dto->generales)->keyBy('clave');
        $this->assertSame('con_observaciones', $generales['plantel']->estado);
        $this->assertStringContainsString('Teléfono inválido', (string) $generales['plantel']->observacion);
        $this->assertTrue($generales['plantel']->editable);
        $this->assertNotNull($generales['plantel']->href);
        $this->assertNotNull($generales['plantel']->accion);

        $this->assertSame('con_observaciones', $this->secciones($dto, 0)['inmueble']->estado);
        $this->assertSame('Falta el croquis', $this->secciones($dto, 0)['inmueble']->observacion);
        $this->assertNotNull($this->secciones($dto, 0)['inmueble']->href);
        $this->assertSame('con_observaciones', $this->secciones($dto, 1)['matricula']->estado);
        $this->assertCount(3, $dto->observaciones);
    }

    public function test_resumen_deja_bloqueadas_las_aceptadas(): void
    {
        [$dueno, $primaria, $secundaria] = $this->escuela();
        $this->devolverTramite($primaria->escuela, [$primaria->id => ['inmueble' => 'Falta el croquis']], [$primaria->id => ['matricula'], $secundaria->id => ['inmueble', 'plantel']]);

        $dto = app(ResumenTramite::class)->paraEscuela($primaria->escuela_id, $dueno->user->id);

        $matricula = $this->secciones($dto, 0)['matricula'];
        $this->assertNull($matricula->href);
        $this->assertNull($matricula->accion);
        $this->assertFalse($matricula->editable);
        $this->assertNull($matricula->observacion);
        $this->assertNotSame('con_observaciones', $matricula->estado);
        // El inmueble de la secundaria fue aceptado: la única reabierta de ese nombre es la de primaria.
        $this->assertNull($this->secciones($dto, 1)['inmueble']->href);
        foreach ($dto->generales as $g) {
            $this->assertNull($g->href, "{$g->clave} aceptado queda bloqueado");
            $this->assertFalse($g->editable);
        }
    }

    public function test_ronda_abierta_se_ve_como_enviado_sin_filtrar_nada(): void
    {
        [$dueno, $primaria] = $this->escuela();
        // Secundaria sigue en revisión: ronda abierta.
        $this->devolverTramite($primaria->escuela, [$primaria->id => ['inmueble' => 'Falta el croquis']]);

        $dto = app(ResumenTramite::class)->paraEscuela($primaria->escuela_id, $dueno->user->id);

        $this->assertFalse($dto->conObservaciones);
        $this->assertFalse($dto->puedeReenviar);
        $this->assertTrue($dto->enviado);
        $this->assertSame([], $dto->observaciones);
        foreach ([...$dto->generales, ...$dto->niveles[0]->secciones] as $s) {
            $this->assertNotSame('con_observaciones', $s->estado);
            $this->assertNull($s->href);
            $this->assertNull($s->observacion);
        }
    }

    public function test_el_responsable_solo_ve_reabiertos_los_pasos_de_su_nivel(): void
    {
        [, $primaria, $secundaria] = $this->escuela();
        $this->devolverTramite($primaria->escuela, [
            $primaria->id => ['matricula' => 'Cifras mal', 'plantel' => 'x'],
            $secundaria->id => ['plan_estudios' => 'Cifras inconsistentes'],
        ]);
        $responsable = $this->responsableDe($primaria);

        $dto = app(ResumenTramite::class)->paraEscuela($primaria->escuela_id, $responsable->id);

        $this->assertTrue($dto->conObservaciones);
        $this->assertFalse($dto->puedeReenviar, 'solo el dueño reenvía');
        $this->assertCount(1, $dto->niveles);
        $matricula = $this->secciones($dto, 0)['matricula'];
        $this->assertSame('con_observaciones', $matricula->estado);
        $this->assertNotNull($matricula->href);
        $this->assertTrue($matricula->editable);
        $this->assertSame(['matricula'], array_column($dto->observaciones, 'paso'));
    }

    public function test_responsable_lee_el_motivo_de_inmueble_pero_no_puede_corregirlo(): void
    {
        [, $primaria, $secundaria] = $this->escuela();
        $this->devolverTramite($primaria->escuela, [
            $primaria->id => ['inmueble' => 'Falta el croquis', 'infraestructura' => 'Faltan aulas'],
            $secundaria->id => ['matricula' => 'x'],
        ]);
        $responsable = $this->responsableDe($primaria);

        $dto = app(ResumenTramite::class)->paraEscuela($primaria->escuela_id, $responsable->id);

        foreach (['inmueble' => 'Falta el croquis', 'infraestructura' => 'Faltan aulas'] as $clave => $motivo) {
            $s = $this->secciones($dto, 0)[$clave];
            $this->assertSame('con_observaciones', $s->estado);
            $this->assertSame($motivo, $s->observacion);
            $this->assertNull($s->accion, $clave);
            $this->assertNull($s->href, $clave);
            $this->assertFalse($s->editable, $clave);
            $this->assertStringContainsString('titular', (string) $s->motivoBloqueo);
        }
    }

    public function test_el_dueno_si_corrige_inmueble(): void
    {
        [$dueno, $primaria, $secundaria] = $this->escuela();
        $this->devolverTramite($primaria->escuela, [$primaria->id => ['inmueble' => 'Falta el croquis'], $secundaria->id => ['matricula' => 'x']]);

        $s = $this->secciones(app(ResumenTramite::class)->paraEscuela($primaria->escuela_id, $dueno->user->id), 0)['inmueble'];

        $this->assertTrue($s->editable);
        $this->assertNotNull($s->href);
    }

    public function test_el_banner_del_dueno_trae_el_reenvio_y_el_del_responsable_no(): void
    {
        [$dueno, $primaria, $secundaria] = $this->escuela();
        $this->devolverTramite($primaria->escuela, [$primaria->id => ['inmueble' => 'Falta el croquis'], $secundaria->id => ['matricula' => 'x']]);
        $url = route('tramite.resumen', ['escuela' => $primaria->escuela_id]);

        $this->actingAs($dueno->user)->get($url)->assertOk()
            ->assertSee('corrige solo los pasos marcados y vuelve a enviar')->assertSee('Reenviar correcciones');

        $this->actingAs($this->responsableDe($primaria))->get($url)->assertOk()
            ->assertSee('El titular del trámite corregirá y reenviará')->assertDontSee('vuelve a enviar')->assertDontSee('Reenviar correcciones');
    }

    public function test_el_responsable_sin_observaciones_propias_no_ve_lista_vacia(): void
    {
        [, $primaria, $secundaria] = $this->escuela();
        $this->devolverTramite($primaria->escuela, [$primaria->id => ['plantel' => 'x'], $secundaria->id => ['matricula' => 'y']]);

        $this->actingAs($this->responsableDe($primaria))->get(route('tramite.resumen', ['escuela' => $primaria->escuela_id]))
            ->assertOk()->assertSee('El titular del trámite corregirá')->assertDontSee('<ul class="mt-xs list-disc', false);
    }

    public function test_mis_tramites_muestra_con_observaciones_solo_en_ronda_cerrada(): void
    {
        [$dueno, $primaria, $secundaria] = $this->escuela();
        $this->devolverTramite($primaria->escuela, [$primaria->id => ['inmueble' => 'Falta el croquis']]);

        $this->actingAs($dueno->user)->get(route('tramite.index'))
            ->assertOk()->assertSee('En revisión por SEDEQ')->assertDontSee('Con observaciones');

        $this->devolverTramite($primaria->escuela, [$secundaria->id => ['matricula' => 'x']]);

        $this->actingAs($dueno->user)->get(route('tramite.index'))
            ->assertOk()->assertSee('Con observaciones')->assertDontSee('En revisión por SEDEQ');
    }

    public function test_la_pagina_del_hub_muestra_el_banner_con_los_motivos_solo_en_ronda_cerrada(): void
    {
        [$dueno, $primaria, $secundaria] = $this->escuela();
        $this->devolverTramite($primaria->escuela, [$primaria->id => ['inmueble' => 'Falta el croquis']]);

        $this->actingAs($dueno->user)->get(route('tramite.resumen', ['escuela' => $primaria->escuela_id]))
            ->assertOk()->assertDontSee('Falta el croquis')->assertDontSee('Tu trámite tiene observaciones');

        $this->devolverTramite($primaria->escuela, [$secundaria->id => ['matricula' => 'Cifras inconsistentes']]);

        $this->actingAs($dueno->user)->get(route('tramite.resumen', ['escuela' => $primaria->escuela_id]))
            ->assertOk()->assertSee('Tu trámite tiene observaciones')->assertSee('Falta el croquis')->assertSee('Cifras inconsistentes');
    }
}
