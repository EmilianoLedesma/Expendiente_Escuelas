<?php

namespace App\Application\ProyectoEducativo;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosPresentacionProyecto;
use App\Application\Tramite\CompuertaSubPaso3;
use App\Application\Tramite\TramiteEditable;
use App\Models\EscuelaNivel;
use App\Models\ProyectoEducativoPresentacion;
use Illuminate\Support\Facades\DB;

/**
 * Sección 1 del Proyecto Educativo (media superior): guarda la presentación como borrador (formatos y máximos de
 * lo capturado; lo requerido solo cuenta para "completa", ver PresentacionProyectoCapturada). No marca el paso
 * proyecto_educativo como completado: el paso se cierra cuando estén las 20 secciones, que aún no existen.
 */
class GuardarPresentacionProyecto
{
    public function __construct(
        private readonly CompuertaSubPaso3 $compuerta,
        private readonly TramiteEditable $tramiteEditable,
    ) {}

    /**
     * @throws DatosInvalidos
     * @throws PrecondicionIncumplida
     */
    public function ejecutar(int $escuelaNivelId, DatosPresentacionProyecto $datos): void
    {
        $escuelaNivel = EscuelaNivel::findOrFail($escuelaNivelId);
        // Fuera de media superior el paso no existe en el catálogo del nivel y la compuerta lanza InvalidArgumentException.
        $this->compuerta->verificar($escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);

        $campos = self::normalizar($datos);
        $errores = ReglasPresentacionProyecto::errores($campos, completa: false);
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        DB::transaction(function () use ($escuelaNivel, $campos) {
            $bloqueado = EscuelaNivel::lockForUpdate()->findOrFail($escuelaNivel->id);
            $this->compuerta->verificar($bloqueado, SeccionesProyectoEducativo::CLAVE_PASO);
            $this->tramiteEditable->asegurarEscuela((int) $bloqueado->escuela_id, SeccionesProyectoEducativo::CLAVE_PASO, $bloqueado->id);

            ProyectoEducativoPresentacion::updateOrCreate(['escuela_nivel_id' => $bloqueado->id], $campos);
        });
    }

    /** @return array<string, string|null> */
    private static function normalizar(DatosPresentacionProyecto $datos): array
    {
        $campos = array_map(fn (?string $valor) => ($valor = trim((string) $valor)) === '' ? null : $valor, $datos->aArray());

        // Única normalización de teléfonos (la máscara del navegador es solo ayuda): se quitan separadores (una letra sigue
        // siendo error) y una lada +52/52 solo se descarta si lo que queda son exactamente 10 dígitos.
        foreach (['telefono', 'celular'] as $campo) {
            $limpio = $campos[$campo] === null ? null : preg_replace('/[\s().-]/', '', $campos[$campo]);
            $campos[$campo] = $limpio !== null && preg_match('/^\+?52(\d{10})$/', $limpio, $m) ? $m[1] : $limpio;
        }

        // "Otro" solo aplica cuando se eligió otro; un texto residual de una elección anterior no se guarda.
        if ($campos['ciclo_escolar'] !== 'otro') {
            $campos['ciclo_escolar_otro'] = null;
        }
        if ($campos['duracion'] !== 'otro') {
            $campos['duracion_otro'] = null;
        }

        return $campos;
    }
}
