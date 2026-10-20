<?php

namespace App\Application\EscuelaNiveles;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ResponsablesNivel\InvitarResponsableNivel;
use App\Application\Tramite\EstadoPaso2;
use App\Application\Tramite\TramiteEditable;
use App\Infrastructure\Documentos\AlmacenDocumentos;
use App\Models\DocumentoEscuelaNivel;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\ResponsableNivel;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Caso de uso de PRD §5 Paso 2.3: crea un escuela_niveles por cada nivel
 * marcado — esto es lo que "dispara Paso 3" para cada uno (PRD §5, texto
 * literal). Cada fila arranca en el estado 'en_captura' de
 * estados_expediente (el trámite ya dejó 'preregistro' al llegar aquí).
 *
 * WS-7b-1 U3: entradas explícitas `agregar` y `quitar` (ids de nivel educativo), no el
 * conjunto completo, para que una pestaña vieja no borre un nivel agregado en otra.
 * Agregar exige el responsable legal (desde 2026-10-20 los niveles se eligen ANTES de los documentos generales:
 * EstadoPaso2::puedeSeleccionarNiveles); quitar no. Quitar borra el nivel y todo lo que cuelga de
 * él (ON DELETE CASCADE), sus archivos después del commit y la cuenta de cada responsable
 * que se queda sin accesos (ADR-015, misma regla que RevocarResponsableNivel).
 */
class RegistrarNivelesSeleccionados
{
    /** Etapa de PrecondicionIncumplida: los datos del nivel cambiaron desde la confirmación. */
    public const CONFIRMACION = 'confirmacion';

    public function __construct(
        private readonly EstadoPaso2 $estadoPaso2,
        private readonly AlmacenDocumentos $almacen,
        private readonly TramiteEditable $tramiteEditable = new TramiteEditable,
        private readonly ResumenEliminacionNivel $resumen = new ResumenEliminacionNivel,
    ) {}

    /**
     * @param  list<int>  $agregar  nivel_educativo_id a crear
     * @param  list<int>  $quitar  nivel_educativo_id a borrar
     * @param  array<int, array<string, int>>  $confirmado  conteos que el usuario confirmó (ResumenEliminacionNivel::ejecutar)
     *
     * @throws PrecondicionIncumplida si se agrega sin responsable legal, si los datos crecieron desde la confirmación o si el trámite ya se envió.
     */
    public function ejecutar(int $escuelaId, array $agregar, array $quitar = [], array $confirmado = []): void
    {
        $agregar = array_values(array_unique(array_map('intval', $agregar)));
        $quitar = array_values(array_unique(array_map('intval', $quitar)));

        if ($agregar === [] && $quitar === []) {
            throw new InvalidArgumentException('Debe seleccionarse al menos un nivel educativo.');
        }

        if (array_intersect($agregar, $quitar) !== []) {
            throw new InvalidArgumentException('Un nivel no puede agregarse y quitarse a la vez.');
        }

        $permitidos = NivelEducativo::educacionBasica()->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (array_diff([...$agregar, ...$quitar], $permitidos) !== []) {
            throw new InvalidArgumentException('Nivel educativo fuera de Educación Básica.');
        }

        // WS-1.2: sin esto, seleccionar niveles justo después de Paso 1 creaba escuela_niveles sin responsable.
        // Los documentos generales ya no se exigen aquí: se capturan después, sobre el alcance ya elegido, y
        // 2.4 y Paso 3 los siguen exigiendo (EstadoPaso2::generalesSatisfechos).
        if ($agregar !== [] && ! $this->estadoPaso2->puedeSeleccionarNiveles($escuelaId)) {
            throw new PrecondicionIncumplida(EstadoPaso2::RESPONSABLE, 'Captura el responsable legal (Paso 2) antes de seleccionar niveles.');
        }

        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        DB::transaction(function () use ($escuelaId, $agregar, $quitar, $confirmado, $estadoId) {
            // Orden de EliminarTramite (WS-7a): escuela_niveles antes que escuelas.
            /** @var Collection<int, int> $niveles escuela_nivel_id => nivel_educativo_id */
            $niveles = EscuelaNivel::where('escuela_id', $escuelaId)->orderBy('id')->lockForUpdate()->pluck('nivel_educativo_id', 'id')->map(fn ($id) => (int) $id);
            $this->tramiteEditable->asegurarEscuela($escuelaId);

            $porQuitar = $niveles->filter(fn (int $nivelEducativoId) => in_array($nivelEducativoId, $quitar, true));
            $nuevos = array_diff($agregar, $niveles->values()->all());

            if ($niveles->count() - $porQuitar->count() + count($nuevos) === 0) {
                throw new InvalidArgumentException('El trámite debe conservar al menos un nivel educativo. Para descartarlo completo usa «Eliminar trámite».');
            }

            if ($porQuitar->isNotEmpty()) {
                $this->quitar($porQuitar->all(), $confirmado);
            }

            foreach ($agregar as $nivelEducativoId) {
                // ponytail: firstOrCreate makes a repeated submit of the same nivel a no-op
                // instead of tripping the UNIQUE(escuela_id, nivel_educativo_id) constraint.
                EscuelaNivel::firstOrCreate(
                    ['escuela_id' => $escuelaId, 'nivel_educativo_id' => $nivelEducativoId],
                    ['estado_id' => $estadoId, 'tipo_tramite' => 'alta_nueva'],
                );
            }
        });
    }

