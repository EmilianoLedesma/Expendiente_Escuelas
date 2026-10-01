<?php

namespace Tests\Feature\Validacion;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Infraestructura\DTO\DatosInfraestructuraNivel;
use App\Application\Infraestructura\RegistrarInfraestructuraNivel;
use App\Application\Inmueble\DTO\DatosInmueble;
use App\Application\Inmueble\RegistrarDatosInmueble;
use App\Application\Preregistro\DTO\DatosPreregistro;
use App\Application\Preregistro\IniciarTramiteNuevo;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Models\DocumentoEscuela;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\ResponsableLegal;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Database\Seeders\TiposDocumentosSeeder;
use Database\Seeders\TiposEspaciosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CompletaPaso2;
use Tests\Concerns\CompletaPaso24;
use Tests\TestCase;

/**
 * Los casos de uso no confían en que la pantalla haya validado: un adaptador
 * que se salte el formulario (la futura API, un script) recibe
 * DatosInvalidos con la misma ruta de campo que el formulario, nunca un
 * error de base de datos ni un dato mal formado guardado.
 */
class InvariantesDeCapturaTest extends TestCase
{
    use CompletaPaso2;
    use CompletaPaso24;
    use RefreshDatabase;

    /** @return array<string, string> */
    private function erroresDe(callable $accion): array
    {
        try {
            $accion();
        } catch (DatosInvalidos $e) {
            return $e->errores;
        }

        $this->fail('Se esperaba DatosInvalidos.');
    }

    private function escuela(): Escuela
    {
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
    }

    public function test_preregistro_rechaza_contacto_y_codigo_postal_mal_formados(): void
    {
        $solicitante = Solicitante::factory()->create();

        $errores = $this->erroresDe(fn () => (new IniciarTramiteNuevo)->ejecutar(new DatosPreregistro(
            bifurcacion: 'nuevo',
            calle: 'Av. Reforma',
            colonia: 'Centro',
            municipio: 'Querétaro',
            codigoPostal: '7600',
            telefono: '442-123',
            correoElectronico: 'no-es-correo',
        ), $solicitante->id));

        $this->assertSame(['codigoPostal', 'telefono', 'correoElectronico'], array_keys($errores));
        $this->assertSame(0, Plantel::count());
    }

    public function test_responsable_rechaza_identificadores_mal_formados_sin_escribir_nada(): void
    {
        $escuela = $this->escuela();

        $errores = $this->erroresDe(fn () => (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(
            tipoPersona: 'fisica',
            nombre: 'Juana Pérez',
            rfc: 'ABC800101AB1',
            curp: 'goma800101hqtrrl09',
        )));

        $this->assertSame(['personaFisicaForm.rfc', 'personaFisicaForm.curp'], array_keys($errores));
        $this->assertSame(0, ResponsableLegal::count());
    }

    public function test_responsable_rechaza_la_curp_del_gestor_mal_formada(): void
    {
        $errores = $this->erroresDe(fn () => (new RegistrarResponsableLegal)->ejecutar($this->escuela()->id, new DatosResponsableLegal(
            tipoPersona: 'fisica_con_gestor',
            nombre: 'Juana Pérez',
            gestorNombre: 'Carlos Gómez',
            gestorCurp: 'GOMC80',
        )));

        $this->assertSame(['gestorForm.curp'], array_keys($errores));
        $this->assertSame(0, ResponsableLegal::count());
    }

