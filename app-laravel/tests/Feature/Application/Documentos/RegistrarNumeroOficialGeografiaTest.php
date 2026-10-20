<?php

namespace Tests\Feature\Application\Documentos;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\Excepciones\DatosInvalidos;
use App\Models\CertificadoNumeroOficial;
use App\Models\DocumentoPlantel;
use App\Models\EntidadFederativa;
use App\Models\Escuela;
use App\Models\Municipio;
use App\Models\Plantel;
use App\Models\ResponsableLegal;
use App\Models\Solicitante;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

/**
 * Entidad federativa y municipio del certificado de número oficial desde el catálogo geográfico local
 * (ADR-017), el mismo de la notaría en la escritura. El nombre del municipio se deriva en el servidor del
 * catálogo; colonia y código postal siguen siendo texto (no hay catálogo oficial de INEGI para colonias).
 */
class RegistrarNumeroOficialGeografiaTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    private EntidadFederativa $queretaro;

    private EntidadFederativa $otra;

    private Municipio $corregidora;

    private Municipio $asientos;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $this->queretaro = EntidadFederativa::create(['clave' => 22, 'nombre' => 'QUERÉTARO']);
        $this->otra = EntidadFederativa::create(['clave' => 1, 'nombre' => 'AGUASCALIENTES']);
        $this->corregidora = Municipio::create(['entidad_federativa_id' => $this->queretaro->id, 'clave' => 6, 'nombre' => 'CORREGIDORA']);
        $this->asientos = Municipio::create(['entidad_federativa_id' => $this->otra->id, 'clave' => 2, 'nombre' => 'ASIENTOS']);
    }

    private function crearEscuela(): Escuela
    {
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
        ResponsableLegal::create(['escuela_id' => $escuela->id, 'tipo_persona' => 'fisica']);
        $this->elegirNivel($escuela->id);

        return $escuela;
    }

    /** @param  array<string, mixed>  $campos */
    private function datos(array $campos = []): DatosDocumento
    {
        return new DatosDocumento(...[
            'domicilioCalle' => 'Hidalgo', 'domicilioNumeroExt' => '12', 'domicilioColonia' => 'Centro', 'domicilioCodigoPostal' => '76900',
            'domicilioEntidadFederativaId' => $this->queretaro->id, 'domicilioMunicipioId' => $this->corregidora->id,
            ...$campos,
        ]);
    }

    private function registrar(Escuela $escuela, DatosDocumento $datos, string $nombre = 'cert.pdf'): void
    {
        (new RegistrarDocumento)->ejecutar($escuela->id, 'certificado_numero_oficial', UploadedFile::fake()->create($nombre, 10, 'application/pdf'), $datos);
    }

    private function fila(Escuela $escuela): CertificadoNumeroOficial
    {
        return CertificadoNumeroOficial::query()
            ->whereIn('documento_plantel_id', DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->select('id'))
            ->firstOrFail();
    }

    public function test_guarda_las_referencias_y_deriva_el_nombre_del_municipio_del_catalogo(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, $this->datos());

        $fila = $this->fila($escuela);
        $this->assertSame($this->queretaro->id, $fila->entidad_federativa_id);
        $this->assertSame($this->corregidora->id, $fila->municipio_id);
        $this->assertSame('CORREGIDORA', $fila->municipio, 'el texto que usa el motor de validación sale del catálogo');
    }

    public function test_el_texto_de_municipio_que_manda_el_llamador_se_ignora_si_hay_referencias(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, $this->datos(['domicilioMunicipio' => 'OTRO MUNICIPIO']));

        $this->assertSame('CORREGIDORA', $this->fila($escuela)->municipio);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function ubicacionesInvalidas(): array
    {
        return [
            'municipio de otra entidad' => ['otra_entidad', 'numeroOficialForm.municipioId'],
            'municipio inexistente' => ['municipio_inexistente', 'numeroOficialForm.municipioId'],
            'entidad inexistente' => ['entidad_inexistente', 'numeroOficialForm.entidadFederativaId'],
            'entidad sin municipio' => ['sin_municipio', 'numeroOficialForm.municipioId'],
            'municipio sin entidad' => ['sin_entidad', 'numeroOficialForm.entidadFederativaId'],
        ];
    }

    #[DataProvider('ubicacionesInvalidas')]
    public function test_rechaza_ubicaciones_incoherentes_sin_dejar_nada(string $caso, string $rutaDeError): void
    {
        $escuela = $this->crearEscuela();
        $campos = match ($caso) {
            'otra_entidad' => ['domicilioMunicipioId' => $this->asientos->id],
            'municipio_inexistente' => ['domicilioMunicipioId' => 999999],
            'entidad_inexistente' => ['domicilioEntidadFederativaId' => 999999],
            'sin_municipio' => ['domicilioMunicipioId' => null],
            'sin_entidad' => ['domicilioEntidadFederativaId' => null],
        };

        try {
            $this->registrar($escuela, $this->datos($campos));
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey($rutaDeError, $e->errores);
        }

        $this->assertDatabaseCount('documentos_plantel', 0);
        $this->assertDatabaseCount('certificados_numero_oficial', 0);
        $this->assertSame([], Storage::disk('documentos')->allFiles());
    }

    public function test_un_llamador_anterior_con_municipio_en_texto_sigue_funcionando(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, new DatosDocumento(domicilioCalle: 'Hidalgo', domicilioColonia: 'Centro', domicilioMunicipio: 'QUERETARO', domicilioCodigoPostal: '76000'));

        $fila = $this->fila($escuela);
        $this->assertSame('QUERETARO', $fila->municipio);
        $this->assertNull($fila->entidad_federativa_id);
        $this->assertNull($fila->municipio_id);
    }

    public function test_las_columnas_nuevas_son_nullable_y_las_filas_anteriores_no_se_tocan(): void
    {
        $this->assertTrue(Schema::hasColumn('certificados_numero_oficial', 'entidad_federativa_id'));
        $this->assertTrue(Schema::hasColumn('certificados_numero_oficial', 'municipio_id'));

        $migracion = require database_path('migrations/2026_10_20_000400_add_geografia_to_certificados_numero_oficial.php');
        $escuela = $this->crearEscuela();
        $this->registrar($escuela, new DatosDocumento(domicilioCalle: 'Hidalgo', domicilioColonia: 'Centro', domicilioMunicipio: 'QUERETARO', domicilioCodigoPostal: '76000'));

        $migracion->down();
        $this->assertFalse(Schema::hasColumn('certificados_numero_oficial', 'municipio_id'));
        $this->assertSame('QUERETARO', DB::table('certificados_numero_oficial')->value('municipio'));
        $migracion->up();

        $this->assertNull($this->fila($escuela)->municipio_id);
        $this->assertSame('QUERETARO', $this->fila($escuela)->municipio);
    }

    public function test_reemplazar_con_ubicacion_invalida_conserva_el_certificado_anterior(): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar($escuela, $this->datos(), 'v1.pdf');
        $ruta = DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->value('archivo_path');

        try {
            $this->registrar($escuela, $this->datos(['domicilioMunicipioId' => $this->asientos->id]), 'v2.pdf');
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos) {
            // esperado
        }

        $this->assertSame($ruta, DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->value('archivo_path'));
        $this->assertSame($this->corregidora->id, $this->fila($escuela)->municipio_id);
    }
}
