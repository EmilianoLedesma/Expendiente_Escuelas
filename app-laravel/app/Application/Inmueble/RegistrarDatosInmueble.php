<?php

namespace App\Application\Inmueble;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Inmueble\DTO\DatosInmueble;
use App\Models\InmuebleEstudioActual;
use App\Models\Plantel;
use App\Models\ServicioCercano;
use Illuminate\Support\Facades\DB;

/**
 * Caso de uso de PRD §5 Paso 3, sub-paso 1 (Datos del inmueble).
 *
 * Todo lo que escribe es del plantel, así que solo escribe la primera vez:
 * al reutilizar un plantel ya capturado el paso se marca completado sin
 * reescribir nada. La verificación se hace aquí y no solo en el componente
 * —el caso de uso no confía en que la presentación haya escondido el
 * formulario.
 */
class RegistrarDatosInmueble
{
    public function __construct(
        private readonly DatosInmuebleYaCapturados $yaCapturados,
        private readonly MarcarPasoCompletado $marcarPasoCompletado,
    ) {}

    public function ejecutar(int $plantelId, int $escuelaNivelId, DatosInmueble $datos): void
    {
        $this->validar($datos);

        DB::transaction(function () use ($plantelId, $escuelaNivelId, $datos) {
            if (! $this->yaCapturados->ejecutar($plantelId)) {
                Plantel::where('id', $plantelId)->update([
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
                    'updated_at' => now(),
                ]);

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
        }

        foreach ($datos->estudiosActuales as $i => $estudio) {
            $tieneNivel = $estudio['nivelEducativoId'] !== null;
            $tieneOtro = $estudio['otroNivelTexto'] !== null;

            if ($tieneNivel === $tieneOtro) {
                $errores["estudiosActuales.{$i}.nivelEducativoId"] = 'Debe indicar el nivel educativo o "otro", pero no ambos ni ninguno.';
            }
        }

        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }
    }
}
