<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Infraestructura\DTO\DatosEdicionInfraestructura;
use App\Application\Infraestructura\DTO\DatosInfraestructuraNivel;
use App\Application\Infraestructura\EditarInfraestructuraCompartida;
use App\Application\Infraestructura\RegistrarInfraestructuraNivel;
use App\Application\Inmueble\DTO\DatosInmueble;
use App\Application\Inmueble\RegistrarDatosInmueble;
use App\Application\Matricula\DTO\DatosMatricula;
use App\Application\Matricula\RegistrarMatricula;
use App\Application\Mobiliario\RegistrarMobiliarioNivel;
use App\Application\Personal\DTO\DatosPersona;
use App\Application\Personal\RegistrarPlantillaDocente;
use App\Application\PlanEstudios\DTO\DatosPlanEstudios;
use App\Application\PlanEstudios\RegistrarPlanEstudios;
use App\Models\EscuelaNivel;
use Database\Seeders\MobiliarioConceptosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ConTramiteDevuelto;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

/**
 * Devolución, escritores de Paso 3: cada caso de uso escribe solo si SU paso está
 * reabierto para SU nivel, de extremo a extremo (por el caso de uso, no por la ruta).
 * El efecto se mide con escuela_nivel_pasos.updated_at: MarcarPasoCompletado lo
 * actualiza al final de la misma transacción, y un rechazo revierte todo.
 */
class DevolucionPaso3Test extends TestCase
{
    use ConTramiteDevuelto;
    use PreparaPaso3;
    use RefreshDatabase;

    private const ANTES = '2020-01-01 00:00:00';

    /** @return array<string, array{string}> */
    public static function pasos(): array
    {
        return array_combine(
            ['plan_estudios', 'plantilla_docente', 'matricula', 'mobiliario', 'inmueble', 'infraestructura'],
            array_map(fn ($p) => [$p], ['plan_estudios', 'plantilla_docente', 'matricula', 'mobiliario', 'inmueble', 'infraestructura']),
        );
    }

    /** Otro paso por nivel, distinto de $paso, para el caso «se rechazó otro paso». */
    private function otroPaso(string $paso): string
    {
        return $paso === 'matricula' ? 'plan_estudios' : 'matricula';
    }

    private function claveNivel(string $paso): string
    {
        return $paso === 'mobiliario' ? 'inicial' : 'primaria';
    }

    /** Nivel con Paso 2, 2.4 y todo Paso 3 completo (el estado real previo a una devolución). */
    private function nivelCompleto(string $claveNivel): EscuelaNivel
    {
        $nivel = $this->nivelListoPara($claveNivel, 'sin_paso_tope');
        (new MobiliarioConceptosSeeder)->run();
        $this->fijarAntes($nivel);

        return $nivel;
    }

    /** Segundo nivel en la misma escuela, también con Paso 3 completo. */
    private function segundoNivel(EscuelaNivel $primero, string $claveNivel): EscuelaNivel
    {
        $nivel = $this->nivelEn($primero->escuela, $claveNivel);
        $this->completarPaso24($nivel->id);
        foreach (DB::table('pasos_captura')->orderBy('orden')->pluck('clave') as $clave) {
            (new MarcarPasoCompletado)->ejecutar($nivel->id, (string) $clave);
        }
        $this->fijarAntes($nivel);

        return $nivel;
    }

    private function fijarAntes(EscuelaNivel $nivel): void
    {
        DB::table('escuela_nivel_pasos')->where('escuela_nivel_id', $nivel->id)->update(['updated_at' => self::ANTES]);
    }

    private function escribio(EscuelaNivel $nivel, string $paso): bool
    {
        return DB::table('escuela_nivel_pasos')
            ->join('pasos_captura', 'pasos_captura.id', '=', 'escuela_nivel_pasos.paso_captura_id')
            ->where('escuela_nivel_id', $nivel->id)->where('pasos_captura.clave', $paso)
            ->value('escuela_nivel_pasos.updated_at') > self::ANTES;
    }

    /** Invoca el caso de uso del paso con datos válidos para el nivel. */
    private function escribir(string $paso, EscuelaNivel $nivel): void
    {
        $clave = $nivel->nivelEducativo()->value('clave');
        $grado = (int) DB::table('grados')->where('nivel_educativo_id', $nivel->nivel_educativo_id)->where('orden', 1)->value('id');
        $plantelId = (int) $nivel->escuela()->value('plantel_id');

        match ($paso) {
            'plan_estudios' => app(RegistrarPlanEstudios::class)->ejecutar($nivel->id, new DatosPlanEstudios('escolarizada', 'Plan 2022')),
            'plantilla_docente' => app(RegistrarPlantillaDocente::class)->ejecutar($nivel->id, [new DatosPersona(
                cargoPuestoId: $this->cargoId($clave, 'Director Técnico'), nombre: 'Ana López', nacionalidad: 'Mexicana', sexo: 'F',
                estudios: 'Lic. en Educación', cedulaODocumento: '1234567',
            )]),
            'matricula' => app(RegistrarMatricula::class)->ejecutar($nivel->id, new DatosMatricula(grupos: [['gradoId' => $grado, 'grupo' => 'A', 'alumnos' => 25]])),
            'mobiliario' => app(RegistrarMobiliarioNivel::class)->ejecutar($nivel->id, [(int) DB::table('mobiliario_conceptos')->value('id') => 4]),
            'inmueble' => app(RegistrarDatosInmueble::class)->ejecutar($plantelId, $nivel->id, new DatosInmueble(metrosTotales: 500.0, metrosConstruidos: 250.0)),
            'infraestructura' => app(RegistrarInfraestructuraNivel::class)->ejecutar($plantelId, $nivel->id, new DatosInfraestructuraNivel([], [], 3, 90.0)),
            default => throw new InvalidArgumentException("Paso desconocido: {$paso}"),
        };
    }

