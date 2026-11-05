<?php

namespace Tests\Feature\Application\Reportes;

use App\Application\Archivo\ArchivarTramite;
use App\Application\Reportes\ConsultarReportes;
use App\Application\Revision\RolesSedeq;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\TernaNombre;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\TestCase;

class ConsultarReportesTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolesSeeder)->run();
        $this->admin = User::factory()->create()->assignRole(RolesSedeq::ADMIN);
    }

    /** Escuela con un nivel en el estado dado, en el municipio dado. */
    private function tramite(string $estado, string $municipio = 'Querétaro', ?string $nombre = null): Escuela
    {
        $plantel = Plantel::create(['calle' => 'Hidalgo', 'colonia' => 'Centro', 'municipio' => $municipio, 'codigo_postal' => '76000']);
        $escuela = $this->crearEscuelaConPlantel($plantel->id);
        $escuela->update(['nombre_aprobado' => $nombre]);
        $this->registrarResponsable($escuela);
        DB::table('escuela_niveles')->where('escuela_id', $escuela->id)
            ->update(['estado_id' => DB::table('estados_expediente')->where('clave', $estado)->value('id')]);

        return $escuela;
    }

    /** @return array<string, array{titulo: string, columnas: list<string>, filas: list<list<string|int>>}> */
    private function secciones(): array
    {
        return app(ConsultarReportes::class)->secciones();
    }

    /** @return array<string|int, mixed> primera columna => segunda, de una sección de dos columnas */
    private function mapa(string $seccion): array
    {
        return collect($this->secciones()[$seccion]['filas'])->mapWithKeys(fn ($f) => [$f[0] => $f[1]])->all();
    }

    public function test_por_estado_cuenta_niveles_y_rellena_con_cero(): void
    {
        $this->tramite('en_revision');
        $this->tramite('en_revision');
        $this->tramite('aprobado');

        $por = $this->mapa('por_estado');

        $this->assertSame(2, $por['En revisión']);
        $this->assertSame(1, $por['Aprobado']);
        $this->assertSame(0, $por['Rechazado']);
    }

    public function test_los_archivados_no_cuentan_en_estado_pero_si_en_activos_y_archivados(): void
    {
        $this->tramite('en_revision');
        $archivado = $this->tramite('en_revision');
        app(ArchivarTramite::class)->ejecutar($archivado->id, $this->admin->id);

        $this->assertSame(1, $this->mapa('por_estado')['En revisión']);
        $this->assertSame(['Activos' => 1, 'Archivados' => 1], $this->mapa('tramites'));
    }

    public function test_por_municipio_junta_mayusculas_y_minusculas(): void
    {
        $this->tramite('en_captura', 'Corregidora');
        $this->tramite('en_captura', 'CORREGIDORA');
        $this->tramite('en_captura', 'Querétaro');

        $por = $this->mapa('por_municipio');

        $this->assertSame(2, $por['Corregidora']);
        $this->assertSame(1, $por['Querétaro']);
    }

    public function test_esperando_al_solicitante_trae_nombre_dias_y_el_mas_antiguo_primero(): void
    {
        $viejo = $this->tramite('con_observaciones', nombre: 'Colegio Viejo');
        $nuevo = $this->tramite('con_observaciones');
        TernaNombre::create(['escuela_id' => $nuevo->id, 'numero_propuesta' => 1, 'nombre_propuesto' => 'Colegio Nuevo']);
        $this->tramite('en_revision');
        $obs = DB::table('estados_expediente')->where('clave', 'con_observaciones')->value('id');
        foreach ([[$viejo, 10], [$nuevo, 2]] as [$e, $dias]) {
            DB::table('historial_estados_expediente')->insert([
                'escuela_nivel_id' => DB::table('escuela_niveles')->where('escuela_id', $e->id)->value('id'),
                'estado_id' => $obs, 'comentario' => 'x', 'usuario_sedeq_id' => $this->admin->id, 'fecha' => now()->subDays($dias),
            ]);
        }

        $filas = $this->secciones()['esperando']['filas'];

        $this->assertCount(2, $filas);
        $this->assertSame('Colegio Viejo', $filas[0][0]);
        $this->assertSame(10, $filas[0][4]);
        $this->assertSame('Colegio Nuevo', $filas[1][0]);
        $this->assertSame('Primaria', $filas[0][2]);
    }

    public function test_carga_de_revision_cuenta_sin_asignar_y_por_revisor(): void
    {
        $revisor = User::factory()->create(['name' => 'Rita Revisora'])->assignRole(RolesSedeq::REVISOR);
        $asignado = $this->tramite('en_revision');
        $this->tramite('en_revision');
        DB::table('asignaciones_revision')->insert([
            'escuela_nivel_id' => DB::table('escuela_niveles')->where('escuela_id', $asignado->id)->value('id'),
            'revisor_id' => $revisor->id, 'asignado_por' => $this->admin->id, 'asignado_at' => now(),
        ]);

        $carga = $this->mapa('carga');

        $this->assertSame(2, $carga['Niveles en revisión']);
        $this->assertSame(1, $carga['Sin revisor asignado']);
        $this->assertSame(1, $carga['Revisor: Rita Revisora']);
    }

    /** @return list<list<string|null>> filas del CSV ya separadas, con el encabezado en la primera */
    private function filasCsv(string $seccion): array
    {
        $csv = app(ConsultarReportes::class)->csv($this->secciones()[$seccion]);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        return array_map('str_getcsv', array_values(array_filter(explode("\n", str_replace("\r", '', substr($csv, 3))))));
    }

    public function test_el_csv_es_el_detalle_por_registro_con_datos_del_plantel(): void
    {
        $e = $this->tramite('en_captura', 'Corregidora', 'Colegio Sol');
        DB::table('planteles')->where('id', $e->plantel_id)->update(['telefono' => '4421234567', 'correo_electronico' => 'plantel@example.test']);

        $filas = $this->filasCsv('por_estado');

        $this->assertSame(['Trámite', 'Nivel', 'Estado'], array_slice($filas[0], 0, 3));
        $this->assertContains('Teléfono del plantel', $filas[0]);
        $this->assertCount(2, $filas); // encabezado + un nivel, no el resumen de estados
        $fila = array_combine($filas[0], $filas[1]);
        $this->assertSame('Colegio Sol', $fila['Trámite']);
        $this->assertSame('Primaria', $fila['Nivel']);
        $this->assertSame('En captura', $fila['Estado']);
        $this->assertSame('Hidalgo, Centro', $fila['Plantel']);
        $this->assertSame('Corregidora', $fila['Municipio']);
        $this->assertSame('4421234567', $fila['Teléfono del plantel']);
        $this->assertSame('plantel@example.test', $fila['Correo del plantel']);
        $this->assertNotSame('', $fila['Correo del solicitante']);
    }

    public function test_el_csv_de_tramites_incluye_archivados_con_su_motivo(): void
    {
        $activo = $this->tramite('en_revision', nombre: 'Colegio Activo');
        $archivado = $this->tramite('en_revision', nombre: 'Colegio Viejo');
        app(ArchivarTramite::class)->ejecutar($archivado->id, $this->admin->id, 'Duplicado');

        $filas = $this->filasCsv('tramites');
        $por = collect(array_slice($filas, 1))->map(fn ($f) => array_combine($filas[0], $f))->keyBy('Trámite');

        $this->assertSame('Activo', $por['Colegio Activo']['Situación']);
        $this->assertSame('Archivado', $por['Colegio Viejo']['Situación']);
        $this->assertSame('Duplicado', $por['Colegio Viejo']['Motivo del archivo']);
        $this->assertSame('Primaria (En revisión)', $por['Colegio Activo']['Niveles y estado']);
    }

    public function test_el_csv_de_esperando_y_de_carga_traen_dias_y_revisor(): void
    {
        $this->tramite('con_observaciones', nombre: 'Colegio Devuelto');
        $this->tramite('en_revision', nombre: 'Colegio En Revision');

        $esperando = $this->filasCsv('esperando');
        $carga = $this->filasCsv('carga');

        $this->assertContains('Días en espera', $esperando[0]);
        $this->assertSame('Colegio Devuelto', $esperando[1][0]);
        $this->assertSame('Sin asignar', array_combine($carga[0], $carga[1])['Revisor asignado']);
        $this->assertSame('Colegio En Revision', $carga[1][0]);
    }
}
