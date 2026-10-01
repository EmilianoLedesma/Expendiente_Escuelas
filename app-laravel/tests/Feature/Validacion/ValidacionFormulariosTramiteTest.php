<?php

namespace Tests\Feature\Validacion;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Livewire\Tramite\Paso1Preregistro;
use App\Livewire\Tramite\Paso24DocumentosNivel;
use App\Livewire\Tramite\Paso2Documentos;
use App\Livewire\Tramite\Paso2Responsable;
use App\Livewire\Tramite\Paso3\DatosInmueble;
use App\Livewire\Tramite\Paso3\InfraestructuraNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Database\Seeders\TiposDocumentosSeeder;
use Database\Seeders\TiposEspaciosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\CompletaPaso2;
use Tests\Concerns\CompletaPaso24;
use Tests\TestCase;

/**
 * Cada campo del trámite se limpia, se valida al capturarlo y responde con
 * un mensaje en español junto al campo. Complementa los tests de cada paso,
 * que cubren el flujo; aquí solo forma y contenido de lo capturado.
 */
class ValidacionFormulariosTramiteTest extends TestCase
{
    use CompletaPaso2;
    use CompletaPaso24;
    use RefreshDatabase;

    private Solicitante $solicitante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->solicitante = Solicitante::factory()->create();
        $this->actingAs($this->solicitante->user);
    }

    private function escuela(): Escuela
    {
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $this->solicitante->id]);
    }

    private function primerError(Testable $prueba, string $campo): ?string
    {
        return $prueba->errors()->first($campo) ?: null;
    }

    // --- Paso 1 -------------------------------------------------------------

    public function test_paso1_normaliza_telefono_correo_y_codigo_postal(): void
    {
        Livewire::test(Paso1Preregistro::class)
            ->set('telefono', '+52 (442) 123-4567')
            ->set('correoElectronico', '  Direccion@Escuela.MX ')
            ->set('codigoPostal', ' 76 000 ')
            ->assertSet('telefono', '4421234567')
            ->assertSet('correoElectronico', 'direccion@escuela.mx')
            ->assertSet('codigoPostal', '76000')
            ->assertHasNoErrors();
    }

    public function test_paso1_rechaza_formatos_invalidos_al_capturar(): void
    {
        $prueba = Livewire::test(Paso1Preregistro::class)
            ->set('codigoPostal', '7600')
            ->set('telefono', '442123')
            ->assertHasErrors(['codigoPostal', 'telefono']);

        $this->assertSame('El código postal debe tener 5 dígitos, por ejemplo 76000.', $this->primerError($prueba, 'codigoPostal'));
        $this->assertSame('El teléfono debe tener 10 dígitos, por ejemplo 4421234567.', $this->primerError($prueba, 'telefono'));
    }

    public function test_paso1_un_campo_solo_con_espacios_es_obligatorio(): void
    {
        $prueba = Livewire::test(Paso1Preregistro::class)
            ->set('calle', '    ')
            ->assertSet('calle', '')
            ->call('guardar')
            ->assertHasErrors(['calle']);

        $this->assertSame('El campo calle es obligatorio.', $this->primerError($prueba, 'calle'));
    }

    // --- Paso 2.1 -----------------------------------------------------------

    public function test_paso2_responsable_normaliza_curp_y_rfc(): void
    {
        Livewire::test(Paso2Responsable::class, ['escuela' => $this->escuela()])
            ->set('personaFisicaForm.curp', ' goma800101hqtrrl09 ')
            ->set('personaFisicaForm.rfc', 'goma-800101-ab1')
            ->assertSet('personaFisicaForm.curp', 'GOMA800101HQTRRL09')
            ->assertSet('personaFisicaForm.rfc', 'GOMA800101AB1')
            ->assertHasNoErrors();
    }

    public function test_paso2_responsable_rechaza_datos_personales_invalidos(): void
    {
        $prueba = Livewire::test(Paso2Responsable::class, ['escuela' => $this->escuela()])
            ->set('personaFisicaForm.curp', 'GOMA800101')
            ->set('personaFisicaForm.rfc', 'ABC800101AB1')
            ->set('personaFisicaForm.nombre', 'Juana 3')
            ->set('personaFisicaForm.fechaNacimiento', '2999-01-01')
            ->assertHasErrors(['personaFisicaForm.curp', 'personaFisicaForm.rfc', 'personaFisicaForm.nombre', 'personaFisicaForm.fechaNacimiento']);

        $this->assertSame('La CURP no tiene un formato válido: son 18 caracteres, por ejemplo GOMA800101HQTRRL09.', $this->primerError($prueba, 'personaFisicaForm.curp'));
        $this->assertSame('El campo nombre completo solo puede contener letras, espacios, puntos, apóstrofos y guiones.', $this->primerError($prueba, 'personaFisicaForm.nombre'));
        $this->assertSame('La fecha de nacimiento no puede ser posterior a hoy.', $this->primerError($prueba, 'personaFisicaForm.fechaNacimiento'));
    }

    public function test_paso2_responsable_los_campos_de_cada_formulario_tienen_su_propio_nombre_visible(): void
    {
        $prueba = Livewire::test(Paso2Responsable::class, ['escuela' => $this->escuela()])
            ->set('tipoPersona', 'fisica_con_gestor')
            ->call('guardarResponsable');

        $this->assertSame('El campo nombre completo es obligatorio.', $this->primerError($prueba, 'personaFisicaForm.nombre'));

        $prueba->set('personaFisicaForm.nombre', 'Juana Pérez')->call('guardarResponsable');
        $this->assertSame('El campo nombre del gestor es obligatorio.', $this->primerError($prueba, 'gestorForm.nombre'));
    }

    public function test_paso2_responsable_la_terna_no_admite_nombres_repetidos(): void
    {
        $prueba = Livewire::test(Paso2Responsable::class, ['escuela' => $this->escuela()])
            ->set('nombrePropuesto1', 'Colegio Juárez')
            ->set('nombrePropuesto2', 'colegio  JUÁREZ')
            ->assertHasErrors(['nombrePropuesto2']);

        $this->assertSame('Las tres propuestas de nombre deben ser distintas.', $this->primerError($prueba, 'nombrePropuesto2'));
    }

    public function test_paso2_responsable_valida_persona_moral(): void
    {
        $prueba = Livewire::test(Paso2Responsable::class, ['escuela' => $this->escuela()])
            ->set('tipoPersona', 'moral')
            ->set('personaMoralForm.nombreRepresentanteLegal', 'Miguel 2')
            ->set('personaMoralForm.fechaEscrituraConstitutiva', '2999-01-01')
            ->assertHasErrors(['personaMoralForm.nombreRepresentanteLegal', 'personaMoralForm.fechaEscrituraConstitutiva']);

        $this->assertSame('La fecha de escritura constitutiva no puede ser posterior a hoy.', $this->primerError($prueba, 'personaMoralForm.fechaEscrituraConstitutiva'));
    }

    // --- Paso 2.2 -----------------------------------------------------------

    private function escuelaConResponsable(): Escuela
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->escuela();
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));

        return $escuela;
    }

    public function test_paso2_documentos_una_fecha_de_emision_futura_se_rechaza(): void
    {
        $prueba = Livewire::test(Paso2Documentos::class, ['escuela' => $this->escuelaConResponsable()])
            ->set('dictamenForm.fechaEmision', '2999-01-01')
            ->set('constanciaForm.peritoNombre', 'Ing. Pedro 5')
            ->assertHasErrors(['dictamenForm.fechaEmision', 'constanciaForm.peritoNombre']);

        $this->assertSame('La fecha de emisión no puede ser posterior a hoy.', $this->primerError($prueba, 'dictamenForm.fechaEmision'));
    }

    public function test_paso2_documentos_la_vigencia_del_contrato_no_es_anterior_al_contrato(): void
    {
        $prueba = Livewire::test(Paso2Documentos::class, ['escuela' => $this->escuelaConResponsable()])
            ->set('acreditacionForm.tipo', 'arrendamiento')
            ->set('acreditacionForm.fechaContrato', '2025-06-01')
            ->set('acreditacionForm.vigenciaContrato', '2025-01-01')
            ->assertHasErrors(['acreditacionForm.vigenciaContrato']);

        $this->assertSame('La vigencia del contrato no puede ser anterior a la fecha del contrato.', $this->primerError($prueba, 'acreditacionForm.vigenciaContrato'));
    }

    public function test_paso2_documentos_observaciones_conserva_parrafos(): void
    {
        Livewire::test(Paso2Documentos::class, ['escuela' => $this->escuelaConResponsable()])
            ->set('acreditacionForm.observaciones', "  Primera  línea\r\n\r\n\r\nSegunda ")
            ->assertSet('acreditacionForm.observaciones', "Primera línea\n\nSegunda");
    }

    public function test_paso2_documentos_normaliza_los_datos_que_compara_el_motor_documental(): void
    {
        Livewire::test(Paso2Documentos::class, ['escuela' => $this->escuelaConResponsable()])
            ->set('ineForm.curp', ' pegj800101hqtrml09 ')
            ->set('constanciaCurpForm.curp', 'pegj-800101-hqtrml09')
            ->set('situacionFiscalForm.rfc', 'pegj 800101 ab1')
            ->set('numeroOficialForm.codigoPostal', '76 000')
            ->assertSet('ineForm.curp', 'PEGJ800101HQTRML09')
            ->assertSet('constanciaCurpForm.curp', 'PEGJ800101HQTRML09')
            ->assertSet('situacionFiscalForm.rfc', 'PEGJ800101AB1')
            ->assertSet('numeroOficialForm.codigoPostal', '76000')
            ->assertHasNoErrors();
    }

    public function test_paso2_documentos_los_datos_del_motor_usan_los_mismos_formatos_y_mensajes(): void
    {
        $prueba = Livewire::test(Paso2Documentos::class, ['escuela' => $this->escuelaConResponsable()])
            ->set('ineForm.curp', 'PEGJ801301HQTRML09')
            ->set('situacionFiscalForm.rfc', 'PEGJ8001')
            ->set('numeroOficialForm.codigoPostal', '00123')
            ->assertHasErrors(['ineForm.curp', 'situacionFiscalForm.rfc', 'numeroOficialForm.codigoPostal']);

        $this->assertSame('La CURP no tiene un formato válido: son 18 caracteres, por ejemplo GOMA800101HQTRRL09.', $this->primerError($prueba, 'ineForm.curp'));
        $this->assertSame('El RFC no tiene un formato válido: son 13 caracteres para persona física o 12 para persona moral.', $this->primerError($prueba, 'situacionFiscalForm.rfc'));
        $this->assertSame('El código postal debe tener 5 dígitos, por ejemplo 76000.', $this->primerError($prueba, 'numeroOficialForm.codigoPostal'));
    }

    public function test_paso2_documentos_los_datos_del_motor_muestran_todos_los_errores_en_una_sola_ronda(): void
    {
        $prueba = Livewire::test(Paso2Documentos::class, ['escuela' => $this->escuelaConResponsable()]);

        $prueba->call('guardarIne')->assertHasErrors(['archivos.ine', 'ineForm.nombre', 'ineForm.curp']);
        $prueba->call('guardarSituacionFiscal')->assertHasErrors(['archivos.constancia_situacion_fiscal', 'situacionFiscalForm.rfc']);
        $prueba->call('guardarNumeroOficial')->assertHasErrors(['archivos.certificado_numero_oficial', 'numeroOficialForm.calle']);
    }

    public function test_paso2_responsable_normaliza_y_valida_la_curp_del_gestor(): void
    {
        $prueba = Livewire::test(Paso2Responsable::class, ['escuela' => $this->escuela()])
            ->set('tipoPersona', 'fisica_con_gestor')
            ->set('gestorForm.curp', ' gomc800101hqtmrr01 ')
            ->assertSet('gestorForm.curp', 'GOMC800101HQTMRR01')
            ->assertHasNoErrors('gestorForm.curp')
            ->set('gestorForm.curp', 'GOMC80')
            ->assertHasErrors(['gestorForm.curp']);

        $this->assertSame('La CURP del gestor no tiene un formato válido: son 18 caracteres, por ejemplo GOMA800101HQTRRL09.', $this->primerError($prueba, 'gestorForm.curp'));
    }

    // --- Paso 2.4 (WS-5b) ---------------------------------------------------

    public function test_paso24_recibo_de_pago_valida_monto_fecha_y_folio(): void
    {
        $prueba = Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $this->escuelaNivel(paso24: false)])
            ->set('recibo.folio', '  f-0001 ')
            ->set('recibo.monto', '1500.555')
            ->set('recibo.fechaPago', '2999-01-01')
            ->assertSet('recibo.folio', 'f-0001')
            ->assertHasErrors(['recibo.monto', 'recibo.fechaPago']);

        $this->assertSame('El campo monto pagado admite como máximo 2 decimales.', $this->primerError($prueba, 'recibo.monto'));
        $this->assertSame('La fecha de pago no puede ser posterior a hoy.', $this->primerError($prueba, 'recibo.fechaPago'));
    }

    public function test_paso24_turno_y_tipo_de_alumnado_se_validan_al_capturar(): void
    {
        $prueba = Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $this->escuelaNivel(paso24: false)])
            ->set('turno', 'madrugada')
            ->assertHasErrors(['turno']);

        $this->assertSame('Selecciona una opción válida en turno.', $this->primerError($prueba, 'turno'));
    }

    public function test_paso24_el_recibo_muestra_todos_los_errores_en_una_sola_ronda(): void
    {
        Livewire::test(Paso24DocumentosNivel::class, ['escuelaNivel' => $this->escuelaNivel(paso24: false)])
            ->call('guardarRecibo')
            ->assertHasErrors(['archivos.recibo_pago_derechos', 'recibo.folio', 'recibo.monto', 'recibo.fechaPago']);
    }

    // --- Paso 3 -------------------------------------------------------------

    private function escuelaNivel(bool $inmuebleCompletado = false, bool $paso24 = true): EscuelaNivel
    {
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();
        (new TiposEspaciosSeeder)->run();
        $escuela = $this->escuela();
        $this->completarPaso2($escuela->id);

        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
        if ($paso24) {
            $this->completarPaso24($escuelaNivel->id);
        }

        if ($inmuebleCompletado) {
            (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'inmueble');
        }

        return $escuelaNivel;
    }

    public function test_paso3_inmueble_respeta_la_precision_de_las_columnas(): void
    {
        $prueba = Livewire::test(DatosInmueble::class, ['escuelaNivel' => $this->escuelaNivel()])
            ->set('metrosTotales', '100000000')
            ->set('areaCivicaM2', '10.555')
            ->set('latitud', '20.58806221')
            ->call('agregarServicio')
            ->set('serviciosCercanos.0.distanciaValor', '10000')
            ->assertHasErrors(['metrosTotales', 'areaCivicaM2', 'latitud', 'serviciosCercanos.0.distanciaValor']);

        $this->assertSame('El campo superficie del predio no debe ser mayor que 99999999.99.', $this->primerError($prueba, 'metrosTotales'));
        $this->assertSame('El campo latitud admite como máximo 7 decimales.', $this->primerError($prueba, 'latitud'));
    }

    public function test_paso3_inmueble_latitud_y_longitud_van_juntas(): void
    {
        $prueba = Livewire::test(DatosInmueble::class, ['escuelaNivel' => $this->escuelaNivel()])
            ->set('metrosTotales', '500')
            ->set('latitud', '20.5880622')
            ->call('guardar')
            ->assertHasErrors(['longitud']);

        $this->assertSame('El campo longitud es obligatorio.', $this->primerError($prueba, 'longitud'));
    }

    public function test_paso3_infraestructura_respeta_la_precision_de_las_columnas(): void
    {
        Livewire::test(InfraestructuraNivel::class, ['escuelaNivel' => $this->escuelaNivel(inmuebleCompletado: true)])
            ->set('numeroAulas', '40000')
            ->set('superficieAulasM2', '100000000')
            ->set('sanitarios.alumnado_masculino.superficieM2', '1000000')
            ->set('materialesBiblioteca.1.numeroTitulos', '3000000000')
            ->assertHasErrors([
                'numeroAulas',
                'superficieAulasM2',
                'sanitarios.alumnado_masculino.superficieM2',
                'materialesBiblioteca.1.numeroTitulos',
            ]);
    }
}
