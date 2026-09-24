<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\EscuelaNiveles\RegistrarNivelesSeleccionados;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Livewire\Tramite\Paso2Responsable;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use App\Models\TipoDocumento;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class Paso2ResponsableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // guardarResponsable() ahora consulta el catálogo (gate a Documentos);
        // sin esto, cualquier test que llegue a guardarResponsable() lanza la
        // RuntimeException de catálogo incompleto.
        (new TiposDocumentosSeeder)->run();
    }

    private function crearEscuelaPara(Solicitante $solicitante): Escuela
    {
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    /**
     * WS-2.4b: RegistrarDocumento ahora exige responsable legal capturado
     * antes de escribir. Algunos escenarios de este archivo necesitan
     * documentos ya en disco SIN responsable capturado todavía (simulando
     * back-navigation/datos legacy) — se insertan directo, mismo patrón que
     * DocumentoDownloadTest para los casos "documento legacy".
     */
    private function registrarDocumentoLegacy(Escuela $escuela, string $clave, ?string $fechaEmision = null): void
    {
        $tipo = TipoDocumento::where('clave', $clave)->firstOrFail();
        $ownerId = $tipo->ambito === 'plantel' ? $escuela->plantel_id : $escuela->id;
        $ruta = ($tipo->ambito === 'plantel' ? 'plantel/' : 'escuela/')."{$ownerId}/{$clave}-legacy.pdf";
        $fechaVigencia = $fechaEmision !== null && $tipo->vigencia_max_dias !== null
            ? date('Y-m-d', strtotime("{$fechaEmision} +{$tipo->vigencia_max_dias} days"))
            : null;
        $atributos = [
            'tipo_documento_id' => $tipo->id,
            'archivo_path' => $ruta,
            'fecha_emision' => $fechaEmision,
            'fecha_vigencia' => $fechaVigencia,
            'estado_validacion' => 'pendiente',
        ];

        if ($tipo->ambito === 'plantel') {
            DocumentoPlantel::create(['plantel_id' => $ownerId, ...$atributos]);
        } else {
            DocumentoEscuela::create(['escuela_id' => $ownerId, ...$atributos]);
        }

        Storage::disk('documentos')->put($ruta, 'CONTENIDO');
    }

    public function test_arranca_en_fase_responsable_cuando_no_hay_responsable_legal(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertSet('fase', 'responsable');
    }

    public function test_resume_en_fase_niveles_cuando_ya_hay_responsable_legal(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));
        $registrar = new RegistrarDocumento;
        foreach (['ine', 'acta_nacimiento', 'escritura_inmueble', 'dictamen_uso_suelo', 'constancia_seguridad_estructural', 'formato_solicitud'] as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertSet('fase', 'niveles');
    }

    /**
     * Antes de esto: navegar de vuelta a fase 'niveles' no mostraba nada del
     * responsable ya capturado — mount() saltaba directo al selector de
     * niveles sin ningún resumen de lo ya enviado.
     */
    public function test_fase_niveles_muestra_un_resumen_de_solo_lectura_del_responsable_ya_capturado(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(
            tipoPersona: 'fisica',
            domicilioNotificaciones: 'Calle Falsa 123, Centro',
            nombre: 'Juana Pérez',
        ));
        $registrar = new RegistrarDocumento;
        foreach (['ine', 'acta_nacimiento', 'escritura_inmueble', 'dictamen_uso_suelo', 'constancia_seguridad_estructural', 'formato_solicitud'] as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertSee('Juana Pérez')
            ->assertSee('Calle Falsa 123, Centro')
            ->assertSee('Persona física');
    }

    public function test_redirige_a_documentos_si_responsable_legal_existe_pero_documentos_incompletos(): void
    {
        (new TiposDocumentosSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertRedirect(route('tramite.paso2-documentos', ['escuela' => $escuela->id]));
    }

    public function test_redirige_a_documentos_si_el_dictamen_de_uso_de_suelo_esta_vencido(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));
        $registrar = new RegistrarDocumento;
        foreach (['ine', 'acta_nacimiento', 'escritura_inmueble', 'constancia_seguridad_estructural', 'formato_solicitud'] as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }
        $registrar->ejecutar($escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'), new DatosDocumento(
            fechaEmision: now()->subDays(60)->toDateString(),
        ));
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertRedirect(route('tramite.paso2-documentos', ['escuela' => $escuela->id]));
    }

    public function test_redirige_a_paso3_cuando_la_escuela_ya_tiene_niveles(): void
    {
        (new CatalogoMinimoSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));
        $this->registrarDocumentosCompletos($escuela);
        $preescolar = NivelEducativo::where('clave', 'preescolar')->first();
        app(RegistrarNivelesSeleccionados::class)->ejecutar($escuela->id, [$preescolar->id]);
        $this->actingAs($solicitante->user);
        $escuelaNivel = EscuelaNivel::where('escuela_id', $escuela->id)->firstOrFail();

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertRedirect(route('tramite.paso3-inmueble', ['escuelaNivel' => $escuelaNivel->id]));
    }

    /**
     * Antes de esto: guardarResponsable() ponía fase='niveles' sin condición
     * — Documentos (Paso 2.2) nunca se hacía cumplir dentro de la misma
     * visita, solo en un mount() posterior (que ya no se vuelve a ejecutar
     * una vez que escuela_niveles existe). En la práctica, Documentos era
     * inalcanzable en el flujo normal de un solo tramo.
     */
    public function test_guardar_responsable_redirige_a_documentos_si_documentos_no_estan_completos(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('tipoPersona', 'fisica')
            ->set('domicilioNotificaciones', 'Calle Falsa 123, Centro')
            ->set('nombrePropuesto1', 'Colegio Reforma')
            ->set('nombrePropuesto2', 'Instituto Reforma')
            ->set('nombrePropuesto3', 'Escuela Reforma')
            ->set('personaFisicaForm.nombre', 'Juana Pérez')
            ->call('guardarResponsable')
            ->assertRedirect(route('tramite.paso2-documentos', ['escuela' => $escuela->id]));

        $this->assertDatabaseHas('responsables_legales', ['escuela_id' => $escuela->id, 'tipo_persona' => 'fisica']);
    }

    public function test_guardar_responsable_avanza_a_fase_niveles_si_los_documentos_ya_estaban_completos(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        // WS-2.4b: RegistrarDocumento ahora exige responsable legal capturado
        // antes de escribir, pero este escenario depende de que mount() vea
        // AÚN sin responsable (fase arranca en 'responsable') con los
        // documentos ya en disco (back-navigation) — se insertan directo,
        // como datos preexistentes/legacy, igual que en DocumentoDownloadTest.
        foreach (['ine', 'acta_nacimiento', 'escritura_inmueble', 'dictamen_uso_suelo', 'constancia_seguridad_estructural', 'formato_solicitud'] as $clave) {
            $this->registrarDocumentoLegacy($escuela, $clave);
        }
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('tipoPersona', 'fisica')
            ->set('domicilioNotificaciones', 'Calle Falsa 123, Centro')
            ->set('nombrePropuesto1', 'Colegio Reforma')
            ->set('nombrePropuesto2', 'Instituto Reforma')
            ->set('nombrePropuesto3', 'Escuela Reforma')
            ->set('personaFisicaForm.nombre', 'Juana Pérez')
            ->call('guardarResponsable')
            ->assertSet('fase', 'niveles')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('responsables_legales', ['escuela_id' => $escuela->id, 'tipo_persona' => 'fisica']);
    }

    /** WS-1.2: guardarResponsable() aplica también la vigencia, no solo la completitud. */
    public function test_guardar_responsable_redirige_a_documentos_si_el_dictamen_esta_vencido(): void
    {
        Storage::fake('documentos');
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        // WS-2.4b: ver comentario equivalente arriba — documentos insertados
        // directo para que mount() siga viendo la escuela sin responsable.
        foreach (['ine', 'acta_nacimiento', 'escritura_inmueble', 'constancia_seguridad_estructural', 'formato_solicitud'] as $clave) {
            $this->registrarDocumentoLegacy($escuela, $clave);
        }
        $this->registrarDocumentoLegacy($escuela, 'dictamen_uso_suelo', now()->subDays(60)->toDateString());
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('tipoPersona', 'fisica')
            ->set('domicilioNotificaciones', 'Calle Falsa 123, Centro')
            ->set('nombrePropuesto1', 'Colegio Reforma')
            ->set('nombrePropuesto2', 'Instituto Reforma')
            ->set('nombrePropuesto3', 'Escuela Reforma')
            ->set('personaFisicaForm.nombre', 'Juana Pérez')
            ->call('guardarResponsable')
            ->assertRedirect(route('tramite.paso2-documentos', ['escuela' => $escuela->id]));
    }

    public function test_captura_domicilio_notificaciones_y_persona_autorizada_recoger(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('tipoPersona', 'fisica')
            ->set('domicilioNotificaciones', 'Calle Falsa 123, Centro, Querétaro')
            ->set('personaAutorizadaRecoger', 'María López')
            ->set('nombrePropuesto1', 'Colegio Reforma')
            ->set('nombrePropuesto2', 'Instituto Reforma')
            ->set('nombrePropuesto3', 'Escuela Reforma')
            ->set('personaFisicaForm.nombre', 'Juana Pérez')
            ->call('guardarResponsable')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('responsables_legales', [
            'escuela_id' => $escuela->id,
            'domicilio_notificaciones' => 'Calle Falsa 123, Centro, Querétaro',
            'persona_autorizada_recoger' => 'María López',
        ]);
    }

    public function test_rechaza_tipo_persona_desconocido(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('tipoPersona', 'algo_inventado')
            ->set('domicilioNotificaciones', 'Calle Falsa 123')
            ->call('guardarResponsable')
            ->assertHasErrors('tipoPersona');

        $this->assertDatabaseCount('responsables_legales', 0);
    }

    public function test_domicilio_notificaciones_es_requerido(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('tipoPersona', 'fisica')
            ->set('personaFisicaForm.nombre', 'Juana Pérez')
            ->call('guardarResponsable')
            ->assertHasErrors('domicilioNotificaciones');

        $this->assertDatabaseCount('responsables_legales', 0);
    }

    public function test_tipo_moral_captura_todos_los_campos_notariales(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('tipoPersona', 'moral')
            ->set('domicilioNotificaciones', 'Av. Reforma 456')
            ->set('nombrePropuesto1', 'Colegio Ejemplo')
            ->set('nombrePropuesto2', 'Instituto Ejemplo')
            ->set('nombrePropuesto3', 'Escuela Ejemplo')
            ->set('personaMoralForm.razonSocial', 'Colegio Ejemplo A.C.')
            ->set('personaMoralForm.nombreRepresentanteLegal', 'Carlos Ruiz')
            ->set('personaMoralForm.numeroEscrituraConstitutiva', 'E-100')
            ->set('personaMoralForm.fechaEscrituraConstitutiva', '2020-01-15')
            ->set('personaMoralForm.notarioNombre', 'Lic. Pedro Notario')
            ->set('personaMoralForm.notarioNumero', '45')
            ->set('personaMoralForm.notarioCiudad', 'Querétaro')
            ->set('personaMoralForm.folioRegistroPublico', 'F-999')
            ->set('personaMoralForm.fechaInscripcionRpp', '2020-02-01')
            ->call('guardarResponsable')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('personas_morales', [
            'razon_social' => 'Colegio Ejemplo A.C.',
            'nombre_representante_legal' => 'Carlos Ruiz',
            'numero_escritura_constitutiva' => 'E-100',
            'fecha_escritura_constitutiva' => '2020-01-15',
            'notario_nombre' => 'Lic. Pedro Notario',
            'notario_numero' => '45',
            'notario_ciudad' => 'Querétaro',
            'folio_registro_publico' => 'F-999',
            'fecha_inscripcion_rpp' => '2020-02-01',
        ]);
    }

    public function test_tipo_fisica_con_gestor_captura_fecha_nacimiento_y_datos_notariales_del_gestor(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('tipoPersona', 'fisica_con_gestor')
            ->set('domicilioNotificaciones', 'Calle Falsa 123')
            ->set('nombrePropuesto1', 'Colegio Reforma')
            ->set('nombrePropuesto2', 'Instituto Reforma')
            ->set('nombrePropuesto3', 'Escuela Reforma')
            ->set('personaFisicaForm.nombre', 'Juana Pérez')
            ->set('personaFisicaForm.fechaNacimiento', '1985-06-20')
            ->set('gestorForm.nombre', 'Roberto Gómez')
            ->set('gestorForm.numeroPoder', 'P-500')
            ->set('gestorForm.notarioNombre', 'Lic. Ana Notaria')
            ->set('gestorForm.notarioNumero', '10')
            ->set('gestorForm.fechaPoder', '2021-03-10')
            ->call('guardarResponsable')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('personas_fisicas', [
            'nombre' => 'Juana Pérez',
            'fecha_nacimiento' => '1985-06-20',
        ]);
        $this->assertDatabaseHas('gestores', [
            'nombre' => 'Roberto Gómez',
            'numero_poder' => 'P-500',
            'notario_nombre' => 'Lic. Ana Notaria',
            'notario_numero' => '10',
            'fecha_poder' => '2021-03-10',
        ]);
    }

    public function test_rfc_y_curp_se_guardan_en_mayusculas(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('tipoPersona', 'fisica')
            ->set('domicilioNotificaciones', 'Calle Falsa 123')
            ->set('nombrePropuesto1', 'Colegio Reforma')
            ->set('nombrePropuesto2', 'Instituto Reforma')
            ->set('nombrePropuesto3', 'Escuela Reforma')
            ->set('personaFisicaForm.nombre', 'Juana Pérez')
            ->set('personaFisicaForm.rfc', 'perj850620ab1')
            ->set('personaFisicaForm.curp', 'perj850620mqrrn01')
            ->call('guardarResponsable')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('personas_fisicas', [
            'rfc' => 'PERJ850620AB1',
            'curp' => 'PERJ850620MQRRN01',
        ]);
    }

    public function test_captura_la_terna_de_nombres_propuestos(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('tipoPersona', 'fisica')
            ->set('domicilioNotificaciones', 'Calle Falsa 123')
            ->set('nombrePropuesto1', 'Colegio Reforma')
            ->set('nombrePropuesto2', 'Instituto Reforma')
            ->set('nombrePropuesto3', 'Escuela Reforma')
            ->set('personaFisicaForm.nombre', 'Juana Pérez')
            ->call('guardarResponsable')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ternas_nombres', ['escuela_id' => $escuela->id, 'numero_propuesta' => 1, 'nombre_propuesto' => 'Colegio Reforma']);
        $this->assertDatabaseHas('ternas_nombres', ['escuela_id' => $escuela->id, 'numero_propuesta' => 2, 'nombre_propuesto' => 'Instituto Reforma']);
        $this->assertDatabaseHas('ternas_nombres', ['escuela_id' => $escuela->id, 'numero_propuesta' => 3, 'nombre_propuesto' => 'Escuela Reforma']);
    }

    public function test_la_terna_de_nombres_es_requerida(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('tipoPersona', 'fisica')
            ->set('domicilioNotificaciones', 'Calle Falsa 123')
            ->set('personaFisicaForm.nombre', 'Juana Pérez')
            ->call('guardarResponsable')
            ->assertHasErrors(['nombrePropuesto1', 'nombrePropuesto2', 'nombrePropuesto3']);

        $this->assertDatabaseCount('responsables_legales', 0);
        $this->assertDatabaseCount('ternas_nombres', 0);
    }

    /** Registra los 6 documentos requeridos para que mount() no redirija a paso2-documentos. */
    private function registrarDocumentosCompletos(Escuela $escuela): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $registrar = new RegistrarDocumento;
        foreach (['ine', 'acta_nacimiento', 'escritura_inmueble', 'dictamen_uso_suelo', 'constancia_seguridad_estructural', 'formato_solicitud'] as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }
    }

    public function test_rechaza_envio_sin_ningun_nivel_seleccionado(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));
        $this->registrarDocumentosCompletos($escuela);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('nivelesSeleccionados', [])
            ->call('guardarNiveles')
            ->assertHasErrors('nivelesSeleccionados');

        $this->assertDatabaseCount('escuela_niveles', 0);
    }

    public function test_rechaza_nivel_seleccionado_no_numerico(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));
        $this->registrarDocumentosCompletos($escuela);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('nivelesSeleccionados', ['no-es-un-id'])
            ->call('guardarNiveles')
            ->assertHasErrors('nivelesSeleccionados.0');

        $this->assertDatabaseCount('escuela_niveles', 0);
    }

    /** Handler de PrecondicionIncumplida: guardarNiveles() sin Paso 2 completo manda de vuelta a /tramite/paso2 sin escribir. */
    public function test_guardar_niveles_con_paso2_incompleto_redirige_a_paso2_sin_escribir(): void
    {
        (new CatalogoMinimoSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertNoRedirect()
            ->set('nivelesSeleccionados', [NivelEducativo::where('clave', 'preescolar')->value('id')])
            ->call('guardarNiveles')
            ->assertRedirect(route('tramite.paso2', ['escuela' => $escuela->id]));

        $this->assertDatabaseCount('escuela_niveles', 0);
    }

    public function test_envio_con_niveles_crea_escuela_niveles_y_redirige(): void
    {
        (new CatalogoMinimoSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));
        $this->registrarDocumentosCompletos($escuela);
        $this->actingAs($solicitante->user);
        $preescolar = NivelEducativo::where('clave', 'preescolar')->first();

        $component = Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('nivelesSeleccionados', [$preescolar->id])
            ->call('guardarNiveles');

        $this->assertDatabaseHas('escuela_niveles', ['escuela_id' => $escuela->id, 'nivel_educativo_id' => $preescolar->id]);
        $escuelaNivel = EscuelaNivel::where('escuela_id', $escuela->id)->firstOrFail();
        $component->assertRedirect(route('tramite.paso3-inmueble', ['escuelaNivel' => $escuelaNivel->id]));
    }
}
