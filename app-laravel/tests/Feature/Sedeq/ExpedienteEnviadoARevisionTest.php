<?php

namespace Tests\Feature\Sedeq;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\EscuelaNiveles\RegistrarNivelesSeleccionados;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Matricula\DTO\DatosMatricula;
use App\Application\Matricula\RegistrarMatricula;
use App\Application\Personal\DTO\DatosPersona;
use App\Application\Personal\RegistrarPlantillaDocente;
use App\Application\PlanEstudios\DTO\DatosPlanEstudios;
use App\Application\PlanEstudios\RegistrarPlanEstudios;
use App\Application\Preregistro\DTO\DatosPreregistro;
use App\Application\Preregistro\IniciarTramiteNuevo;
use App\Application\Revision\AsignarTramite;
use App\Application\Revision\ConsultarTramiteSedeq;
use App\Application\Tramite\EnviarTramite;
use App\Application\Tramite\EstadoPaso2;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\EvaluacionValidacion;
use App\Models\NivelEducativo;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\AsignaturasSeeder;
use Database\Seeders\CargosPuestosSeeder;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\GradosSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

/**
 * Prueba integral (spec docs/specs/2026-10-20-paso2-integracion-end-to-end.md §7 y encargo §28): el solicitante captura
 * en el orden nuevo por los casos de uso reales, valida y envía; SEDEQ lee una representación fiel —datos, metadatos y
 * el resultado guardado al envío— sin escribir nada, con permisos por expediente y por nivel.
 */
class ExpedienteEnviadoARevisionTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private Solicitante $solicitante;

    private Escuela $escuela;

    private EscuelaNivel $primaria;

    private EscuelaNivel $preescolar;

    protected function setUp(): void
    {
        parent::setUp();
        // Catálogos que usa la captura; sin reglas_validacion: la capacidad instalada exige capturar infraestructura,
        // que no es el objeto de esta prueba (EnviarTramiteTest cubre que la capacidad bloquea).
        foreach ([CatalogoMinimoSeeder::class, PasosCapturaSeeder::class, GradosSeeder::class, CargosPuestosSeeder::class, AsignaturasSeeder::class, TiposDocumentosSeeder::class, RolesSeeder::class] as $seeder) {
            $this->seed($seeder);
        }
        Storage::fake('documentos');
        $this->solicitante = Solicitante::factory()->create();

        // 1. Prerregistro.
        $resultado = app(IniciarTramiteNuevo::class)->ejecutar(new DatosPreregistro(
            bifurcacion: 'nuevo', calle: 'Av. Juárez', numeroExt: '12', colonia: 'Centro', municipio: 'Querétaro', codigoPostal: '76000',
        ), $this->solicitante->id);
        $this->escuela = Escuela::findOrFail($resultado->escuelaId);

        // 2. Responsable (sin niveles aún: los documentos generales todavía no se aceptan).
        $this->registrarSoloResponsable($this->escuela);
        $this->assertSame(EstadoPaso2::NIVELES, app(EstadoPaso2::class)->etapaFaltante($this->escuela->id));

        // 3. Niveles antes que los documentos.
        app(RegistrarNivelesSeleccionados::class)->ejecutar($this->escuela->id, agregar: [$this->idNivel('primaria'), $this->idNivel('preescolar')]);
        $this->primaria = $this->nivelDeClave('primaria');
        $this->preescolar = $this->nivelDeClave('preescolar');

        // 4. Documentos generales con sus datos.
        $this->subirTodosConDatos($this->escuela);
        $this->assertTrue(app(EstadoPaso2::class)->generalesSatisfechos($this->escuela->id));

        // 5-6. Documentos por nivel y Paso 3 de cada nivel.
        foreach ([$this->primaria, $this->preescolar] as $nivel) {
            $this->completarPaso24($nivel->id);
            $this->capturarPaso3($nivel);
        }

        // 7-9. Validación final y envío.
        $validacion = app(EjecutarValidacionFinal::class)->ejecutar($this->escuela->id);
        $this->assertTrue($validacion->listaParaEnvio, implode('; ', $validacion->motivosBloqueo()));
        app(EnviarTramite::class)->ejecutar($this->escuela->id, $this->solicitante->user->id);
    }

    private function idNivel(string $clave): int
    {
        return (int) NivelEducativo::where('clave', $clave)->value('id');
    }

    private function nivelDeClave(string $clave): EscuelaNivel
    {
        return EscuelaNivel::where('escuela_id', $this->escuela->id)->where('nivel_educativo_id', $this->idNivel($clave))->firstOrFail();
    }

    private function capturarPaso3(EscuelaNivel $nivel): void
    {
        foreach (['inmueble', 'infraestructura', 'mobiliario'] as $paso) {
            (new MarcarPasoCompletado)->ejecutar($nivel->id, $paso);
        }
        app(RegistrarPlanEstudios::class)->ejecutar($nivel->id, new DatosPlanEstudios(modalidad: 'escolarizada'));
        $cargo = fn (string $nombre): int => (int) DB::table('cargos_puestos')->where('nivel_educativo_id', $nivel->nivel_educativo_id)->where('nombre', $nombre)->value('id');
        app(RegistrarPlantillaDocente::class)->ejecutar($nivel->id, [
            new DatosPersona($cargo('Director Técnico'), 'Laura Méndez', 'Mexicana', 'F', 'Lic. en Educación', '1234567'),
            new DatosPersona($cargo('Docente Titular de Grupo'), 'Luis Pérez', 'Mexicana', 'M', 'Lic. en Educación', '7654321'),
        ]);
        $primero = (int) DB::table('grados')->where('nivel_educativo_id', $nivel->nivel_educativo_id)->where('orden', 1)->value('id');
        app(RegistrarMatricula::class)->ejecutar($nivel->id, new DatosMatricula(grupos: [['gradoId' => $primero, 'grupo' => 'A', 'alumnos' => 25]]));
    }

    private function asignar(EscuelaNivel ...$niveles): User
    {
        $revisor = $this->revisor();
        $supervisor = $this->supervisor();
        foreach ($niveles as $nivel) {
            app(AsignarTramite::class)->ejecutar($supervisor->id, $nivel->id, $revisor->id);
        }

        return $revisor;
    }

    public function test_el_envio_dejo_cada_nivel_en_revision_con_su_reporte(): void
    {
        foreach ([$this->primaria, $this->preescolar] as $nivel) {
            $this->assertSame('en_revision', DB::table('estados_expediente')->where('id', $nivel->fresh()->estado_id)->value('clave'));
        }
        // La de «validar» y la que guarda el propio envío (nunca confía en la anterior): la última es la enviada.
        $this->assertSame(2, EvaluacionValidacion::where('escuela_id', $this->escuela->id)->count());
        $this->assertTrue(EvaluacionValidacion::where('escuela_id', $this->escuela->id)->latest('id')->firstOrFail()->lista_para_envio);
    }

    public function test_un_revisor_autorizado_lee_datos_metadatos_y_el_resultado_guardado_sin_escribir(): void
    {
        $revisor = $this->asignar($this->primaria, $this->preescolar);
        $escrituras = 0;
        DB::listen(function ($consulta) use (&$escrituras) {
            if (preg_match('/^\s*(insert|update|delete)/i', $consulta->sql) === 1) {
                $escrituras++;
            }
        });

        $tramite = app(ConsultarTramiteSedeq::class)->ejecutar($revisor->id, $this->escuela->id);

        $this->assertSame(0, $escrituras, 'una consulta de solo lectura no escribe');
        $this->assertSame('Persona física', $tramite['responsable']['Tipo de persona']);
        $this->assertSame(self::CURP_TITULAR, $tramite['responsable']['CURP']);
        $ine = collect($tramite['documentos'])->firstWhere('clave', 'ine');
        $this->assertSame('PEREZ GOMEZ JUAN', collect($ine['metadatos'])->firstWhere('etiqueta', 'Nombre')['valor']);
        $this->assertSame(['Primaria', 'Preescolar'], array_column($tramite['niveles'], 'nombre'));
        $this->assertSame('Matutino', $tramite['niveles'][0]['datos']['Turno']);
        $this->assertSame(25, $tramite['niveles'][0]['datos']['Alumnos inscritos']);
        $recibo = collect($tramite['niveles'][0]['documentos'])->firstWhere('clave', 'recibo_pago_derechos');
        $this->assertSame("F-{$this->primaria->id}", collect($recibo['metadatos'])->firstWhere('etiqueta', 'Folio')['valor']);

        $this->assertNotNull($tramite['validacion']);
        $this->assertSame('almacenada_al_envio', $tramite['validacion']['origen']);
        $this->assertTrue($tramite['validacion']['listaParaEnvio']);
        $this->assertNotSame([], $tramite['validacion']['documental']);
        $this->assertSame([$this->primaria->id, $this->preescolar->id], array_map(fn ($s) => $s->escuelaNivelId, $tramite['validacion']['niveles']));
        $this->assertNotNull($tramite['validacion']['reporteHref']);
    }

    public function test_el_revisor_de_un_solo_nivel_no_ve_el_otro_nivel_ni_su_resultado_ni_el_pdf_completo(): void
    {
        $revisor = $this->asignar($this->primaria);

        $tramite = app(ConsultarTramiteSedeq::class)->ejecutar($revisor->id, $this->escuela->id);

        $this->assertSame(['Primaria'], array_column($tramite['niveles'], 'nombre'));
        $this->assertSame([$this->primaria->id], array_map(fn ($s) => $s->escuelaNivelId, $tramite['validacion']['niveles']));
        $this->assertSame([$this->primaria->id], array_map(fn ($s) => $s->escuelaNivelId, $tramite['validacion']['capacidad']));
        $this->assertNull($tramite['validacion']['reporteHref'], 'el PDF cubre todos los niveles');

        $this->actingAs($revisor)->get(route('sedeq.tramite.reporte', ['escuela' => $this->escuela->id]))->assertForbidden();
        $this->actingAs($revisor)->get(route('tramite.paso2-nivel-documentos.descargar', ['escuelaNivel' => $this->preescolar->id, 'clave' => 'recibo_pago_derechos']))->assertForbidden();
        $this->actingAs($revisor)->get(route('tramite.paso2-nivel-documentos.descargar', ['escuelaNivel' => $this->primaria->id, 'clave' => 'recibo_pago_derechos']))->assertOk();
        // Los documentos generales son de la escuela, compartidos por sus niveles (AccesoSedeq): un nivel asignado basta.
        $this->actingAs($revisor)->get(route('tramite.paso2-documentos.descargar', ['escuela' => $this->escuela->id, 'clave' => 'ine']))->assertOk();
    }

    public function test_la_pagina_sedeq_muestra_metadatos_y_el_resultado_rotulado_como_almacenado(): void
    {
        $revisor = $this->asignar($this->primaria, $this->preescolar);

        $this->actingAs($revisor)->get(route('sedeq.tramite', ['escuela' => $this->escuela->id]))
            ->assertOk()
            ->assertSee('PEREZ GOMEZ JUAN')
            ->assertSee('Resultado de validación almacenado al envío')
            ->assertSee('Matutino')
            ->assertSee(route('sedeq.tramite.reporte', ['escuela' => $this->escuela->id]), false);
    }

    public function test_el_supervisor_y_el_revisor_completo_descargan_el_pdf_y_los_documentos(): void
    {
        $revisor = $this->asignar($this->primaria, $this->preescolar);
        $documento = route('tramite.paso2-documentos.descargar', ['escuela' => $this->escuela->id, 'clave' => 'ine']);

        foreach ([$this->supervisor(), $revisor] as $usuario) {
            $this->actingAs($usuario)->get(route('sedeq.tramite.reporte', ['escuela' => $this->escuela->id]))
                ->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->actingAs($usuario)->get($documento)->assertOk();
        }
    }

    public function test_un_revisor_sin_asignacion_y_el_solicitante_reciben_denegacion(): void
    {
        $ajeno = $this->revisor();

        $this->actingAs($ajeno)->get(route('sedeq.tramite', ['escuela' => $this->escuela->id]))->assertForbidden();
        $this->actingAs($ajeno)->get(route('sedeq.tramite.reporte', ['escuela' => $this->escuela->id]))->assertForbidden();
        $this->actingAs($ajeno)->get(route('tramite.paso2-documentos.descargar', ['escuela' => $this->escuela->id, 'clave' => 'ine']))->assertForbidden();
        $this->actingAs($this->solicitante->user)->get(route('sedeq.tramite.reporte', ['escuela' => $this->escuela->id]))->assertForbidden();
    }

    public function test_tras_el_envio_el_solicitante_ya_no_puede_modificar_niveles_ni_documentos(): void
    {
        $this->actingAs($this->solicitante->user)->get(route('tramite.paso2-documentos', ['escuela' => $this->escuela->id]))->assertForbidden();

        $this->expectException(PrecondicionIncumplida::class);
        app(RegistrarDocumento::class)->ejecutar($this->escuela->id, 'plano_inmueble', UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'), new DatosDocumento);
    }

    public function test_un_revisor_no_ve_un_expediente_en_captura_aunque_tenga_su_id(): void
    {
        $otro = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($otro);
        $nivel = EscuelaNivel::where('escuela_id', $otro->id)->firstOrFail();
        $revisor = $this->revisor();

        // Asignar un nivel en captura se rechaza en el caso de uso; aunque existiera la fila, AccesoSedeq no lo deja ver.
        DB::table('asignaciones_revision')->insert(['escuela_nivel_id' => $nivel->id, 'revisor_id' => $revisor->id, 'asignado_por' => $this->supervisor()->id, 'asignado_at' => now()]);

        $this->actingAs($revisor)->get(route('sedeq.tramite', ['escuela' => $otro->id]))->assertForbidden();
        $this->assertContains('ine', app(DocumentosCompletos::class)->clavesAplicables('fisica'));
    }
}
