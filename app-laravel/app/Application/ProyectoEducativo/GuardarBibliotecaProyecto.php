<?php

namespace App\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosBibliotecaProyecto;
use App\Application\Tramite\CompuertaSubPaso3;
use App\Application\Tramite\TramiteEditable;
use App\Models\EscuelaNivel;
use App\Models\ProyectoEducativoBiblioteca;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Sección 19 del Proyecto Educativo (media superior): guarda UN bloque (1 = datos generales y perfiles, 2 = otros servicios y
 * acervo), todo o nada dentro del bloque. Solo valida y escribe los campos del bloque; lo ya guardado del otro no se toca.
 * Un campo condicional que no aplica (tipo virtual con biblioteca física, perfil sin encargado, texto del programa sin
 * programa) se descarta aunque llegue, y borra el guardado. No marca el paso proyecto_educativo como completado.
 */
class GuardarBibliotecaProyecto
{
    public function __construct(
        private readonly CompuertaSubPaso3 $compuerta,
        private readonly TramiteEditable $tramiteEditable,
    ) {}

    /**
     * @throws DatosInvalidos
     * @throws PrecondicionIncumplida
     * @throws InvalidArgumentException con un bloque que no existe
     */
    public function ejecutar(int $escuelaNivelId, int $bloque, DatosBibliotecaProyecto $datos): void
    {
        $camposDelBloque = (ReglasBibliotecaProyecto::BLOQUES[$bloque] ?? throw new InvalidArgumentException("Bloque de biblioteca desconocido: {$bloque}"))['campos'];

        $escuelaNivel = EscuelaNivel::findOrFail($escuelaNivelId);
        // Fuera de media superior el paso no existe en el catálogo del nivel y la compuerta lanza InvalidArgumentException.
        $this->compuerta->verificar($escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);

        $valores = Arr::only(
            array_map(fn (?string $valor) => ($valor = trim((string) $valor)) === '' ? null : $valor, $datos->aArray()),
            $camposDelBloque,
        );
        $errores = ReglasBibliotecaProyecto::errores($valores, $bloque);
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        $atributos = $bloque === 1 ? $this->atributosBloqueUno($valores) : $this->atributosBloqueDos($valores);

        DB::transaction(function () use ($escuelaNivel, $atributos) {
            $bloqueado = EscuelaNivel::lockForUpdate()->findOrFail($escuelaNivel->id);
            $this->compuerta->verificar($bloqueado, SeccionesProyectoEducativo::CLAVE_PASO);
            $this->tramiteEditable->asegurarEscuela((int) $bloqueado->escuela_id, SeccionesProyectoEducativo::CLAVE_PASO, $bloqueado->id);

            ProyectoEducativoBiblioteca::updateOrCreate(['escuela_nivel_id' => $bloqueado->id], $atributos);
        });
    }

    /**
     * @param  array<string, string|null>  $v
     * @return array<string, mixed>
     */
    private function atributosBloqueUno(array $v): array
    {
        return [
            'biblioteca_tipo' => $v['biblioteca_tipo'],
            'biblioteca_virtual_tipo' => $v['biblioteca_tipo'] === 'fisica' ? null : $v['biblioteca_virtual_tipo'],
            'biblioteca_tiene_encargado' => $v['biblioteca_tiene_encargado'] === 'si',
            'biblioteca_perfil_encargado' => $v['biblioteca_tiene_encargado'] === 'si' ? $v['biblioteca_perfil_encargado'] : null,
            'biblioteca_prestamo' => $v['biblioteca_prestamo'],
        ];
    }

    /**
     * @param  array<string, string|null>  $v
     * @return array<string, mixed>
     */
    private function atributosBloqueDos(array $v): array
    {
        return [
            'biblioteca_tiene_programa_lectura' => $v['biblioteca_tiene_programa_lectura'] === 'si',
            'biblioteca_programa_lectura' => $v['biblioteca_tiene_programa_lectura'] === 'si' ? $v['biblioteca_programa_lectura'] : null,
            'biblioteca_otros_programas' => $v['biblioteca_otros_programas'],
            'acervo_clasificacion' => $v['acervo_clasificacion'],
            'acervo_titulos_basicos' => $v['acervo_titulos_basicos'],
            'acervo_titulos_lectura' => $v['acervo_titulos_lectura'],
        ];
    }
}
