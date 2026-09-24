<?php

namespace App\Application\Infraestructura;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Infraestructura\DTO\DatosInfraestructuraNivel;
use App\Models\AulaNivel;
use App\Models\EscuelaNivel;
use App\Models\InstalacionEspacio;
use App\Models\Sanitario;
use Illuminate\Support\Facades\DB;

/**
 * Caso de uso de PRD §5 Paso 3, sub-paso 2 (Infraestructura del nivel).
 *
 * instalaciones_espacios (+ campos_futbol, biblioteca_materiales) y
 * sanitarios (+ sanitarios_bacinicas) están anclados a plantel_id: cada fila
 * se escribe solo la primera vez que *ese* tipo_espacio / categoría de
 * sanitario se captura para el plantel (ADR-005 — guardián por-fila, no un
 * booleano por plantel, porque la aplicabilidad varía por nivel). aulas_nivel
 * es lo único genuinamente por nivel y se escribe siempre. Ambos ámbitos
 * vienen del mismo envío de formulario, así que van en una sola transacción.
 */
class RegistrarInfraestructuraNivel
{
    public function __construct(
        private readonly InfraestructuraYaCapturada $yaCapturada,
        private readonly MarcarPasoCompletado $marcarPasoCompletado,
        private readonly CategoriasSanitariosPorNivel $categoriasSanitariosPorNivel = new CategoriasSanitariosPorNivel,
    ) {}

