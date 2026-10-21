<?php

namespace App\Application\Infraestructura;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Infraestructura\DTO\DatosEdicionInfraestructura;
use App\Application\Tramite\RegistroCompartidoBloqueado;
use App\Application\Tramite\TramiteEditable;
use App\Models\InstalacionEspacio;
use App\Models\Sanitario;
use Illuminate\Support\Facades\DB;

/**
 * WS-7b-2: el dueño corrige o quita filas ya capturadas de espacios y sanitarios del
 * plantel (agregar tipos nuevos sigue en RegistrarInfraestructuraNivel, ADR-005).
 *
 * Sin bloqueo reemplaza los campos de la fila y borra y reinserta sus hijos
 * (campos_futbol, biblioteca_materiales, sanitarios_bacinicas); quitar borra la fila y
 * sus hijos caen por ON DELETE CASCADE. Con otro nivel del plantel con datos propios de
 * Paso 3 (RegistroCompartidoBloqueado) solo completa campos nulos —false es un valor—,
 * inserta hijos solo si la fila no tiene ninguno, y no quita. El permiso (solo dueño,
 * EscuelaNivelPolicy::editarCompartido) lo revisa el adaptador.
 */
class EditarInfraestructuraCompartida
{
    /** Etapa de PrecondicionIncumplida: el usuario se queda en el sub-paso de infraestructura. */
    private const ETAPA = 'infraestructura';

    public function __construct(
        private readonly InfraestructuraYaCapturada $yaCapturada,
        private readonly RegistroCompartidoBloqueado $registroCompartido,
        private readonly TramiteEditable $tramiteEditable,
    ) {}

    /**
     * @throws PrecondicionIncumplida si un trámite del plantel ya se envió o se pide quitar con el registro bloqueado.
     * @throws DatosInvalidos si un tipo o categoría no está capturado en el plantel, una fila editada queda vacía o un campo no cumple su regla.
     */
    public function ejecutar(int $plantelId, int $escuelaNivelId, DatosEdicionInfraestructura $datos): void
    {
        DB::transaction(function () use ($plantelId, $escuelaNivelId, $datos) {
            // Primero el bloqueo de todos los niveles del plantel (orden escuela_niveles → escuelas),
            // luego la guarda del plantel; el estado de bloqueo se lee ya serializado.
            $this->registroCompartido->bloquearNiveles($plantelId);
            $this->tramiteEditable->asegurarPlantel($plantelId);
            $bloqueado = $this->registroCompartido->bloqueado($plantelId, $escuelaNivelId);

            if ($bloqueado && ($datos->quitarEspacios !== [] || $datos->quitarSanitarios !== [])) {
                throw new PrecondicionIncumplida(self::ETAPA, 'Otro nivel del plantel ya capturó sus datos: los espacios y sanitarios compartidos ya no pueden quitarse.');
            }

            $this->validar($plantelId, $datos, $bloqueado);

            foreach ($datos->espacios as $espacio) {
                $this->editarEspacio($plantelId, $espacio, $bloqueado);
            }

            foreach ($datos->sanitarios as $sanitario) {
                $this->editarSanitario($plantelId, $sanitario, $bloqueado);
            }

            InstalacionEspacio::where('plantel_id', $plantelId)->whereIn('tipo_espacio_id', $datos->quitarEspacios)->delete();
            Sanitario::where('plantel_id', $plantelId)->whereIn('categoria', $datos->quitarSanitarios)->delete();
        });
    }