    /** @param  callable(): void  $accion */
    private function assertRechazada(callable $accion): void
    {
        try {
            $accion();
            $this->fail('Se esperaba PrecondicionIncumplida.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame('enviado', $e->etapaFaltante);
        }
    }

    #[DataProvider('pasos')]
    public function test_el_paso_reabierto_escribe_y_se_marca(string $paso): void
    {
        $nivel = $this->nivelCompleto($this->claveNivel($paso));
        $this->devolverTramite($nivel->escuela, [$nivel->id => [$paso => 'Corrige']]);

        $this->escribir($paso, $nivel);

        $this->assertTrue($this->escribio($nivel, $paso));
    }

    #[DataProvider('pasos')]
    public function test_el_paso_aceptado_se_rechaza(string $paso): void
    {
        $nivel = $this->nivelCompleto($this->claveNivel($paso));
        $this->devolverTramite($nivel->escuela, [$nivel->id => [$this->otroPaso($paso) => 'Corrige']], [$nivel->id => [$paso]]);

        $this->assertRechazada(fn () => $this->escribir($paso, $nivel));
        $this->assertFalse($this->escribio($nivel, $paso));
    }

    #[DataProvider('pasos')]
    public function test_con_otro_paso_rechazado_el_paso_se_rechaza(string $paso): void
    {
        $nivel = $this->nivelCompleto($this->claveNivel($paso));
        $this->devolverTramite($nivel->escuela, [$nivel->id => [$this->otroPaso($paso) => 'Corrige']]);

        $this->assertRechazada(fn () => $this->escribir($paso, $nivel));
        $this->assertFalse($this->escribio($nivel, $paso));
    }

    #[DataProvider('pasos')]
    public function test_el_paso_rechazado_en_un_nivel_no_abre_el_otro(string $paso): void
    {
        if ($paso === 'mobiliario') {
            $this->markTestSkipped('Mobiliario solo aplica a Inicial: no hay un segundo nivel donde escribirlo.');
        }

        $a = $this->nivelCompleto('primaria');
        $b = $this->segundoNivel($a, 'secundaria');
        $this->devolverTramite($a->escuela, [$a->id => [$paso => 'Corrige'], $b->id => [$this->otroPaso($paso) => 'Corrige']]);

        $this->escribir($paso, $a);
        $this->assertTrue($this->escribio($a, $paso));

        $this->assertRechazada(fn () => $this->escribir($paso, $b));
        $this->assertFalse($this->escribio($b, $paso));
    }

    public function test_inmueble_reabierto_respeta_el_bloqueo_adr019(): void
    {
        $a = $this->nivelCompleto('primaria');
        $this->escribir('inmueble', $a);
        $b = $this->segundoNivel($a, 'secundaria');
        $this->escribir('plan_estudios', $b); // datos propios de Paso 3 en otro nivel: el plantel queda bloqueado para A
        $this->devolverTramite($a->escuela, [$a->id => ['inmueble' => 'Corrige'], $b->id => ['matricula' => 'Corrige']]);
        $plantelId = (int) $a->escuela()->value('plantel_id');

        app(RegistrarDatosInmueble::class)->ejecutar($plantelId, $a->id, new DatosInmueble(
            metrosTotales: 9999.0, metrosConstruidos: 8888.0, colindanciaNorte: 'Calle Norte',
        ));

        $plantel = DB::table('planteles')->where('id', $plantelId)->first();
        $this->assertEquals(500.0, (float) $plantel->metros_totales, 'valor ya capturado: no se sobrescribe');
        $this->assertSame('Calle Norte', $plantel->colindancia_norte, 'campo nulo: se completa');
    }

    public function test_infraestructura_compartida_solo_con_el_paso_reabierto_en_el_nivel(): void
    {
        $a = $this->nivelCompleto('primaria');
        $this->escribir('infraestructura', $a);
        $plantelId = (int) $a->escuela()->value('plantel_id');
        $this->devolverTramite($a->escuela, [$a->id => ['plan_estudios' => 'Corrige']]);

        $this->assertRechazada(fn () => app(EditarInfraestructuraCompartida::class)->ejecutar($plantelId, $a->id, new DatosEdicionInfraestructura([], [])));

        $this->devolverTramite($a->escuela, [$a->id => ['infraestructura' => 'Corrige']]);

        app(EditarInfraestructuraCompartida::class)->ejecutar($plantelId, $a->id, new DatosEdicionInfraestructura([], []));
        $this->addToAssertionCount(1);
    }
}
