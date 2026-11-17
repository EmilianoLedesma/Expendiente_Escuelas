<?php

namespace App\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosBibliografiaProyecto;
use App\Application\Tramite\CompuertaSubPaso3;
use App\Application\Tramite\TramiteEditable;
use App\Models\EscuelaNivel;
use App\Models\ProyectoEducativoBibliografia;
use Illuminate\Support\Facades\DB;

/**
 * Sección 20 del Proyecto Educativo (media superior): guarda la lista completa de referencias (de 1 a 50), todo o nada: si
 * alguna falla no se escribe nada y la lista guardada queda como estaba. Guarda en orden alfabético por autor (APA) y
 * reemplaza toda la lista de un golpe. No marca el paso proyecto_educativo como completado.
 */
class GuardarBibliografiaProyecto
{
    public function __construct(
        private readonly CompuertaSubPaso3 $compuerta,
        private readonly TramiteEditable $tramiteEditable,
    ) {}

    /**
     * @throws DatosInvalidos
     * @throws PrecondicionIncumplida
     */
    public function ejecutar(int $escuelaNivelId, DatosBibliografiaProyecto $datos): void
    {
        $escuelaNivel = EscuelaNivel::findOrFail($escuelaNivelId);
        // Fuera de media superior el paso no existe en el catálogo del nivel y la compuerta lanza InvalidArgumentException.
        $this->compuerta->verificar($escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);

        $lista = ReglasBibliografiaProyecto::normalizar($datos->referencias);
        $errores = ReglasBibliografiaProyecto::errores($lista);
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        $filas = [];
        foreach (FormatoReferenciaApa::ordenar($lista) as $indice => $referencia) {
            $filas[] = [
                'orden' => $indice + 1,
                'autores' => $referencia['autores'],
                'anio' => (int) $referencia['anio'],
                'titulo' => $referencia['titulo'],
                'fuente' => $referencia['fuente'],
                'url' => $referencia['url'] === '' ? null : $referencia['url'],
            ];
        }

        DB::transaction(function () use ($escuelaNivel, $filas) {
            $bloqueado = EscuelaNivel::lockForUpdate()->findOrFail($escuelaNivel->id);
            $this->compuerta->verificar($bloqueado, SeccionesProyectoEducativo::CLAVE_PASO);
            $this->tramiteEditable->asegurarEscuela((int) $bloqueado->escuela_id, SeccionesProyectoEducativo::CLAVE_PASO, $bloqueado->id);

            // Se borra primero: (escuela_nivel_id, orden) es único y la lista nueva reutiliza los órdenes 1 a n.
            ProyectoEducativoBibliografia::where('escuela_nivel_id', $bloqueado->id)->delete();
            ProyectoEducativoBibliografia::insert(array_map(fn (array $fila): array => ['escuela_nivel_id' => $bloqueado->id, ...$fila], $filas));
        });
    }
}
