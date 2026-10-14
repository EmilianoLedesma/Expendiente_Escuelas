<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\EscuelaNiveles\RegistrarNivelesSeleccionados;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Livewire\Tramite\Paso2Responsable;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

/** WS-7b-1 U2 (presentación): Paso 2.1 prellenado y editable hasta el envío. */
class Paso2ResponsableEdicionTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    private const AVISO_TIPO = 'Revisa tus documentos: algunos dejaron de aplicar y tu identificación debe corresponder a la nueva persona.';

    private const AVISO_BORRADO = 'Si cambias el tipo de persona, se borrarán los datos capturados de la persona o del gestor anterior; tus documentos se conservan.';

    private Solicitante $solicitante;

    protected function setUp(): void
    {
        parent::setUp();
        (new TiposDocumentosSeeder)->run();
        $this->solicitante = Solicitante::factory()->create();
        $this->actingAs($this->solicitante->user);
    }

    private function escuela(): Escuela
    {
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $this->solicitante->id]);
    }

    private function registrar(Escuela $escuela, string $tipo): void
    {
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(
            tipoPersona: $tipo,
            domicilioNotificaciones: 'Calle Falsa 123, Centro',
            personaAutorizadaRecoger: 'María López',
            nombrePropuesto1: 'Colegio Reforma',
            nombrePropuesto2: 'Instituto Reforma',
            nombrePropuesto3: 'Escuela Reforma',
            nombre: $tipo === 'moral' ? null : 'Juana Pérez',
            fechaNacimiento: $tipo === 'moral' ? null : '1985-06-20',
            rfc: $tipo === 'moral' ? null : 'PEJU800101ABC',
            razonSocial: $tipo === 'moral' ? 'Colegio Reforma S.C.' : null,
            notarioNombre: $tipo === 'moral' ? 'Lic. Pedro Notario' : null,
            nombreRepresentanteLegal: $tipo === 'moral' ? 'Miguel Torres' : null,
            gestorNombre: $tipo === 'fisica_con_gestor' ? 'Carlos Gómez' : null,
            gestorCurp: $tipo === 'fisica_con_gestor' ? 'GOMC800101HQTMRR01' : null,
        ));
    }

    public function test_prellena_persona_moral_terna_y_notificaciones(): void
    {
        $escuela = $this->escuela();
        $this->registrar($escuela, 'moral');

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertSet('fase', 'responsable')
            ->assertSet('tipoPersona', 'moral')
            ->assertSet('domicilioNotificaciones', 'Calle Falsa 123, Centro')
            ->assertSet('personaAutorizadaRecoger', 'María López')
            ->assertSet('nombrePropuesto1', 'Colegio Reforma')
            ->assertSet('nombrePropuesto3', 'Escuela Reforma')
            ->assertSet('personaMoralForm.razonSocial', 'Colegio Reforma S.C.')
            ->assertSet('personaMoralForm.notarioNombre', 'Lic. Pedro Notario')
            ->assertSet('personaMoralForm.nombreRepresentanteLegal', 'Miguel Torres');
    }

    public function test_prellena_persona_fisica_y_gestor(): void
    {
        $escuela = $this->escuela();
        $this->registrar($escuela, 'fisica_con_gestor');

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertSet('tipoPersona', 'fisica_con_gestor')
            ->assertSet('personaFisicaForm.nombre', 'Juana Pérez')
            ->assertSet('personaFisicaForm.fechaNacimiento', '1985-06-20')
            ->assertSet('personaFisicaForm.rfc', 'PEJU800101ABC')
            ->assertSet('gestorForm.nombre', 'Carlos Gómez')
            ->assertSet('gestorForm.curp', 'GOMC800101HQTMRR01');
    }

    public function test_editar_guarda_en_el_lugar_y_se_queda_en_la_pagina(): void
    {
        $escuela = $this->escuela();
        $this->registrar($escuela, 'fisica');

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('domicilioNotificaciones', 'Av. Universidad 200')
            ->call('guardarResponsable')
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertSet('fase', 'responsable')
            ->assertDispatched('toast', tipo: 'success', titulo: 'Cambios guardados')
            ->assertDontSee(self::AVISO_TIPO);

        $this->assertDatabaseCount('responsables_legales', 1);
        $this->assertDatabaseHas('responsables_legales', ['escuela_id' => $escuela->id, 'domicilio_notificaciones' => 'Av. Universidad 200']);
    }

    /** D2: tras cambiar el tipo, aviso de documentos; no se exige volver a subir INE/CURP. */
    public function test_tras_cambiar_el_tipo_muestra_el_aviso_de_documentos(): void
    {
        $escuela = $this->escuela();
        $this->registrar($escuela, 'fisica');

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertDontSee(self::AVISO_TIPO)
            ->set('tipoPersona', 'moral')
            ->set('personaMoralForm.razonSocial', 'Colegio Reforma S.C.')
            ->set('personaMoralForm.nombreRepresentanteLegal', 'Miguel Torres')
            ->call('guardarResponsable')
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertSee(self::AVISO_TIPO);

        $this->assertDatabaseHas('responsables_legales', ['escuela_id' => $escuela->id, 'tipo_persona' => 'moral']);
    }

    /** D2: con los documentos ya completos para el nuevo tipo, el enlace del aviso abre la checklist (no rebota). */
    public function test_el_enlace_del_aviso_abre_documentos_aunque_esten_completos(): void
    {
        $escuela = $this->escuela();
        $this->completarPaso2($escuela->id);
        $this->registrar($escuela, 'moral');
        $registrar = app(RegistrarDocumento::class);
        foreach (array_diff(app(DocumentosCompletos::class)->clavesAplicables('moral'), app(DocumentosCompletos::class)->clavesAplicables('fisica')) as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }
        $enlace = route('tramite.paso2-documentos', ['escuela' => $escuela->id]);

        Livewire::withQueryParams(['seccion' => 'responsable'])
            ->test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('tipoPersona', 'fisica')
            ->set('personaFisicaForm.nombre', 'Juana Pérez')
            ->call('guardarResponsable')
            ->assertHasNoErrors()
            ->assertSee(self::AVISO_TIPO)
            ->assertSeeHtml('href="'.$enlace.'"');

        $total = count(app(DocumentosCompletos::class)->clavesAplicables('fisica'));
        $this->get($enlace)
            ->assertOk()
            ->assertSee("{$total} de {$total} documentos completos")
            ->assertSee('Reemplazar');
    }

    public function test_al_editar_avisa_que_cambiar_el_tipo_borra_los_datos_anteriores(): void
    {
        $escuela = $this->escuela();

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertDontSee(self::AVISO_BORRADO);

        $this->registrar($escuela, 'fisica');

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertSee(self::AVISO_BORRADO);
    }

    /** La regla de la terna la decide el caso de uso; Livewire solo muestra el error en el campo. */
    public function test_una_terna_repetida_se_marca_en_el_campo(): void
    {
        $escuela = $this->escuela();
        $this->registrar($escuela, 'fisica');

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('nombrePropuesto3', 'COLEGIO REFORMA')
            ->call('guardarResponsable')
            ->assertHasErrors(['nombrePropuesto3']);

        $this->assertDatabaseHas('ternas_nombres', ['escuela_id' => $escuela->id, 'numero_propuesta' => 3, 'nombre_propuesto' => 'Escuela Reforma']);
    }

    public function test_seccion_responsable_abre_el_formulario_aunque_falten_los_niveles(): void
    {
        $escuela = $this->escuela();
        $this->completarPaso2($escuela->id);

        Livewire::withQueryParams(['seccion' => 'responsable'])
            ->test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertSet('fase', 'responsable')
            ->assertSet('personaFisicaForm.nombre', 'Juana Pérez');
    }

    public function test_el_resumen_muestra_terna_persona_autorizada_gestor_y_enlace_para_editar(): void
    {
        $escuela = $this->escuela();
        $this->completarPaso2($escuela->id, 'fisica_con_gestor');
        $this->registrar($escuela, 'fisica_con_gestor');

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertSet('fase', 'niveles')
            ->assertSee('Carlos Gómez')
            ->assertSee('María López')
            ->assertSee('Colegio Reforma')
            ->assertSee('Escuela Reforma')
            ->assertSeeHtml('href="'.route('tramite.paso2', ['escuela' => $escuela->id, 'seccion' => 'responsable']).'"')
            ->assertDontSee('requiere autorización previa');
    }

    /** D1 visible: con niveles elegidos, el formulario avisa que se descartará el Formato firmado. */
    public function test_con_niveles_avisa_que_se_descarta_el_formato_firmado(): void
    {
        $escuela = $this->escuela();
        $this->completarPaso2($escuela->id);
        app(RegistrarNivelesSeleccionados::class)->ejecutar($escuela->id, [(int) NivelEducativo::where('clave', 'primaria')->value('id')]);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertSee('se descartará el Formato de Solicitud firmado');
    }

    public function test_seccion_niveles_abre_la_fase_de_niveles_con_niveles_presentes(): void
    {
        $escuela = $this->escuela();
        $this->completarPaso2($escuela->id);
        app(RegistrarNivelesSeleccionados::class)->ejecutar($escuela->id, [(int) NivelEducativo::where('clave', 'primaria')->value('id')]);

        Livewire::withQueryParams(['seccion' => 'niveles'])
            ->test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertSet('fase', 'niveles');
    }

    public function test_sin_niveles_no_muestra_el_aviso_del_formato(): void
    {
        $escuela = $this->escuela();
        $this->registrar($escuela, 'fisica');

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertDontSee('se descartará el Formato de Solicitud firmado');
    }

    /** Un trámite ya enviado no se edita: el caso de uso rechaza y la página muestra el error sin redirigir. */
    public function test_con_el_tramite_enviado_muestra_el_error_y_no_cambia_nada(): void
    {
        $escuela = $this->escuela();
        $this->registrar($escuela, 'fisica');

        $componente = Livewire::test(Paso2Responsable::class, ['escuela' => $escuela]);

        EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);

        $componente
            ->set('domicilioNotificaciones', 'Av. Universidad 200')
            ->call('guardarResponsable')
            ->assertHasErrors(['domicilioNotificaciones'])
            ->assertNoRedirect()
            ->assertDispatched('toast', tipo: 'error');

        $this->assertDatabaseMissing('responsables_legales', ['escuela_id' => $escuela->id, 'domicilio_notificaciones' => 'Av. Universidad 200']);
    }
}