    public function ejecutar(int $plantelId, int $escuelaNivelId, DatosInfraestructuraNivel $datos): void
    {
        $escuelaNivel = EscuelaNivel::with('nivelEducativo')->findOrFail($escuelaNivelId);
        $this->validar($plantelId, $escuelaNivel, $datos);

        DB::transaction(function () use ($plantelId, $escuelaNivelId, $datos) {
            $this->escribirEspacios($plantelId, $datos);
            $this->escribirSanitarios($plantelId, $datos);

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
        $yaCapturados = $this->yaCapturada->tiposCapturados($plantelId);

        foreach ($datos->espacios as $espacio) {
            if (in_array($espacio['tipoEspacioId'], $yaCapturados, true)) {
                continue;
            }

            if (! $this->tieneDatosSignificativos($espacio)) {
                continue;
            }

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

    /**
     * Misma regla que el formulario (WS-2.1): un booleano solo cuenta como
     * dato cuando es `true` — de lo contrario un caller de API podría crear
     * filas vacías, y por ADR-005 ese tipo quedaría "capturado" para
     * siempre sin que el solicitante haya declarado nada.
     *
     * @param  array{cantidad: int|null, superficieM2: float|null, capacidadPromedio: int|null, ventilacionNatural: bool|null, iluminacionNatural: bool|null, destinadoA: string|null, materialesBiblioteca: list<array<string, mixed>>}  $espacio
     */
    private function tieneDatosSignificativos(array $espacio): bool
    {
        return $espacio['cantidad'] !== null
            || $espacio['superficieM2'] !== null
            || $espacio['capacidadPromedio'] !== null
            || $espacio['destinadoA'] !== null
            || $espacio['ventilacionNatural'] === true
            || $espacio['iluminacionNatural'] === true
            || $espacio['materialesBiblioteca'] !== [];
    }

    private function escribirSanitarios(int $plantelId, DatosInfraestructuraNivel $datos): void
    {
        $yaCapturadas = $this->yaCapturada->categoriasCapturadas($plantelId);

        foreach ($datos->sanitarios as $sanitario) {
            if (in_array($sanitario['categoria'], $yaCapturadas, true)) {
                continue;
            }

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

    /**
     * Invariantes de entrada (WS-2.4a): un caller que se salte el formulario
     * (o un futuro adaptador de API) no debe poder escribir un tipo_espacio o
     * una categoría de sanitario que no aplica al nivel, ni un número
     * negativo. Las claves de $errores usan la misma ruta que las propiedades
     * Livewire (espacios.{tipoEspacioId}.*, sanitarios.{categoria}.*,
     * materialesBiblioteca.{tipoMaterialId}.*) para que el componente pueda
     * mapearlas 1:1 con addError().
     *
     * Un espacio/sanitario que el plantel ya capturó (en este nivel o en
     * otro) se salta aquí igual que en escribirEspacios()/escribirSanitarios():
     * no se va a escribir, así que no tiene sentido revalidar su
     * aplicabilidad contra el nivel en curso — es exactamente el escenario de
     * ADR-005 (unión por nivel) y un mismo tipo puede aplicar a un nivel y no
     * a otro.
     */
    private function validar(int $plantelId, EscuelaNivel $escuelaNivel, DatosInfraestructuraNivel $datos): void
    {
        $errores = [];

        if ($datos->numeroAulas < 0) {
            $errores['numeroAulas'] = 'El número de aulas no puede ser negativo.';
        }

        if ($datos->superficieAulasM2 !== null && $datos->superficieAulasM2 < 0) {
            $errores['superficieAulasM2'] = 'La superficie de aulas no puede ser negativa.';
        }

        $tiposAplicables = DB::table('niveles_tipos_espacios')
            ->where('nivel_educativo_id', $escuelaNivel->nivel_educativo_id)
            ->pluck('tipo_espacio_id')
            ->all();
        $tiposYaCapturados = $this->yaCapturada->tiposCapturados($plantelId);

        foreach ($datos->espacios as $espacio) {
            if (in_array($espacio['tipoEspacioId'], $tiposYaCapturados, true)) {
                continue;
            }

            $prefijo = "espacios.{$espacio['tipoEspacioId']}";

            if (! in_array($espacio['tipoEspacioId'], $tiposAplicables, true)) {
                $errores["{$prefijo}.tipoEspacioId"] = 'Este tipo de espacio no aplica al nivel educativo.';

                continue;
            }

            foreach (['cantidad' => 'cantidad', 'superficieM2' => 'superficieM2', 'capacidadPromedio' => 'capacidadPromedio'] as $campo => $rutaCampo) {
                if ($espacio[$campo] !== null && $espacio[$campo] < 0) {
                    $errores["{$prefijo}.{$rutaCampo}"] = 'No puede ser negativo.';
                }
            }

            foreach ($espacio['materialesBiblioteca'] as $material) {
                $prefijoMaterial = "materialesBiblioteca.{$material['tipoMaterialId']}";

                if ($material['numeroTitulos'] !== null && $material['numeroTitulos'] < 0) {
                    $errores["{$prefijoMaterial}.numeroTitulos"] = 'No puede ser negativo.';
                }

                if ($material['numeroVolumenes'] !== null && $material['numeroVolumenes'] < 0) {
                    $errores["{$prefijoMaterial}.numeroVolumenes"] = 'No puede ser negativo.';
                }
            }
        }

        $categoriasAplicables = $this->categoriasSanitariosPorNivel->paraNivel($escuelaNivel->nivelEducativo->clave);
        $categoriasYaCapturadas = $this->yaCapturada->categoriasCapturadas($plantelId);

        foreach ($datos->sanitarios as $sanitario) {
            if (in_array($sanitario['categoria'], $categoriasYaCapturadas, true)) {
                continue;
            }

            $prefijo = "sanitarios.{$sanitario['categoria']}";

            if (! in_array($sanitario['categoria'], $categoriasAplicables, true)) {
                $errores["{$prefijo}.categoria"] = 'Esta categoría de sanitario no aplica al nivel educativo.';

                continue;
            }

            foreach (['cantidadRetretes', 'cantidadMingitorios', 'cantidadLavabos', 'cantidadBacinicas'] as $campo) {
                if ($sanitario[$campo] !== null && $sanitario[$campo] < 0) {
                    $errores["{$prefijo}.{$campo}"] = 'No puede ser negativo.';
                }
            }

            if ($sanitario['superficieM2'] !== null && $sanitario['superficieM2'] < 0) {
                $errores["{$prefijo}.superficieM2"] = 'No puede ser negativo.';
            }
        }

        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }
    }
}