    /** @param array<string, mixed> $espacio */
    private function editarEspacio(int $plantelId, array $espacio, bool $bloqueado): void
    {
        $fila = InstalacionEspacio::where('plantel_id', $plantelId)->where('tipo_espacio_id', $espacio['tipoEspacioId'])->firstOrFail();

        $this->actualizar($fila, [
            'cantidad' => $espacio['cantidad'],
            'superficie_m2' => $espacio['superficieM2'],
            'capacidad_promedio' => $espacio['capacidadPromedio'],
            'ventilacion_natural' => $espacio['ventilacionNatural'],
            'iluminacion_natural' => $espacio['iluminacionNatural'],
            'destinado_a' => $espacio['destinadoA'],
        ], $bloqueado);

        if ($this->reemplazaHijos('campos_futbol', 'instalacion_espacio_id', $fila->id, $bloqueado) && $espacio['campoFutbol'] !== null) {
            DB::table('campos_futbol')->insert([
                'instalacion_espacio_id' => $fila->id,
                'tipo_superficie' => $espacio['campoFutbol']['tipoSuperficie'],
                'formato' => $espacio['campoFutbol']['formato'],
            ]);
        }

        if ($this->reemplazaHijos('biblioteca_materiales', 'instalacion_espacio_id', $fila->id, $bloqueado)) {
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

    /** @param array<string, mixed> $sanitario */
    private function editarSanitario(int $plantelId, array $sanitario, bool $bloqueado): void
    {
        $fila = Sanitario::where('plantel_id', $plantelId)->where('categoria', $sanitario['categoria'])->firstOrFail();

        $this->actualizar($fila, [
            'cantidad_retretes' => $sanitario['cantidadRetretes'],
            'cantidad_mingitorios' => $sanitario['cantidadMingitorios'],
            'cantidad_lavabos' => $sanitario['cantidadLavabos'],
            'superficie_m2' => $sanitario['superficieM2'],
            'ventilacion_natural' => $sanitario['ventilacionNatural'],
            'iluminacion_natural' => $sanitario['iluminacionNatural'],
        ], $bloqueado);

        if ($this->reemplazaHijos('sanitarios_bacinicas', 'sanitario_id', $fila->id, $bloqueado) && $sanitario['cantidadBacinicas'] !== null) {
            DB::table('sanitarios_bacinicas')->insert(['sanitario_id' => $fila->id, 'cantidad_bacinicas' => $sanitario['cantidadBacinicas']]);
        }
    }

    /**
     * Sin bloqueo reemplaza todos los campos (también a null); bloqueado solo completa los que están en NULL.
     *
     * @param  array<string, mixed>  $campos
     */
    private function actualizar(InstalacionEspacio|Sanitario $fila, array $campos, bool $bloqueado): void
    {
        if ($bloqueado) {
            $actual = $fila->getAttributes();
            $campos = array_filter($campos, fn ($valor, $columna) => $valor !== null && $actual[$columna] === null, ARRAY_FILTER_USE_BOTH);
        }

        if ($campos !== []) {
            $fila->update($campos);
        }
    }

    /**
     * ¿Se escriben los hijos de esta tabla? Sin bloqueo se borran y se reinsertan; bloqueado,
     * solo se aportan si la fila no tiene ninguno. Si devuelve true, la tabla ya quedó vacía.
     */
    private function reemplazaHijos(string $tabla, string $columna, int $filaId, bool $bloqueado): bool
    {
        $hijos = DB::table($tabla)->where($columna, $filaId);

        if ($bloqueado && $hijos->exists()) {
            return false;
        }

        $hijos->delete();

        return true;
    }

    /**
     * Claves con el prefijo del formulario de edición (edicionEspacios.*, edicionSanitarios.*,
     * edicionMateriales.*) para no chocar con las del formulario «por capturar». Las reglas de
     * campo requerido solo se exigen sin bloqueo: bloqueado, el valor ya guardado puede cubrirlas.
     */
    private function validar(int $plantelId, DatosEdicionInfraestructura $datos, bool $bloqueado): void
    {
        $errores = [];
        $reglas = ReglasFilaInfraestructura::cargar();
        $tipos = $this->yaCapturada->tiposCapturados($plantelId);
        $categorias = $this->yaCapturada->categoriasCapturadas($plantelId);
        $noCapturado = 'El plantel no tiene capturado este registro.';
        $vacia = 'La fila no puede quedar vacía: captura al menos un dato o quítala.';

        foreach ($datos->espacios as $espacio) {
            $clave = "edicionEspacios.{$espacio['tipoEspacioId']}";

            if (! in_array($espacio['tipoEspacioId'], $tipos, true)) {
                $errores["{$clave}.tipoEspacioId"] = $noCapturado;
            } elseif (! FilaSignificativa::espacio($espacio)) {
                $errores["{$clave}.tipoEspacioId"] = $vacia;
            } else {
                $errores += $reglas->espacio($espacio, $clave, 'edicionMateriales', ! $bloqueado);
            }
        }

        foreach ($datos->quitarEspacios as $tipoEspacioId) {
            if (! in_array($tipoEspacioId, $tipos, true)) {
                $errores["edicionEspacios.{$tipoEspacioId}.tipoEspacioId"] = $noCapturado;
            }
        }

        foreach ($datos->sanitarios as $sanitario) {
            $clave = "edicionSanitarios.{$sanitario['categoria']}";

            if (! in_array($sanitario['categoria'], $categorias, true)) {
                $errores["{$clave}.categoria"] = $noCapturado;
            } elseif (! FilaSignificativa::sanitario($sanitario)) {
                $errores["{$clave}.categoria"] = $vacia;
            } else {
                $errores += $reglas->sanitario($sanitario, $clave, ! $bloqueado);
            }
        }

        foreach ($datos->quitarSanitarios as $categoria) {
            if (! in_array($categoria, $categorias, true)) {
                $errores["edicionSanitarios.{$categoria}.categoria"] = $noCapturado;
            }
        }

        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }
    }
}
