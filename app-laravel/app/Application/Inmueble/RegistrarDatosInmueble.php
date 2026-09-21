<?php

namespace App\Application\Inmueble;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
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
}