    /**
     * @param  array<int, int>  $porQuitar  escuela_nivel_id => nivel_educativo_id (ya bloqueados)
     * @param  array<int, array<string, int>>  $confirmado
     */
    private function quitar(array $porQuitar, array $confirmado): void
    {
        foreach ($porQuitar as $escuelaNivelId => $nivelEducativoId) {
            foreach ($this->resumen->conteos($escuelaNivelId) as $concepto => $cantidad) {
                if ($cantidad > ($confirmado[$nivelEducativoId][$concepto] ?? 0)) {
                    throw new PrecondicionIncumplida(self::CONFIRMACION, 'Los datos del nivel cambiaron desde que confirmaste. Revisa de nuevo lo que se eliminará.');
                }
            }
        }

        $ids = array_keys($porQuitar);

        // Rutas reunidas ANTES del borrado (después, el cascade ya se llevó las filas).
        // lockForUpdate: un RegistrarDocumento concurrente no puede reemplazar la ruta y dejar un archivo huérfano.
        $rutas = DocumentoEscuelaNivel::whereIn('escuela_nivel_id', $ids)->whereNotNull('archivo_path')->lockForUpdate()->pluck('archivo_path')->all();

        // Serializa con una invitación o revocación concurrente del mismo usuario (RevocarResponsableNivel).
        $usuarios = User::whereIn('id', ResponsableNivel::whereIn('escuela_nivel_id', $ids)->pluck('user_id'))->orderBy('id')->lockForUpdate()->get();

        EscuelaNivel::whereIn('id', $ids)->delete();

        foreach ($usuarios as $usuario) {
            // Solo cuentas de responsable invitado (guarda de InvitarResponsableNivel): un solicitante conserva la suya.
            if ($usuario->hasRole(InvitarResponsableNivel::ROL) && ! $usuario->solicitante()->exists()
                && ! ResponsableNivel::where('user_id', $usuario->id)->exists()) {
                $usuario->delete();
            }
        }

        // Igual que EliminarTramite: los archivos se borran solo si el borrado se confirma;
        // un fallo de disco se reporta, nunca revierte.
        DB::afterCommit(function () use ($rutas) {
            foreach ($rutas as $ruta) {
                rescue(fn () => $this->almacen->eliminar($ruta), report: true);
            }
        });
    }
}
