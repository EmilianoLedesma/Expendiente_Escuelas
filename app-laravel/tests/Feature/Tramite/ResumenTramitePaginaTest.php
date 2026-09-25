<?php

namespace Tests\Feature\Tramite;

use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use App\Models\TernaNombre;
use App\Models\User;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

class ResumenTramitePaginaTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    private function escuelaDe(Solicitante $solicitante): Escuela
    {
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'numero_ext' => '10', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    public function test_el_dueno_ve_el_resumen(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->escuelaDe($solicitante);

        $this->actingAs($solicitante->user)
            ->get(route('tramite.resumen', ['escuela' => $escuela->id]))
            ->assertOk()
            ->assertSee('Resumen del trámite')
            ->assertSee('Calle 1 #10, Centro, Querétaro, C.P. 76000')
            ->assertSee('Datos del plantel')
            ->assertSee('Responsable legal')
            ->assertSee('Comenzar')
            ->assertSee('href="'.route('tramite.paso2', ['escuela' => $escuela->id]).'"', false)
            ->assertSee('Completa primero: Responsable legal');
    }

    public function test_otro_solicitante_recibe_403(): void
    {
        $escuela = $this->escuelaDe(Solicitante::factory()->create());

        $this->actingAs(Solicitante::factory()->create()->user)
            ->get(route('tramite.resumen', ['escuela' => $escuela->id]))
            ->assertForbidden();
    }

    public function test_un_usuario_no_verificado_va_a_verificar_su_correo(): void
    {
        $solicitante = Solicitante::factory()->create(['user_id' => User::factory()->unverified()->create()->id]);
        $escuela = $this->escuelaDe($solicitante);

        $this->actingAs($solicitante->user)
            ->get(route('tramite.resumen', ['escuela' => $escuela->id]))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_un_visitante_va_a_login(): void
    {
        $escuela = $this->escuelaDe(Solicitante::factory()->create());

        $this->get(route('tramite.resumen', ['escuela' => $escuela->id]))->assertRedirect('/login');
    }

    public function test_preregistro_no_es_capturado_por_la_ruta_del_resumen(): void
    {
        $this->actingAs(Solicitante::factory()->create()->user);

        $this->get('/tramite/preregistro')->assertOk()->assertSeeLivewire('tramite.paso1-preregistro');
        $this->get('/tramite/abc')->assertNotFound();
    }

    public function test_muestra_filas_por_nivel_con_su_estado(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->escuelaDe($solicitante);
        $this->completarPaso2($escuela->id);
        $primaria = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);

        $this->actingAs($solicitante->user)
            ->get(route('tramite.resumen', ['escuela' => $escuela->id]))
            ->assertOk()
            ->assertSee('Primaria')
            ->assertSee('border-nivel-primaria', false)
            ->assertSee('href="'.route('tramite.paso3-inmueble', ['escuelaNivel' => $primaria->id]).'"', false)
            ->assertSee('Completa primero: Datos del inmueble')
            ->assertSee('No aplica para este nivel')
            ->assertSee('No disponible aún');
    }

    public function test_el_resumen_muestra_el_recorrido_y_el_siguiente_paso(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->escuelaDe($solicitante);
        TernaNombre::create(['escuela_id' => $escuela->id, 'numero_propuesta' => 1, 'nombre_propuesto' => 'Colegio Alfa']);

        $this->actingAs($solicitante->user)
            ->get(route('tramite.resumen', ['escuela' => $escuela->id]))
            ->assertOk()
            ->assertSee('Colegio Alfa')
            ->assertSee('Resumen del trámite · Nº '.str_pad((string) $escuela->id, 4, '0', STR_PAD_LEFT))
            ->assertSee('Secciones del trámite')
            ->assertSee('Siguiente paso')
            ->assertSee('aria-current="page"', false);
    }

    public function test_una_pagina_de_paso_muestra_el_recorrido_sin_livewire_antes_del_contenido(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->escuelaDe($solicitante);

        $html = $this->actingAs($solicitante->user)
            ->get(route('tramite.paso2', ['escuela' => $escuela->id]))
            ->assertOk()
            ->assertSee('Secciones del trámite')
            ->assertSee('aria-current="step"', false)
            ->assertSee('Aquí estás')
            ->getContent();

        $this->assertLessThan(strpos($html, 'wire:snapshot'), strpos($html, 'Secciones del trámite'));
        $this->assertLessThan(strpos($html, 'wire:snapshot'), strpos($html, 'id="contenido"'));
    }
}
