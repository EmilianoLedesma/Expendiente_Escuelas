<?php

namespace App\Application\Visita;

use App\Application\Escuelas\VerificarPropietarioEscuelaNivel;
use App\Application\Excepciones\DatosInvalidos;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\RespuestaVisita;
use App\Models\User;
use App\Models\VisitaInspeccion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * El dueño de la escuela (no el responsable por nivel) responde a las observaciones de la última visita de un nivel
 * en subsanación: una nota y hasta 5 PDF. No cambia el estado; la subsanación la acepta el supervisor
 * (AceptarSubsanacion). Orden de bloqueo: escuela_nivel, escuela y visita, como RegistrarActaVisita.
 */
class ResponderObservaciones
{
    public const MAX_ARCHIVOS = 5;

    public const MAX_NOTA = 2000;

    public function __construct(
        private readonly ArchivoActaVisita $archivo,
        private readonly VerificarPropietarioEscuelaNivel $propietario,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $archivos
     * @return int id de la respuesta creada
     *
     * @throws AuthorizationException|DatosInvalidos
     */
    public function ejecutar(User $owner, int $visitaId, string $nota, array $archivos = []): int
    {
        $nivelId = (int) VisitaInspeccion::findOrFail($visitaId)->escuela_nivel_id;
        $nivel = EscuelaNivel::findOrFail($nivelId);
        if ($owner->solicitante === null || ! $this->propietario->ejecutar($nivelId, $owner->solicitante->getKey())) {
            throw new AuthorizationException;
        }

        $nota = trim($nota);
        // Livewire puede entregar claves no secuenciales: una sola secuencia 0..n-1 para validar, guardar e insertar.
        $archivos = array_values($archivos);
        $this->validar($nota, $archivos);

        $escuelaId = (int) $nivel->escuela_id;
        $rutas = [];
        // Se pone en true al inicio del afterCommit: un fallo posterior no debe borrar archivos ya referenciados.
        $confirmado = false;
        try {
            foreach ($archivos as $f) {
                $rutas[] = $this->archivo->guardar($escuelaId, $f, 'respuesta');
            }

            return DB::transaction(function () use ($owner, $visitaId, $nivelId, $escuelaId, $nota, $archivos, $rutas, &$confirmado) {
                $nivel = EscuelaNivel::whereKey($nivelId)->lockForUpdate()->firstOrFail();
                Escuela::whereKey($escuelaId)->lockForUpdate()->firstOrFail();
                $visita = VisitaInspeccion::whereKey($visitaId)->lockForUpdate()->firstOrFail();

                $clave = DB::table('estados_expediente')->where('id', $nivel->estado_id)->value('clave');
                if ($clave !== 'en_subsanacion'
                    || $visita->estado !== 'realizada'
                    || $visita->resultado !== 'con_observaciones'
                    || (int) VisitaInspeccion::where('escuela_nivel_id', $nivelId)->max('numero') !== (int) $visita->numero) {
                    throw new DatosInvalidos(['visita' => 'Solo se pueden responder las observaciones de la última visita de un nivel en subsanación.']);
                }

                $respuesta = RespuestaVisita::create(['visita_id' => $visitaId, 'nota' => $nota, 'creada_por' => $owner->id]);
                foreach ($archivos as $i => $f) {
                    $respuesta->archivos()->create(['archivo_path' => $rutas[$i], 'archivo_nombre' => mb_substr($f->getClientOriginalName(), 0, 255)]);
                }

                DB::afterCommit(function () use (&$confirmado) {
                    $confirmado = true;
                });

                return $respuesta->id;
            });
        } catch (Throwable $e) {
            if (! $confirmado) {
                foreach ($rutas as $ruta) {
                    $this->archivo->borrar($ruta);
                }
            }

            throw $e;
        }
    }

    /** @param array<int, UploadedFile> $archivos */
    private function validar(string $nota, array $archivos): void
    {
        if ($nota === '') {
            throw new DatosInvalidos(['nota' => 'Escribe la nota de la respuesta.']);
        }
        if (mb_strlen($nota) > self::MAX_NOTA) {
            throw new DatosInvalidos(['nota' => 'La nota admite hasta '.self::MAX_NOTA.' caracteres.']);
        }
        if (count($archivos) > self::MAX_ARCHIVOS) {
            throw new DatosInvalidos(['archivos' => 'Se admiten hasta '.self::MAX_ARCHIVOS.' archivos PDF.']);
        }
        foreach ($archivos as $f) {
            $this->archivo->revisar($f, 'archivos');
        }
    }
}
