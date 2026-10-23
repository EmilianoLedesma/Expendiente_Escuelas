<?php

namespace App\Application\Inmueble;

use App\Application\Captura\ReglasCaptura;
use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Inmueble\DTO\DatosInmueble;
use App\Application\Tramite\EstadoPaso2;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\RegistroCompartidoBloqueado;
use App\Application\Tramite\TramiteEditable;
use App\Models\EscuelaNivel;
use App\Models\InmuebleEstudioActual;
use App\Models\Plantel;
use App\Models\ServicioCercano;
use Illuminate\Support\Facades\DB;

/**
 * Caso de uso de PRD §5 Paso 3, sub-paso 1 (Datos del inmueble).
 *
 * Todo lo que escribe es del plantel (WS-7b-2): es editable mientras ningún otro
 * nivel del plantel tenga datos propios de Paso 3; con otro nivel con datos solo
 * se completan los campos nulos y los conjuntos hijos vacíos. La verificación se
 * hace aquí y no solo en el componente —el caso de uso no confía en que la
 * presentación haya escondido o bloqueado el formulario.
 */
class RegistrarDatosInmueble
{
    public function __construct(
        private readonly RegistroCompartidoBloqueado $registroBloqueado,
        private readonly TramiteEditable $tramiteEditable,
        private readonly MarcarPasoCompletado $marcarPasoCompletado,
        private readonly EstadoPaso2 $estadoPaso2,
        private readonly EstadoPaso3 $estadoPaso3,
    ) {}

    /** @throws PrecondicionIncumplida si Paso 2 no está completo o el sub-paso "inmueble" aún no es alcanzable. */
    public function ejecutar(int $plantelId, int $escuelaNivelId, DatosInmueble $datos): void
    {
        $this->validar($datos);
        $this->verificarPrecondicion($escuelaNivelId);

        DB::transaction(function () use ($plantelId, $escuelaNivelId, $datos) {
            // WS-7b-2: FOR UPDATE sobre todos los niveles del plantel, primero (orden escuela_niveles → escuelas);
            // reemplaza el FOR SHARE del nivel y serializa esta edición contra la de otro nivel y contra el envío.
            $this->registroBloqueado->bloquearNiveles($plantelId);
            // `inmueble` es un paso del nivel aunque se escriba a ámbito plantel: pasa el nivel que lo rechazó.
            $escuelaId = (int) EscuelaNivel::where('id', $escuelaNivelId)->value('escuela_id');
            $this->tramiteEditable->asegurarPlantel($plantelId, 'inmueble', $escuelaId, $escuelaNivelId);

            $campos = [
                'metros_totales' => $datos->metrosTotales,
                'metros_construidos' => $datos->metrosConstruidos,
                'colindancia_norte' => $datos->colindanciaNorte,
                'colindancia_sur' => $datos->colindanciaSur,
                'colindancia_este' => $datos->colindanciaEste,
                'colindancia_oeste' => $datos->colindanciaOeste,
                'latitud' => $datos->latitud,
                'longitud' => $datos->longitud,
                'area_civica_m2' => $datos->areaCivicaM2,
                'tiene_asta_bandera' => $datos->tieneAstaBandera,
            ];
            $bloqueado = $this->registroBloqueado->bloqueado($plantelId, $escuelaNivelId);

            if ($bloqueado) {
                // Otro nivel ya tiene datos propios de Paso 3: solo se completa lo que está en NULL (false es un valor).
                $actual = Plantel::where('id', $plantelId)->first(array_keys($campos))?->getAttributes() ?? [];
                $campos = array_filter($campos, fn ($valor, $columna) => $valor !== null && ($actual[$columna] ?? null) === null, ARRAY_FILTER_USE_BOTH);
            }

            if ($campos !== []) {
                Plantel::where('id', $plantelId)->update($campos + ['updated_at' => now()]);
            }

            // Los conjuntos se reemplazan salvo que estén bloqueados, y entonces solo se aportan si el plantel no tiene ninguno.
            if (! $bloqueado || ! ServicioCercano::where('plantel_id', $plantelId)->exists()) {
                ServicioCercano::where('plantel_id', $plantelId)->delete();
                foreach ($datos->serviciosCercanos as $servicio) {
                    ServicioCercano::create([
                        'plantel_id' => $plantelId,
                        'nombre' => $servicio['nombre'],
                        'tipo' => $servicio['tipo'],
                        'es_publico' => $servicio['esPublico'],
                        'distancia_valor' => $servicio['distanciaValor'],
                        'distancia_unidad' => $servicio['distanciaUnidad'],
                    ]);
                }
            }

            if (! $bloqueado || ! InmuebleEstudioActual::where('plantel_id', $plantelId)->exists()) {
                InmuebleEstudioActual::where('plantel_id', $plantelId)->delete();
                foreach ($datos->estudiosActuales as $estudio) {
                    InmuebleEstudioActual::create([
                        'plantel_id' => $plantelId,
                        'nivel_educativo_id' => $estudio['nivelEducativoId'],
                        'otro_nivel_texto' => $estudio['otroNivelTexto'],
                        'numero_alumnos' => $estudio['numeroAlumnos'],
                    ]);
                }
            }

            $this->marcarPasoCompletado->ejecutar($escuelaNivelId, 'inmueble');
        });
    }

