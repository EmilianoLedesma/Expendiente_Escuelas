<?php

namespace App\Application\Infraestructura;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Infraestructura\DTO\DatosInfraestructuraNivel;
use App\Models\AulaNivel;
use App\Models\InstalacionEspacio;
use App\Models\Sanitario;
use Illuminate\Support\Facades\DB;

/**
 * Caso de uso de PRD §5 Paso 3, sub-paso 2 (Infraestructura del nivel).
 *
 * instalaciones_espacios (+ campos_futbol, biblioteca_materiales) y
 * sanitarios (+ sanitarios_bacinicas) están anclados a plantel_id: se
 * escriben solo la primera vez que el plantel se captura. aulas_nivel es lo
 * único genuinamente por nivel y se escribe siempre. Ambos ámbitos vienen
 * del mismo envío de formulario, así que van en una sola transacción.
 */
class RegistrarInfraestructuraNivel
{
    public function __construct(
        private readonly InfraestructuraYaCapturada $yaCapturada,
        private readonly MarcarPasoCompletado $marcarPasoCompletado,
    ) {}

    public function ejecutar(int $plantelId, int $escuelaNivelId, DatosInfraestructuraNivel $datos): void
    {
        DB::transaction(function () use ($plantelId, $escuelaNivelId, $datos) {
            if (! $this->yaCapturada->ejecutar($plantelId)) {
                $this->escribirEspacios($plantelId, $datos);
                $this->escribirSanitarios($plantelId, $datos);
            }

            // ponytail: aulas_nivel no tiene índice único sobre escuela_nivel_id,
            // así que updateOrCreate sobre esa clave es lo que evita filas dobles
            // cuando el solicitante reenvía el formulario del mismo nivel.
            AulaNivel::updateOrCreate(
                ['escuela_nivel_id' => $escuelaNivelId],
                ['numero_aulas' => $datos->numeroAulas, 'superficie_m2' => $datos->superficieAulasM2],
            );

            $this->marcarPasoCompletado->ejecutar($escuelaNivelId, 'infraestructura');
        });
    }

    private function escribirEspacios(int $plantelId, DatosInfraestructuraNivel $datos): void
    {
        foreach ($datos->espacios as $espacio) {
            $fila = InstalacionEspacio::create([
                'plantel_id' => $plantelId,
                'tipo_espacio_id' => $espacio['tipoEspacioId'],
                'cantidad' => $espacio['cantidad'],
                'superficie_m2' => $espacio['superficieM2'],
                'capacidad_promedio' => $espacio['capacidadPromedio'],
                'ventilacion_natural' => $espacio['ventilacionNatural'],
                'iluminacion_natural' => $espacio['iluminacionNatural'],
                'destinado_a' => $espacio['destinadoA'],
            ]);

            if ($espacio['campoFutbol'] !== null) {
                DB::table('campos_futbol')->insert([
                    'instalacion_espacio_id' => $fila->id,
                    'tipo_superficie' => $espacio['campoFutbol']['tipoSuperficie'],
                    'formato' => $espacio['campoFutbol']['formato'],
                ]);
            }

            foreach ($espacio['materialesBiblioteca'] as $material) {
                DB::table('biblioteca_materiales')->insert([
                    'instalacion_espacio_id' => $fila->id,
                    'tipo_material_id' => $material['tipoMaterialId'],
                    'numero_titulos' => $material['numeroTitulos'],
                    'numero_volumenes' => $material['numeroVolumenes'],
                ]);
            }
        }
    }

    private function escribirSanitarios(int $plantelId, DatosInfraestructuraNivel $datos): void
    {
        foreach ($datos->sanitarios as $sanitario) {
            $fila = Sanitario::create([
                'plantel_id' => $plantelId,
                'categoria' => $sanitario['categoria'],
                'cantidad_retretes' => $sanitario['cantidadRetretes'],
                'cantidad_mingitorios' => $sanitario['cantidadMingitorios'],
                'cantidad_lavabos' => $sanitario['cantidadLavabos'],
                'superficie_m2' => $sanitario['superficieM2'],
                'ventilacion_natural' => $sanitario['ventilacionNatural'],
                'iluminacion_natural' => $sanitario['iluminacionNatural'],
            ]);

            if ($sanitario['cantidadBacinicas'] !== null) {
                DB::table('sanitarios_bacinicas')->insert([
                    'sanitario_id' => $fila->id,
                    'cantidad_bacinicas' => $sanitario['cantidadBacinicas'],
                ]);
            }
        }
    }
}
