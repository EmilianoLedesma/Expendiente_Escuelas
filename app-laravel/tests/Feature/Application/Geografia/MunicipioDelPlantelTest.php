<?php

namespace Tests\Feature\Application\Geografia;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Geografia\ListarCatalogoGeografico;
use App\Application\Preregistro\DTO\DatosPreregistro;
use App\Application\Preregistro\IniciarTramiteNuevo;
use App\Models\Municipio;
use App\Models\Plantel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConCatalogoGeografico;
use Tests\TestCase;

/**
 * planteles.municipio_id (ADR-008 + ADR-017, spec 2026-10-20 §8): el municipio del plantel sale del catálogo local,
 * limitado a Querétaro. El texto `municipio` se conserva y se deriva del catálogo en el servidor; un texto heredado
 * sin id sigue aceptándose (mismo criterio que el certificado de número oficial).
 */
class MunicipioDelPlantelTest extends TestCase
{
    use ConCatalogoGeografico;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogoGeografico();
    }

    private function datos(?int $municipioId, ?string $municipio = null): DatosPreregistro
    {
        return new DatosPreregistro(bifurcacion: 'nuevo', calle: 'Av. Hidalgo', numeroExt: '12', colonia: 'Centro', municipio: $municipio, municipioId: $municipioId, codigoPostal: '76000');
    }

    private function iniciar(DatosPreregistro $datos): Plantel
    {
        $resultado = app(IniciarTramiteNuevo::class)->ejecutar($datos, Solicitante::factory()->create()->id);

        return Plantel::findOrFail($resultado->plantelId);
    }

    public function test_la_columna_es_una_fk_nullable_al_catalogo_que_se_anula_al_borrar_el_municipio(): void
    {
        $columna = DB::selectOne("SELECT is_nullable FROM information_schema.columns WHERE table_name = 'planteles' AND column_name = 'municipio_id'");
        $this->assertSame('YES', $columna->is_nullable);

        $fk = DB::selectOne("SELECT confdeltype FROM pg_constraint WHERE conrelid = 'planteles'::regclass AND contype = 'f' AND conkey = ARRAY[(SELECT attnum FROM pg_attribute WHERE attrelid = 'planteles'::regclass AND attname = 'municipio_id')]::smallint[]");
        $this->assertSame('n', $fk->confdeltype, 'ON DELETE SET NULL');
    }

    public function test_guarda_el_id_y_deriva_el_nombre_del_catalogo_ignorando_el_texto_del_cliente(): void
    {
        $corregidora = $this->municipio('CORREGIDORA');

        $plantel = $this->iniciar($this->datos($corregidora->id, 'Texto forjado'));

        $this->assertSame($corregidora->id, $plantel->municipio_id);
        $this->assertSame('CORREGIDORA', $plantel->municipio);
    }

    public function test_rechaza_un_municipio_de_otra_entidad_sin_escribir(): void
    {
        try {
            $this->iniciar($this->datos($this->municipio('CELAYA', $this->guanajuato)->id));
            $this->fail('Debió rechazar un municipio fuera de Querétaro.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey('municipioId', $e->errores);
        }

        $this->assertDatabaseCount('planteles', 0);
    }

    public function test_rechaza_un_id_inexistente(): void
    {
        $this->expectException(DatosInvalidos::class);

        $this->iniciar($this->datos(Municipio::max('id') + 100));
    }

    public function test_un_texto_heredado_sin_id_se_conserva_sin_vincular(): void
    {
        $plantel = $this->iniciar($this->datos(null, 'Querétaro'));

        $this->assertNull($plantel->municipio_id);
        $this->assertSame('Querétaro', $plantel->municipio);
    }

    public function test_el_catalogo_de_queretaro_lista_solo_sus_municipios(): void
    {
        $nombres = array_column(app(ListarCatalogoGeografico::class)->municipiosDeQueretaro(), 'nombre');

        $this->assertSame(['CORREGIDORA', 'QUERÉTARO', 'SAN JUAN DEL RÍO'], $nombres);
    }
}