    /** WS-2.4b: un adaptador que llame este caso de uso sin pasar por CompuertaPaso3 no debe poder saltarse el orden del wizard. */
    private function verificarPrecondicion(int $escuelaNivelId): void
    {
        $escuelaId = EscuelaNivel::where('id', $escuelaNivelId)->value('escuela_id');
        $etapaFaltante = $this->estadoPaso2->etapaFaltante($escuelaId);
        if ($etapaFaltante !== null) {
            throw new PrecondicionIncumplida($etapaFaltante, 'Completa el Paso 2 antes de continuar.');
        }

        if (! $this->estadoPaso3->puedeAcceder($escuelaNivelId, 'inmueble')) {
            throw new PrecondicionIncumplida('inmueble', 'Completa los pasos anteriores de Paso 3 antes de continuar.');
        }
    }

    /**
     * Invariantes de entrada (WS-2.4a): un caller que se salte el formulario
     * no debe poder violar un CHECK del DDL (servicios_cercanos.tipo/
     * distancia_unidad, docs/ddl_sistema_incorporacion_v3.sql líneas 225-228)
     * ni un invariante de negocio (metros_totales > 0; lat/lon geográficos;
     * un estudio actual declara nivel_educativo_id XOR otro_nivel_texto,
     * nunca ambos ni ninguno). Las claves de $errores usan la misma ruta que
     * las propiedades Livewire (serviciosCercanos.{i}.*, estudiosActuales.{i}.*)
     * para que el componente pueda mapearlas 1:1 con addError().
     */
    private function validar(DatosInmueble $datos): void
    {
        $errores = [];

        if ($datos->metrosTotales <= 0) {
            $errores['metrosTotales'] = 'Los metros totales deben ser mayores a cero.';
        }

        // WS-7a §3.1: alimenta preescolar.superficie.construida_total y el inmueble se escribe una sola vez.
        if ($datos->metrosConstruidos === null) {
            $errores['metrosConstruidos'] = 'Captura la superficie construida.';
        }

        // Precisión de la columna (NUMERIC(10,2)): fuera de rango sería un error de base de datos.
        foreach (['metrosTotales' => $datos->metrosTotales, 'metrosConstruidos' => $datos->metrosConstruidos, 'areaCivicaM2' => $datos->areaCivicaM2] as $campo => $valor) {
            if ($valor !== null && $valor > ReglasCaptura::MAX_NUMERIC_10_2) {
                $errores[$campo] = 'El valor no debe ser mayor que '.ReglasCaptura::MAX_NUMERIC_10_2.'.';
            }
        }

        if ($datos->latitud !== null && ($datos->latitud < -90 || $datos->latitud > 90)) {
            $errores['latitud'] = 'La latitud debe estar entre -90 y 90.';
        }

        if ($datos->longitud !== null && ($datos->longitud < -180 || $datos->longitud > 180)) {
            $errores['longitud'] = 'La longitud debe estar entre -180 y 180.';
        }

        foreach ($datos->serviciosCercanos as $i => $servicio) {
            if (! in_array($servicio['tipo'], ['salud', 'emergencia'], true)) {
                $errores["serviciosCercanos.{$i}.tipo"] = 'El tipo debe ser salud o emergencia.';
            }

            if ($servicio['distanciaUnidad'] !== null && ! in_array($servicio['distanciaUnidad'], ['m', 'km'], true)) {
                $errores["serviciosCercanos.{$i}.distanciaUnidad"] = 'La unidad de distancia debe ser m o km.';
            }

            if ($servicio['distanciaValor'] !== null && ($servicio['distanciaValor'] < 0 || $servicio['distanciaValor'] > ReglasCaptura::MAX_NUMERIC_6_2)) {
                $errores["serviciosCercanos.{$i}.distanciaValor"] = 'La distancia debe estar entre 0 y '.ReglasCaptura::MAX_NUMERIC_6_2.'.';
            }
        }

        foreach ($datos->estudiosActuales as $i => $estudio) {
            $tieneNivel = $estudio['nivelEducativoId'] !== null;
            $tieneOtro = $estudio['otroNivelTexto'] !== null;

            if ($tieneNivel === $tieneOtro) {
                $errores["estudiosActuales.{$i}.nivelEducativoId"] = 'Debe indicar el nivel educativo o "otro", pero no ambos ni ninguno.';
            }

            if ($estudio['numeroAlumnos'] < 0 || $estudio['numeroAlumnos'] > ReglasCaptura::MAX_SMALLINT) {
                $errores["estudiosActuales.{$i}.numeroAlumnos"] = 'El número de alumnos debe estar entre 0 y '.ReglasCaptura::MAX_SMALLINT.'.';
            }
        }

        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }
    }
}