    public function test_documento_rechaza_datos_tipados_mal_formados_sin_escribir_nada(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->escuela();
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));
        $subir = fn (string $clave, DatosDocumento $datos) => $this->erroresDe(fn () => app(RegistrarDocumento::class)->ejecutar(
            $escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), $datos,
        ));

        $this->assertSame(['ineForm.curp'], array_keys($subir('ine', new DatosDocumento(identidadNombre: 'PEREZ JUANA', identidadCurp: 'NO ES CURP'))));
        $this->assertSame(['constanciaCurpForm.nombre'], array_keys($subir('constancia_curp', new DatosDocumento(identidadNombre: str_repeat('A', 201), identidadCurp: 'PEGJ800101HQTRML09'))));
        $this->assertSame(['situacionFiscalForm.rfc'], array_keys($subir('constancia_situacion_fiscal', new DatosDocumento(fiscalNombre: 'PEREZ JUANA', fiscalRfc: 'PEGJ800101AB12'))));
        $this->assertSame(['numeroOficialForm.calle', 'numeroOficialForm.codigoPostal'], array_keys($subir('certificado_numero_oficial', new DatosDocumento(
            domicilioCalle: str_repeat('A', 151), domicilioColonia: 'Centro', domicilioMunicipio: 'Querétaro', domicilioCodigoPostal: '7600',
        ))));
        $this->assertSame(0, DocumentoEscuela::count() + DocumentoPlantel::count());
    }

    public function test_responsable_exige_los_nombres_que_la_base_de_datos_exige(): void
    {
        $this->assertSame(['personaFisicaForm.nombre'], array_keys($this->erroresDe(
            fn () => (new RegistrarResponsableLegal)->ejecutar($this->escuela()->id, new DatosResponsableLegal(tipoPersona: 'fisica')),
        )));

        $this->assertSame(['personaFisicaForm.nombre', 'gestorForm.nombre'], array_keys($this->erroresDe(
            fn () => (new RegistrarResponsableLegal)->ejecutar($this->escuela()->id, new DatosResponsableLegal(tipoPersona: 'fisica_con_gestor')),
        )));

        $this->assertSame(['personaMoralForm.razonSocial', 'personaMoralForm.nombreRepresentanteLegal'], array_keys($this->erroresDe(
            fn () => (new RegistrarResponsableLegal)->ejecutar($this->escuela()->id, new DatosResponsableLegal(tipoPersona: 'moral')),
        )));
    }

    public function test_inmueble_rechaza_valores_que_no_caben_en_la_columna(): void
    {
        $errores = $this->erroresDe(fn () => app(RegistrarDatosInmueble::class)->ejecutar(1, 1, new DatosInmueble(
            metrosTotales: 100000000,
            metrosConstruidos: 100000000,
            areaCivicaM2: 100000000,
            serviciosCercanos: [['nombre' => 'Cruz Roja', 'tipo' => 'salud', 'esPublico' => true, 'distanciaValor' => 10000.0, 'distanciaUnidad' => 'm']],
            estudiosActuales: [['nivelEducativoId' => 1, 'otroNivelTexto' => null, 'numeroAlumnos' => 40000]],
        )));

        $this->assertSame([
            'metrosTotales',
            'metrosConstruidos',
            'areaCivicaM2',
            'serviciosCercanos.0.distanciaValor',
            'estudiosActuales.0.numeroAlumnos',
        ], array_keys($errores));
    }

    public function test_infraestructura_rechaza_valores_que_no_caben_en_la_columna(): void
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
        $this->completarPaso24($escuelaNivel->id);
        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'inmueble');

        $errores = $this->erroresDe(fn () => app(RegistrarInfraestructuraNivel::class)->ejecutar($escuela->plantel_id, $escuelaNivel->id, new DatosInfraestructuraNivel(
            espacios: [],
            sanitarios: [[
                'categoria' => 'alumnado_masculino',
                'cantidadRetretes' => 40000,
                'cantidadMingitorios' => null,
                'cantidadLavabos' => null,
                'superficieM2' => 1000000.0,
                'ventilacionNatural' => null,
                'iluminacionNatural' => null,
                'cantidadBacinicas' => null,
            ]],
            numeroAulas: 40000,
            superficieAulasM2: 100000000.0,
        )));

        $this->assertSame([
            'numeroAulas',
            'superficieAulasM2',
            'sanitarios.alumnado_masculino.cantidadRetretes',
            'sanitarios.alumnado_masculino.superficieM2',
        ], array_keys($errores));
    }
}
