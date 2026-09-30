<?php

namespace Tests\Feature\Application\Validaciones;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Application\Validaciones\ConstruirContextoValidacion;
use App\Application\Validaciones\EjecutarValidacionDocumental;
use App\Application\Validaciones\RegistrarHechoDocumento;
use App\Domain\Validaciones\Documental\TipoHecho;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EjecutarValidacionDocumentalTest extends TestCase
{
    use RefreshDatabase;

    private const CURP = 'PEGJ800101HQTRML09';

    private int $escuelaId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();

        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuelaId = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id])->id;
    }

    private function responsable(string $tipoPersona = 'fisica'): void
    {
        (new RegistrarResponsableLegal)->ejecutar($this->escuelaId, new DatosResponsableLegal(
            tipoPersona: $tipoPersona,
            nombre: 'Juan Pérez Gómez',
            curp: self::CURP,
            razonSocial: $tipoPersona === 'moral' ? 'Colegio Ejemplo A.C.' : null,
            nombreRepresentanteLegal: $tipoPersona === 'moral' ? 'Juan Pérez Gómez' : null,
            gestorNombre: $tipoPersona === 'fisica_con_gestor' ? 'Ana López' : null,
        ));
    }

    private function subir(string $clave): void
    {
        app(RegistrarDocumento::class)->ejecutar($this->escuelaId, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
    }

    private function subirTodos(string $tipoPersona = 'fisica'): void
    {
        foreach (app(DocumentosCompletos::class)->clavesAplicables($tipoPersona) as $clave) {
            $this->subir($clave);
        }
    }

    private function hechoIne(string $tipoHecho, string $valor): void
    {
        app(RegistrarHechoDocumento::class)->ejecutar($this->escuelaId, 'ine', $tipoHecho, $valor);
    }

    public function test_expediente_consistente_cumple_las_tres_reglas(): void
    {
        $this->responsable();
        $this->subirTodos();
        $this->hechoIne('nombre_titular', 'PEREZ GOMEZ JUAN');
        $this->hechoIne('curp', self::CURP);

        $reporte = app(EjecutarValidacionDocumental::class)->ejecutar($this->escuelaId);

        $this->assertSame(3, $reporte->ejecutadas());
        $this->assertSame(3, $reporte->contar(EstadoResultado::Cumple), json_encode($reporte->resultados));
    }

    public function test_reporta_cada_inconsistencia_sin_lanzar(): void
    {
        $this->responsable();
        $this->subir('ine');
        $this->hechoIne('nombre_titular', 'LOPEZ MARIA');
        $this->hechoIne('curp', 'LOMA800101MQTPRR01');

        $reporte = app(EjecutarValidacionDocumental::class)->ejecutar($this->escuelaId);

        $this->assertSame(EstadoResultado::Advertencia, $reporte->resultado('nombre_titular_coincide')?->estado);
        $this->assertSame(EstadoResultado::NoCumple, $reporte->resultado('curp_coincide')?->estado);
        $this->assertSame(EstadoResultado::NoCumple, $reporte->resultado('documentos_requeridos_presentes')?->estado);
        $this->assertNotContains('ine', $reporte->resultado('documentos_requeridos_presentes')?->detalles['faltantes']);
        $this->assertContains('acta_nacimiento', $reporte->resultado('documentos_requeridos_presentes')?->detalles['faltantes']);
    }

    public function test_sin_hechos_capturados_las_reglas_de_identidad_son_no_evaluables(): void
    {
        $this->responsable();
        $this->subirTodos();

        $reporte = app(EjecutarValidacionDocumental::class)->ejecutar($this->escuelaId);

        $this->assertSame(EstadoResultado::NoEvaluable, $reporte->resultado('nombre_titular_coincide')?->estado);
        $this->assertSame(EstadoResultado::NoEvaluable, $reporte->resultado('curp_coincide')?->estado);
        $this->assertFalse($reporte->tieneNoCumplimientos());
    }

    public function test_los_hechos_de_un_archivo_reemplazado_se_descartan(): void
    {
        $this->responsable();
        $this->subir('ine');
        $this->hechoIne('curp', self::CURP);

        // A new file replaces the old one in the same documentos_escuela row;
        // the CURP captured from the previous file must not validate it.
        $this->subir('ine');

        $contexto = app(ConstruirContextoValidacion::class)->ejecutar($this->escuelaId);

        $this->assertNull($contexto->deDocumento(TipoHecho::Curp, 'ine'));
        $this->assertSame(self::CURP, $contexto->declarado(TipoHecho::Curp)?->valor);
    }

    public function test_el_contexto_toma_nombre_y_curp_declarados_de_la_persona_fisica(): void
    {
        $this->responsable();

        $contexto = app(ConstruirContextoValidacion::class)->ejecutar($this->escuelaId);

        $this->assertSame('fisica', $contexto->tipoPersona);
        $this->assertSame('Juan Pérez Gómez', $contexto->declarado(TipoHecho::NombreTitular)?->valor);
        $this->assertSame(self::CURP, $contexto->declarado(TipoHecho::Curp)?->valor);
        $this->assertSame(app(DocumentosCompletos::class)->clavesAplicables('fisica'), $contexto->clavesRequeridas);
        $this->assertSame([], $contexto->clavesPresentes);
    }

    public function test_los_hechos_de_otra_escuela_no_se_mezclan(): void
    {
        $this->responsable();
        $this->subir('ine');
        $this->hechoIne('curp', self::CURP);

        $otroPlantel = Plantel::create(['calle' => 'Calle 2', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $otraEscuelaId = Escuela::create(['plantel_id' => $otroPlantel->id, 'solicitante_id' => Solicitante::factory()->create()->id])->id;
        (new RegistrarResponsableLegal)->ejecutar($otraEscuelaId, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Otra Persona'));

        $contexto = app(ConstruirContextoValidacion::class)->ejecutar($otraEscuelaId);

        $this->assertNull($contexto->deDocumento(TipoHecho::Curp, 'ine'));
    }

    public function test_persona_moral_y_gestor_quedan_no_evaluables_en_identidad(): void
    {
        $this->responsable('moral');
        $this->subir('ine');
        $this->hechoIne('nombre_titular', 'PEREZ GOMEZ JUAN');

        $reporte = app(EjecutarValidacionDocumental::class)->ejecutar($this->escuelaId);

        $this->assertSame(EstadoResultado::NoEvaluable, $reporte->resultado('nombre_titular_coincide')?->estado);
        $this->assertSame(EstadoResultado::NoEvaluable, $reporte->resultado('curp_coincide')?->estado);
    }

    public function test_sin_responsable_legal_no_hay_contexto(): void
    {
        $this->expectException(PrecondicionIncumplida::class);

        app(EjecutarValidacionDocumental::class)->ejecutar($this->escuelaId);
    }
}
