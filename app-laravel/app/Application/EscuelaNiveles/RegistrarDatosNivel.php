<?php

namespace App\Application\EscuelaNiveles;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\EstadoPaso2;
use App\Infrastructure\Documentos\AlmacenDocumentos;
use App\Models\DocumentoEscuelaNivel;
use App\Models\EscuelaNivel;
use App\Models\TipoDocumento;
use Illuminate\Support\Facades\DB;

/**
 * Paso 2.4 (WS-5b): turno y tipo de alumnado del nivel — columnas que el DDL
 * ya tenía en escuela_niveles y nunca se capturaban. Se imprimen en el
 * Formato de Solicitud: si cambian y ya hay un Formato firmado subido, ese
 * Formato deja de coincidir y se descarta (decisión del dueño, 2026-09-29),
 * con lo que 2.4 y el Paso 3 del nivel se vuelven a bloquear hasta subir uno
 * nuevo. Límite conocido: nada liga el PDF descargado con el escaneo subido.
 */
class RegistrarDatosNivel
{
    /** Espejo de los CHECK de escuela_niveles (docs/ddl_sistema_incorporacion_v3.sql). */
    public const TURNOS = ['matutino', 'vespertino', 'mixto'];

    public const TIPOS_ALUMNADO = ['mixto', 'femenino', 'masculino'];

    public function __construct(
        private readonly EstadoPaso2 $estadoPaso2,
        private readonly AlmacenDocumentos $almacen,
    ) {}

    /** @throws PrecondicionIncumplida si Paso 2 no está completo. */
    public function ejecutar(int $escuelaNivelId, string $turno, string $tipoAlumnado): void
    {
        $errores = [];

        if (! in_array($turno, self::TURNOS, true)) {
            $errores['turno'] = 'Selecciona el turno: matutino, vespertino o mixto.';
        }

        if (! in_array($tipoAlumnado, self::TIPOS_ALUMNADO, true)) {
            $errores['tipoAlumnado'] = 'Selecciona el tipo de alumnado: mixto, femenino o masculino.';
        }

        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        $escuelaNivel = EscuelaNivel::findOrFail($escuelaNivelId);

        $etapaFaltante = $this->estadoPaso2->etapaFaltante($escuelaNivel->escuela_id);
        if ($etapaFaltante !== null) {
            throw new PrecondicionIncumplida($etapaFaltante, 'Completa el Paso 2 antes de capturar los datos del nivel.');
        }

        $cambia = $escuelaNivel->turno !== $turno || $escuelaNivel->tipo_alumnado !== $tipoAlumnado;

        if (! $cambia) {
            return;
        }

        DB::transaction(function () use ($escuelaNivel, $turno, $tipoAlumnado) {
            $escuelaNivel->update(['turno' => $turno, 'tipo_alumnado' => $tipoAlumnado]);

            $formato = DocumentoEscuelaNivel::where('escuela_nivel_id', $escuelaNivel->id)
                ->where('tipo_documento_id', TipoDocumento::where('clave', 'formato_solicitud')->value('id'))
                ->first();

            if ($formato === null) {
                return;
            }

            $ruta = $formato->archivo_path;
            $formato->delete();

            // Mismo patrón que RegistrarDocumento: el archivo se borra solo cuando
            // la transacción real confirma (si una transacción externa hace
            // rollback, fila y archivo se conservan), y un fallo al borrar se
            // reporta sin revertir lo ya confirmado.
            if ($ruta !== null) {
                DB::afterCommit(fn () => rescue(fn () => $this->almacen->eliminar($ruta), report: true));
            }
        });
    }
}
