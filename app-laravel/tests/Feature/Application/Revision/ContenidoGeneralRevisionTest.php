<?php

namespace Tests\Feature\Application\Revision;

use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Application\Revision\ContenidoPasoRevision;
use App\Models\AcreditacionOcupacionLegal;
use App\Models\AsignacionRevision;
use App\Models\ConstanciaSeguridadEstructural;
use App\Models\DocumentoEscuelaNivel;
use App\Models\DocumentoPlantel;
use App\Models\EscuelaNivel;
use App\Models\ReciboPagoDerechos;
use App\Models\RelacionAcervoBibliografico;
use App\Models\TipoDocumento;
use App\Models\User;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

/** Lo que el solicitante capturó en Paso 2 (responsable, documentos y documentos del nivel) llega completo al revisor. */
class ContenidoGeneralRevisionTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private EscuelaNivel $nivel;

    private User $sup;

    protected function setUp(): void
    {
        parent::setUp();
        (new TiposDocumentosSeeder)->run();
        $this->nivel = $this->nivelEnRevision('en_captura');
        $this->sup = $this->supervisor();
    }

    private function enRevision(): void
    {
        $this->nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);
    }

    private function registrar(DatosResponsableLegal $datos): void
    {
        (new RegistrarResponsableLegal)->ejecutar($this->nivel->escuela_id, $datos);
        $this->enRevision();
    }

    /** @return list<array{0: string, 1: string}> */
    private function filas(string $clave, string $bloque): array
    {
        $bloques = app(ContenidoPasoRevision::class)->ejecutar($this->sup->id, $this->nivel->id, $clave)['bloques'];

        return array_column($bloques, 'filas', 'titulo')[$bloque] ?? [];
    }

    public function test_responsable_moral_muestra_acta_notario_registro_domicilio_autorizado_y_terna(): void
    {
        $this->registrar(new DatosResponsableLegal(
            tipoPersona: 'moral', domicilioNotificaciones: 'Av. Universidad 10', personaAutorizadaRecoger: 'Luis Gómez',
            nombrePropuesto1: 'Colegio Alfa', nombrePropuesto2: 'Colegio Beta', nombrePropuesto3: 'Colegio Gama',
            razonSocial: 'Educación SA de CV', numeroEscrituraConstitutiva: '1234', fechaEscrituraConstitutiva: '2020-03-15',
            notarioNombre: 'Ana Notaria', notarioNumero: '7', notarioCiudad: 'Querétaro',
            folioRegistroPublico: 'RPP-99', fechaInscripcionRpp: '2020-04-01', nombreRepresentanteLegal: 'Rosa Rep',
        ));

        $responsable = $this->filas('responsable', 'Responsable legal');

        $this->assertContains(['Tipo de persona', 'Persona moral'], $responsable);
        $this->assertContains(['Razón social', 'Educación SA de CV'], $responsable);
        $this->assertContains(['Representante legal', 'Rosa Rep'], $responsable);
        $this->assertContains(['Número de escritura constitutiva', '1234'], $responsable);
        $this->assertContains(['Fecha de escritura constitutiva', '15/03/2020'], $responsable);
        $this->assertContains(['Notario', 'Ana Notaria'], $responsable);
        $this->assertContains(['Número de notaría', '7'], $responsable);
        $this->assertContains(['Ciudad de la notaría', 'Querétaro'], $responsable);
        $this->assertContains(['Folio del Registro Público', 'RPP-99'], $responsable);
        $this->assertContains(['Fecha de inscripción en el Registro Público', '01/04/2020'], $responsable);
        $this->assertContains(['Domicilio para notificaciones', 'Av. Universidad 10'], $responsable);
        $this->assertContains(['Persona autorizada a recoger', 'Luis Gómez'], $responsable);
        $this->assertSame([['Propuesta 1', 'Colegio Alfa'], ['Propuesta 2', 'Colegio Beta'], ['Propuesta 3', 'Colegio Gama']], $this->filas('responsable', 'Terna de nombres propuestos'));
    }

    public function test_responsable_fisica_con_gestor_muestra_fecha_rfc_curp_y_el_gestor(): void
    {
        $this->registrar(new DatosResponsableLegal(
            tipoPersona: 'fisica_con_gestor', domicilioNotificaciones: 'Calle 1', nombre: 'Juana Pérez', fechaNacimiento: '1980-02-03', rfc: 'PEPJ800203AB1', curp: 'PEPJ800203MQTRRN01',
            gestorNombre: 'Gaspar Gestor', gestorNumeroPoder: 'P-55', gestorNotarioNombre: 'Nora Notaria', gestorNotarioNumero: '12', gestorFechaPoder: '2024-05-06', gestorCurp: 'GEGG900101HQTSSS02',
        ));

        $responsable = $this->filas('responsable', 'Responsable legal');
        $gestor = $this->filas('responsable', 'Gestor');

        $this->assertContains(['Tipo de persona', 'Persona física con gestor'], $responsable);
        $this->assertContains(['Nombre', 'Juana Pérez'], $responsable);
        $this->assertContains(['Fecha de nacimiento', '03/02/1980'], $responsable);
        $this->assertContains(['RFC', 'PEPJ800203AB1'], $responsable);
        $this->assertContains(['CURP', 'PEPJ800203MQTRRN01'], $responsable);
        $this->assertContains(['Nombre del gestor', 'Gaspar Gestor'], $gestor);
        $this->assertContains(['Número de poder', 'P-55'], $gestor);
        $this->assertContains(['Notario', 'Nora Notaria'], $gestor);
        $this->assertContains(['Fecha del poder', '06/05/2024'], $gestor);
        $this->assertContains(['CURP del gestor', 'GEGG900101HQTSSS02'], $gestor);
    }

    public function test_documentos_muestra_los_datos_estructurados_de_la_acreditacion_y_la_constancia(): void
    {
        $this->registrar(new DatosResponsableLegal(tipoPersona: 'fisica', domicilioNotificaciones: 'Calle 1', nombre: 'Juana Pérez'));
        $plantelId = $this->nivel->escuela->plantel_id;
        $escritura = DocumentoPlantel::create(['plantel_id' => $plantelId, 'tipo_documento_id' => TipoDocumento::where('clave', 'escritura_inmueble')->value('id'), 'archivo_path' => 'plantel/e.pdf']);
        AcreditacionOcupacionLegal::create(['documento_plantel_id' => $escritura->id, 'tipo' => 'arrendamiento', 'arrendador_comodante' => 'Inmobiliaria Sol', 'arrendatario_comodatario' => 'Juana Pérez', 'fecha_contrato' => '2024-01-10', 'vigencia_contrato' => '2029-01-10', 'ratificado_notario' => true]);
        $constancia = DocumentoPlantel::create(['plantel_id' => $plantelId, 'tipo_documento_id' => TipoDocumento::where('clave', 'constancia_seguridad_estructural')->value('id'), 'archivo_path' => 'plantel/c.pdf', 'fecha_emision' => '2026-02-20']);
        ConstanciaSeguridadEstructural::create(['documento_plantel_id' => $constancia->id, 'perito_nombre' => 'Pedro Perito', 'perito_registro_dro' => 'DRO-77', 'vigencia_constancia' => '2027-02-20', 'tipo_vigencia' => 'fecha_expresa']);

        $acreditacion = $this->filas('documentos', 'Datos de '.TipoDocumento::where('clave', 'escritura_inmueble')->value('nombre'));
        $seguridad = $this->filas('documentos', 'Datos de '.TipoDocumento::where('clave', 'constancia_seguridad_estructural')->value('nombre'));

        $this->assertContains(['Tipo de acreditación', 'Arrendamiento'], $acreditacion);
        $this->assertContains(['Arrendador / comodante', 'Inmobiliaria Sol'], $acreditacion);
        $this->assertContains(['Fecha del contrato', '10/01/2024'], $acreditacion);
        $this->assertContains(['Vigencia del contrato', '10/01/2029'], $acreditacion);
        $this->assertContains(['Ratificado ante notario', 'Sí'], $acreditacion);
        $this->assertContains(['Fecha de emisión', '20/02/2026'], $seguridad);
        $this->assertContains(['Nombre del perito', 'Pedro Perito'], $seguridad);
        $this->assertContains(['Registro DRO', 'DRO-77'], $seguridad);
        $this->assertContains(['Vigencia de la constancia', '20/02/2027'], $seguridad);
    }

    public function test_documentos_nivel_muestra_el_recibo_de_pago_y_el_acervo(): void
    {
        $this->registrar(new DatosResponsableLegal(tipoPersona: 'fisica', domicilioNotificaciones: 'Calle 1', nombre: 'Juana Pérez'));
        $recibo = DocumentoEscuelaNivel::create(['escuela_nivel_id' => $this->nivel->id, 'tipo_documento_id' => TipoDocumento::where('clave', 'recibo_pago_derechos')->value('id'), 'archivo_path' => 'nivel/r.pdf']);
        ReciboPagoDerechos::create(['documento_escuela_nivel_id' => $recibo->id, 'folio' => 'F-100', 'monto' => 1234.5, 'fecha_pago' => '2026-06-01', 'portal_referencia' => 'REF-9']);
        $acervo = DocumentoEscuelaNivel::create(['escuela_nivel_id' => $this->nivel->id, 'tipo_documento_id' => TipoDocumento::where('clave', 'acervo_bibliografico_primaria')->value('id'), 'archivo_path' => 'nivel/a.pdf']);
        RelacionAcervoBibliografico::create(['documento_escuela_nivel_id' => $acervo->id, 'numero_titulos' => 250]);

        $reciboFilas = $this->filas('documentos_nivel', 'Datos de '.TipoDocumento::where('clave', 'recibo_pago_derechos')->value('nombre'));
        $acervoFilas = $this->filas('documentos_nivel', 'Datos de '.TipoDocumento::where('clave', 'acervo_bibliografico_primaria')->value('nombre'));

        $this->assertContains(['Folio del recibo', 'F-100'], $reciboFilas);
        $this->assertContains(['Monto pagado', '$1,234.50'], $reciboFilas);
        $this->assertContains(['Fecha de pago', '01/06/2026'], $reciboFilas);
        $this->assertContains(['Referencia del portal de pago', 'REF-9'], $reciboFilas);
        $this->assertContains(['Número de títulos', '250'], $acervoFilas);
    }

    public function test_niveles_lista_todos_los_niveles_aunque_el_revisor_solo_tenga_asignado_uno(): void
    {
        $this->enRevision();
        $otro = $this->nivelEn($this->nivel->escuela, 'secundaria', 'en_revision');
        $revisor = $this->revisor();
        AsignacionRevision::create(['escuela_nivel_id' => $this->nivel->id, 'revisor_id' => $revisor->id, 'asignado_por' => $this->sup->id, 'asignado_at' => now()]);

        $filas = array_column(app(ContenidoPasoRevision::class)->ejecutar($revisor->id, $this->nivel->id, 'niveles')['bloques'][0]['filas'], 1, 0);

        $this->assertSame(['Primaria' => 'En revisión', 'Secundaria' => 'En revisión'], $filas);
        $this->assertNotNull($otro->id);
    }
}
