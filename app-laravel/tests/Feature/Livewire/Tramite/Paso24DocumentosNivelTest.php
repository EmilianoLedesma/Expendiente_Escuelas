<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\Documentos\DocumentosNivelCompletos;
use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\EscuelaNiveles\RegistrarDatosNivel;
use App\Livewire\Tramite\Paso24DocumentosNivel;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoEscuelaNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CompletaPaso2;
use Tests\Concerns\CompletaPaso24;
use Tests\TestCase;

class Paso24DocumentosNivelTest extends TestCase
{
    use CompletaPaso2;
    use CompletaPaso24;
    use RefreshDatabase;

    /** Nivel con Paso 2 completo y el dueño autenticado. */
    private function nivel(string $claveNivel = 'primaria', string $tipoPersona = 'fisica'): EscuelaNivel
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $this->completarPaso2($escuela->id, $tipoPersona);
        $this->actingAs($solicitante->user);

        return EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', $claveNivel)->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
    }

    private function conDatos(EscuelaNivel $escuelaNivel): void
    {
        app(RegistrarDatosNivel::class)->ejecutar($escuelaNivel->id, 'matutino', 'mixto');
    }

    private function pdf(string $nombre = 'd.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nombre, 10, 'application/pdf');
    }

    public function test_el_dueno_ve_la_pagina(): void
    {
        $escuelaNivel = $this->nivel();

        $this->get(route('tramite.paso2-nivel-documentos', ['escuelaNivel' => $escuelaNivel->id]))
            ->assertOk()
            ->assertSeeLivewire('tramite.paso24-documentos-nivel')
            ->assertSee('Documentos del nivel');
    }

    public function test_el_recorrido_marca_documentos_del_nivel_como_la_seccion_actual(): void
    {
        $escuelaNivel = $this->nivel();

        $this->get(route('tramite.paso2-nivel-documentos', ['escuelaNivel' => $escuelaNivel->id]))
            ->assertOk()
            ->assertSee('Primaria · Paso 5 de 10')
            ->assertSee('aria-current="step"', false)
            ->assertSee('Aquí estás');
    }

    public function test_muestra_una_fila_por_documento_aplicable_al_nivel(): void
    {
        $escuelaNivel = $this->nivel('secundaria');

        $componente = Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel]);

        foreach (['formato_solicitud', 'recibo_pago_derechos', 'acervo_bibliografico_secundaria', 'inventario_laboratorio'] as $clave) {
            $componente->assertSeeHtml('data-clave="'.$clave.'"');
        }
        $componente->assertDontSeeHtml('data-clave="acervo_bibliografico_primaria"')
            ->assertSee('0 de 4 documentos completos');
    }

    /** @return array<string, array{string}> */
    public static function tiposPersona(): array
    {
        return ['fisica' => ['fisica'], 'moral' => ['moral'], 'fisica_con_gestor' => ['fisica_con_gestor']];
    }

    /** WS-5a M1, sobre la página nueva. */
    #[DataProvider('tiposPersona')]
    public function test_toda_clave_aplicable_renderiza_su_fila(string $tipoPersona): void
    {
        $escuelaNivel = $this->nivel('secundaria', $tipoPersona);

        $componente = Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel]);

        foreach (app(DocumentosNivelCompletos::class)->clavesAplicables($escuelaNivel->id) as $clave) {
            $componente->assertSeeHtml('data-clave="'.$clave.'"');
        }
    }

    public function test_una_clave_nueva_del_catalogo_sin_bloque_propio_se_puede_subir(): void
    {
        $escuelaNivel = $this->nivel();
        DB::table('tipos_documentos')->insert([
            'clave' => 'documento_nivel_de_prueba', 'nombre' => 'Documento del nivel de prueba',
            'aplica_persona' => 'ambas', 'ambito' => 'escuela_nivel', 'created_at' => now(), 'updated_at' => now(),
        ]);

        Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->assertSeeHtml('data-clave="documento_nivel_de_prueba"')
            ->set('archivos.documento_nivel_de_prueba', $this->pdf())
            ->call('guardarDocumento', 'documento_nivel_de_prueba')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('documentos_escuela_nivel', 1);
    }

    public function test_guardar_datos_del_nivel_persiste_turno_y_tipo_de_alumnado(): void
    {
        $escuelaNivel = $this->nivel();

        Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->set('turno', 'vespertino')
            ->set('tipoAlumnado', 'femenino')
            ->call('guardarDatos')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('escuela_niveles', ['id' => $escuelaNivel->id, 'turno' => 'vespertino', 'tipo_alumnado' => 'femenino']);
    }

    public function test_un_turno_fuera_del_catalogo_falla_validacion(): void
    {
        $escuelaNivel = $this->nivel();

        Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->set('turno', 'nocturno')
            ->set('tipoAlumnado', 'mixto')
            ->call('guardarDatos')
            ->assertHasErrors('turno');

        $this->assertNull($escuelaNivel->fresh()->turno);
    }

    public function test_el_formato_solo_se_puede_generar_con_datos_capturados(): void
    {
        $escuelaNivel = $this->nivel();
        $rutaPdf = route('tramite.paso2-nivel-documentos.formato-solicitud', ['escuelaNivel' => $escuelaNivel->id]);

        Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->assertDontSee($rutaPdf, false)
            ->assertSee('Guarda el turno y el tipo de alumnado para generar el Formato de Solicitud.')
            ->set('turno', 'matutino')
            ->set('tipoAlumnado', 'mixto')
            ->call('guardarDatos')
            ->assertSee($rutaPdf, false);
    }

    public function test_sube_el_formato_firmado_despues_de_capturar_datos(): void
    {
        $escuelaNivel = $this->nivel();
        $this->conDatos($escuelaNivel);

        Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->set('archivos.formato_solicitud', $this->pdf('firmado.pdf'))
            ->call('guardarDocumento', 'formato_solicitud')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('documentos_escuela_nivel', ['escuela_nivel_id' => $escuelaNivel->id]);
    }

    public function test_subir_el_formato_sin_datos_muestra_el_error_en_turno(): void
    {
        $escuelaNivel = $this->nivel();

        Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->set('archivos.formato_solicitud', $this->pdf('firmado.pdf'))
            ->call('guardarDocumento', 'formato_solicitud')
            ->assertHasErrors('turno')
            ->assertNoRedirect();

        $this->assertDatabaseCount('documentos_escuela_nivel', 0);
    }

    public function test_sube_el_recibo_con_sus_datos(): void
    {
        $escuelaNivel = $this->nivel();

        Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->set('archivos.recibo_pago_derechos', $this->pdf('recibo.pdf'))
            ->set('recibo.folio', 'F-900')
            ->set('recibo.monto', '1800.00')
            ->set('recibo.fechaPago', now()->toDateString())
            ->call('guardarRecibo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('recibos_pago_derechos', ['folio' => 'F-900', 'monto' => '1800.00']);
    }

    public function test_el_recibo_sin_folio_monto_ni_fecha_falla_validacion(): void
    {
        $escuelaNivel = $this->nivel();

        Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->set('archivos.recibo_pago_derechos', $this->pdf('recibo.pdf'))
            ->call('guardarRecibo')
            ->assertHasErrors(['recibo.folio', 'recibo.monto', 'recibo.fechaPago']);

        $this->assertDatabaseCount('documentos_escuela_nivel', 0);
    }

    public function test_guardar_documento_rechaza_una_clave_que_no_aplica_al_nivel(): void
    {
        $escuelaNivel = $this->nivel('primaria');

        Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->set('archivos.inventario_laboratorio', $this->pdf())
            ->call('guardarDocumento', 'inventario_laboratorio')
            ->assertStatus(403);
    }

    /** O2: el recibo lleva datos estructurados; la vía genérica no lo acepta. */
    public function test_guardar_documento_rechaza_el_recibo_por_la_via_generica(): void
    {
        $escuelaNivel = $this->nivel();

        Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->set('archivos.recibo_pago_derechos', $this->pdf())
            ->call('guardarDocumento', 'recibo_pago_derechos')
            ->assertStatus(403);

        $this->assertDatabaseCount('recibos_pago_derechos', 0);
    }

    /** Review Focus 2. */
    public function test_un_no_dueno_recibe_403(): void
    {
        $escuelaNivel = $this->nivel();
        $this->actingAs(Solicitante::factory()->create()->user);

        $this->get(route('tramite.paso2-nivel-documentos', ['escuelaNivel' => $escuelaNivel->id]))->assertForbidden();
    }

    public function test_con_paso2_incompleto_redirige_a_paso2(): void
    {
        (new TiposDocumentosSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);

        $this->actingAs($solicitante->user)
            ->get(route('tramite.paso2-nivel-documentos', ['escuelaNivel' => $escuelaNivel->id]))
            ->assertRedirect(route('tramite.paso2', ['escuela' => $escuela->id]));
    }

    public function test_con_todo_completo_ofrece_continuar_a_paso3_y_no_redirige(): void
    {
        $escuelaNivel = $this->nivel();
        $this->completarPaso24($escuelaNivel->id);

        Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->assertNoRedirect()
            ->assertSee('Continuar a Datos del inmueble')
            ->assertSee(route('tramite.paso3-inmueble', ['escuelaNivel' => $escuelaNivel->id]), false);
    }

    /** Migrado de Paso2DocumentosTest (WS-5b): el enlace para regenerar sigue visible con el Formato ya subido. */
    public function test_el_formato_capturado_sigue_mostrando_el_enlace_para_reemplazarlo(): void
    {
        $escuelaNivel = $this->nivel();
        $this->conDatos($escuelaNivel);
        app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'formato_solicitud', $this->pdf('firmado.pdf'), new DatosDocumento, $escuelaNivel->id);
        $nombreArchivo = basename((string) DocumentoEscuelaNivel::where('escuela_nivel_id', $escuelaNivel->id)->value('archivo_path'));

        Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->assertSee($nombreArchivo)
            ->assertSee('Reemplazar')
            ->assertSee('Generar y descargar Formato de Solicitud')
            ->assertSee(route('tramite.paso2-nivel-documentos.descargar', ['escuelaNivel' => $escuelaNivel->id, 'clave' => 'formato_solicitud']), false);
    }

    /** Decisión del dueño: con Formato subido, la página avisa y pide confirmar antes de cambiar los datos; sin Formato, no (control). */
    public function test_con_formato_subido_avisa_que_cambiar_los_datos_lo_descarta(): void
    {
        $aviso = 'se descartará el Formato de Solicitud firmado que ya subiste';
        $escuelaNivel = $this->nivel();
        $this->conDatos($escuelaNivel);

        Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->assertDontSee($aviso);

        app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'formato_solicitud', $this->pdf('firmado.pdf'), new DatosDocumento, $escuelaNivel->id);

        Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->assertSee($aviso)
            ->assertSeeHtml('wire:confirm=');
    }

    public function test_cambiar_el_turno_desde_la_pagina_descarta_el_formato_subido(): void
    {
        $escuelaNivel = $this->nivel();
        $this->conDatos($escuelaNivel);
        app(RegistrarDocumento::class)->ejecutar($escuelaNivel->escuela_id, 'formato_solicitud', $this->pdf('firmado.pdf'), new DatosDocumento, $escuelaNivel->id);

        Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel->fresh()])
            ->set('turno', 'vespertino')
            ->call('guardarDatos')
            ->assertHasNoErrors()
            ->assertDontSee('se descartará el Formato de Solicitud firmado que ya subiste');

        $this->assertDatabaseCount('documentos_escuela_nivel', 0);
    }

    /** Revisión M2: una fila vencida se marca como en Paso 2.2. */
    public function test_una_fila_vencida_muestra_el_aviso_de_vigencia(): void
    {
        $escuelaNivel = $this->nivel();
        $tipoId = DB::table('tipos_documentos')->insertGetId([
            'clave' => 'documento_nivel_con_vigencia', 'nombre' => 'Documento con vigencia', 'aplica_persona' => 'ambas',
            'ambito' => 'escuela_nivel', 'vigencia_max_dias' => 30, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DocumentoEscuelaNivel::create([
            'escuela_nivel_id' => $escuelaNivel->id, 'tipo_documento_id' => $tipoId,
            'archivo_path' => 'x.pdf', 'fecha_vigencia' => now()->subDay()->toDateString(),
        ]);

        Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->assertSee('Documento con vigencia: ha superado su vigencia máxima, debe resubirse.');
    }

    /**
     * Revisión M1, lado 2.4: la vía genérica (guardarDocumento) no captura fecha
     * de emisión; ninguna clave por nivel fuera de CON_DATOS_ESTRUCTURADOS puede
     * tener vigencia_max_dias. Hoy ninguna la tiene: la prueba es una guarda.
     */
    public function test_ninguna_clave_por_nivel_de_la_via_generica_tiene_vigencia(): void
    {
        (new TiposDocumentosSeeder)->run();

        $conVigencia = DB::table('tipos_documentos')->where('ambito', 'escuela_nivel')->whereNotNull('vigencia_max_dias')->pluck('clave')->all();

        $this->assertSame([], array_values(array_diff($conVigencia, Paso24DocumentosNivel::CON_DATOS_ESTRUCTURADOS)));
    }

    /**
     * Revisión Importante 1: Livewire ya rechaza reescribir propiedades de un modelo público
     * ("Can't set model properties directly"); #[Locked] lo vuelve explícito y estable ante
     * cambios de Livewire. La prueba exige la excepción de bloqueo, no un rechazo cualquiera.
     */
    public function test_el_modelo_del_nivel_esta_bloqueado_contra_reescritura_del_cliente(): void
    {
        $propio = $this->nivel();
        $ajeno = $this->nivel();
        $this->actingAs($propio->escuela->solicitante->user);

        foreach (['escuelaNivel.id' => $ajeno->id, 'escuelaNivel.escuela_id' => $ajeno->escuela_id] as $ruta => $valor) {
            $componente = Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $propio->fresh()]);

            try {
                $componente->set($ruta, $valor);
                $this->fail("Se aceptó reescribir {$ruta}.");
            } catch (CannotUpdateLockedPropertyException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_guardar_datos_solo_toca_el_nivel_propio(): void
    {
        $propio = $this->nivel();
        $ajeno = $this->nivel();
        $this->actingAs($propio->escuela->solicitante->user);

        Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $propio->fresh()])
            ->set('turno', 'vespertino')
            ->set('tipoAlumnado', 'femenino')
            ->call('guardarDatos');

        $this->assertSame('vespertino', $propio->fresh()->turno);
        $this->assertNull($ajeno->fresh()->turno);
    }

    /** Revisión Importante 2a: DatosInvalidos que el formulario deja pasar y el caso de uso rechaza (3 decimales) llega como error de campo. */
    public function test_un_monto_que_el_caso_de_uso_rechaza_se_muestra_como_error_del_campo(): void
    {
        $escuelaNivel = $this->nivel();

        Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->set('archivos.recibo_pago_derechos', $this->pdf('recibo.pdf'))
            ->set('recibo.folio', 'F-1')
            ->set('recibo.monto', '10.999')
            ->set('recibo.fechaPago', now()->toDateString())
            ->call('guardarRecibo')
            ->assertHasErrors('recibo.monto');

        $this->assertDatabaseCount('recibos_pago_derechos', 0);
    }

    /** Revisión Importante 2b: si Paso 2 deja de estar completo tras montar, guardar redirige a Paso 2 (sin error de campo ni escritura). */
    public function test_si_paso2_se_incompleta_tras_montar_guardar_documento_redirige_a_paso2(): void
    {
        $escuelaNivel = $this->nivel();
        $componente = Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel->fresh()]);
        DocumentoEscuela::where('escuela_id', $escuelaNivel->escuela_id)->delete();

        $componente->set('archivos.acervo_bibliografico_primaria', $this->pdf())
            ->call('guardarDocumento', 'acervo_bibliografico_primaria')
            ->assertRedirect(route('tramite.paso2', ['escuela' => $escuelaNivel->escuela_id]));

        $this->assertDatabaseCount('documentos_escuela_nivel', 0);
    }

    public function test_si_paso2_se_incompleta_tras_montar_guardar_datos_redirige_a_paso2(): void
    {
        $escuelaNivel = $this->nivel();
        $componente = Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel->fresh()]);
        DocumentoEscuela::where('escuela_id', $escuelaNivel->escuela_id)->delete();

        $componente->set('turno', 'vespertino')
            ->set('tipoAlumnado', 'mixto')
            ->call('guardarDatos')
            ->assertRedirect(route('tramite.paso2', ['escuela' => $escuelaNivel->escuela_id]));

        $this->assertNull($escuelaNivel->fresh()->turno);
    }

    public function test_toggle_reemplazar_solo_acepta_claves_aplicables_al_nivel(): void
    {
        $escuelaNivel = $this->nivel('primaria');

        Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $escuelaNivel])
            ->call('toggleReemplazar', 'inventario_laboratorio')
            ->call('toggleReemplazar', 'clave_inventada')
            ->assertSet('reemplazando', [])
            ->call('toggleReemplazar', 'formato_solicitud')
            ->assertSet('reemplazando.formato_solicitud', true);
    }
}
