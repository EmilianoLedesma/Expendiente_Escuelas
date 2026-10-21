<?php

namespace App\Application\Infraestructura;

use App\Application\Captura\ReglasCaptura;
use App\Application\Validaciones\ConstruirDatosCapacidad;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de campo de una fila de espacio o de sanitario, compartidas por el alta
 * por nivel (RegistrarInfraestructuraNivel) y la edición del dueño
 * (EditarInfraestructuraCompartida). Cada caso de uso decide qué filas revisa y
 * con qué prefijo de clave (las rutas Livewire de cada formulario); aquí solo
 * viven los rangos de columna y los campos que el motor de capacidad lee.
 */
final class ReglasFilaInfraestructura
{
    public const FALTA_SUPERFICIE = 'Captura la superficie: la revisión de capacidad instalada la usa.';

    /**
     * @param  list<int>  $tiposConSuperficie
     * @param  list<int>  $tiposBiblioteca
     */
    private function __construct(
        private readonly array $tiposConSuperficie,
        private readonly array $tiposBiblioteca,
    ) {}

    public static function cargar(): self
    {
        return new self(
            DB::table('tipos_espacios')
                ->where('categoria', ConstruirDatosCapacidad::CATEGORIA_RECREATIVA)
                ->orWhere('clave', ConstruirDatosCapacidad::CLAVE_USOS_MULTIPLES)
                ->pluck('id')->map(fn ($id) => (int) $id)->all(),
            DB::table('tipos_espacios')->where('permite_material_biblioteca', true)->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    /**
     * $prefijoMateriales sirve también como clave del error "sin materiales".
     *
     * @param  array<string, mixed>  $espacio  misma forma que DatosInfraestructuraNivel::$espacios
     * @param  bool  $exigirRequeridos  false al solo completar nulos: el valor ya guardado puede cubrir el campo
     * @return array<string, string>
     */
    public function espacio(array $espacio, string $prefijo, string $prefijoMateriales, bool $exigirRequeridos = true): array
    {
        $errores = [];

        foreach (['cantidad' => ReglasCaptura::MAX_SMALLINT, 'superficieM2' => ReglasCaptura::MAX_NUMERIC_10_2, 'capacidadPromedio' => ReglasCaptura::MAX_SMALLINT] as $campo => $maximo) {
            if ($espacio[$campo] !== null && $espacio[$campo] < 0) {
                $errores["{$prefijo}.{$campo}"] = 'No puede ser negativo.';
            } elseif (self::fueraDeRango($espacio[$campo], $maximo)) {
                $errores["{$prefijo}.{$campo}"] = "No debe ser mayor que {$maximo}.";
            }
        }

        if ($exigirRequeridos && FilaSignificativa::espacio($espacio)) {
            if ($espacio['superficieM2'] === null && in_array($espacio['tipoEspacioId'], $this->tiposConSuperficie, true)) {
                $errores["{$prefijo}.superficieM2"] = self::FALTA_SUPERFICIE;
            }

            if ($espacio['materialesBiblioteca'] === [] && in_array($espacio['tipoEspacioId'], $this->tiposBiblioteca, true)) {
                $errores[$prefijoMateriales] = 'Captura el acervo de la biblioteca: al menos un tipo de material con su número de títulos.';
            }
        }

        foreach ($espacio['materialesBiblioteca'] as $material) {
            $prefijoMaterial = "{$prefijoMateriales}.{$material['tipoMaterialId']}";

            if ($material['numeroTitulos'] === null) {
                $errores["{$prefijoMaterial}.numeroTitulos"] = 'Captura el número de títulos.';
            } elseif ($material['numeroTitulos'] < 0) {
                $errores["{$prefijoMaterial}.numeroTitulos"] = 'No puede ser negativo.';
            } elseif (self::fueraDeRango($material['numeroTitulos'], ReglasCaptura::MAX_INTEGER)) {
                $errores["{$prefijoMaterial}.numeroTitulos"] = 'No debe ser mayor que '.ReglasCaptura::MAX_INTEGER.'.';
            }

            if ($material['numeroVolumenes'] !== null && $material['numeroVolumenes'] < 0) {
                $errores["{$prefijoMaterial}.numeroVolumenes"] = 'No puede ser negativo.';
            } elseif (self::fueraDeRango($material['numeroVolumenes'], ReglasCaptura::MAX_INTEGER)) {
                $errores["{$prefijoMaterial}.numeroVolumenes"] = 'No debe ser mayor que '.ReglasCaptura::MAX_INTEGER.'.';
            }
        }

        return $errores;
    }

    /**
     * @param  array<string, mixed>  $sanitario  misma forma que DatosInfraestructuraNivel::$sanitarios
     * @return array<string, string>
     */
    public function sanitario(array $sanitario, string $prefijo, bool $exigirRequeridos = true): array
    {
        $errores = [];

        foreach (['cantidadRetretes', 'cantidadMingitorios', 'cantidadLavabos', 'cantidadBacinicas'] as $campo) {
            if ($sanitario[$campo] !== null && $sanitario[$campo] < 0) {
                $errores["{$prefijo}.{$campo}"] = 'No puede ser negativo.';
            } elseif (self::fueraDeRango($sanitario[$campo], ReglasCaptura::MAX_SMALLINT)) {
                $errores["{$prefijo}.{$campo}"] = 'No debe ser mayor que '.ReglasCaptura::MAX_SMALLINT.'.';
            }
        }

        if ($sanitario['superficieM2'] !== null && $sanitario['superficieM2'] < 0) {
            $errores["{$prefijo}.superficieM2"] = 'No puede ser negativo.';
        } elseif (self::fueraDeRango($sanitario['superficieM2'], ReglasCaptura::MAX_NUMERIC_8_2)) {
            $errores["{$prefijo}.superficieM2"] = 'No debe ser mayor que '.ReglasCaptura::MAX_NUMERIC_8_2.'.';
        }

        if ($exigirRequeridos
            && $sanitario['superficieM2'] === null
            && str_starts_with($sanitario['categoria'], ConstruirDatosCapacidad::PREFIJO_SANITARIOS_ALUMNOS)
            && FilaSignificativa::sanitario($sanitario)) {
            $errores["{$prefijo}.superficieM2"] = self::FALTA_SUPERFICIE;
        }

        return $errores;
    }

    /** Además de "no negativo", el rango de la columna: fuera de él sería un error de base de datos. */
    public static function fueraDeRango(int|float|null $valor, int|float $maximo): bool
    {
        return $valor !== null && $valor > $maximo;
    }
}
