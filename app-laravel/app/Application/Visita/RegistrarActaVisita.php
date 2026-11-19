<?php

namespace App\Application\Visita;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\RolesSedeq;
use App\Domain\Calendario\DiasHabiles;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\User;
use App\Models\VisitaInspeccion;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * El supervisor registra el acta de una visita programada: el resultado (cumple, o con observaciones y 5 días hábiles
 * para subsanar), la fecha en que se realizó y, opcionalmente, el PDF del acta. Con `cumple` las observaciones deben
 * venir vacías. Orden de bloqueo: escuela_nivel, escuela y visita, como ProgramarVisita.
 */
class RegistrarActaVisita
{
    private const DIAS_SUBSANACION = 5;

    public function __construct(private readonly ArchivoActaVisita $archivo) {}

    /**
     * @param  list<string>  $observaciones
     *
     * @throws DatosInvalidos|PrecondicionIncumplida
     */
    public function ejecutar(User $actor, int $visitaId, string $resultado, \DateTimeInterface $realizadaEl, array $observaciones = [], ?UploadedFile $acta = null): void
    {
        RolesSedeq::exigir($actor, RolesSedeq::SUPERVISOR);
        $realizada = Carbon::instance($realizadaEl)->startOfDay();
        $observaciones = array_values(array_filter(array_map(fn ($o) => trim((string) $o), $observaciones), fn ($o) => $o !== ''));
        $this->validar($resultado, $realizada, $observaciones);
        if ($acta !== null) {
            $this->archivo->revisar($acta);
        }

        $nivelId = (int) VisitaInspeccion::findOrFail($visitaId)->escuela_nivel_id;
        $escuelaId = (int) EscuelaNivel::findOrFail($nivelId)->escuela_id;
        $ruta = $acta !== null ? $this->archivo->guardar($escuelaId, $acta) : null;

        // Se pone en true al inicio del afterCommit: un fallo posterior (p. ej. un listener) no debe borrar el archivo ya referenciado.
        $confirmado = false;
        try {
            DB::transaction(function () use ($actor, $visitaId, $nivelId, $escuelaId, $resultado, $realizada, $observaciones, $acta, $ruta, &$confirmado) {
                $nivel = EscuelaNivel::whereKey($nivelId)->lockForUpdate()->firstOrFail();
                Escuela::whereKey($escuelaId)->lockForUpdate()->firstOrFail();
                $visita = VisitaInspeccion::whereKey($visitaId)->lockForUpdate()->firstOrFail();

                if ($visita->estado !== 'programada') {
                    throw new DatosInvalidos(['visita' => 'Solo se puede registrar el acta de una visita programada.']);
                }

                $conObservaciones = $resultado === 'con_observaciones';
                $visita->update([
                    'estado' => 'realizada',
                    'resultado' => $resultado,
                    'realizada_el' => $realizada->toDateString(),
                    'subsanar_hasta' => $conObservaciones
                        ? DiasHabiles::sumar($realizada->toDateTimeImmutable(), self::DIAS_SUBSANACION)->format('Y-m-d')
                        : null,
                    'registrada_por' => $actor->id,
                    'acta_path' => $ruta,
                    'acta_nombre' => $acta !== null ? mb_substr($acta->getClientOriginalName(), 0, 255) : null,
                ]);
                foreach ($observaciones as $i => $texto) {
                    $visita->observaciones()->create(['orden' => $i + 1, 'texto' => $texto]);
                }

                $clave = $conObservaciones ? 'en_subsanacion' : 'visita_aprobada';
                $estadoId = (int) DB::table('estados_expediente')->where('clave', $clave)->value('id');
                $nivel->update(['estado_id' => $estadoId]);
                DB::table('historial_estados_expediente')->insert([
                    'escuela_nivel_id' => $nivelId,
                    'estado_id' => $estadoId,
                    'comentario' => $conObservaciones ? 'Acta de visita con observaciones' : 'Acta de visita: cumple',
                    'usuario_sedeq_id' => $actor->id,
                    'fecha' => now(),
                ]);

                DB::afterCommit(function () use (&$confirmado, $visita) {
                    $confirmado = true;
                    event(new ActaDeVisitaRegistrada($visita->id));
                });
            });
        } catch (Throwable $e) {
            if (! $confirmado && $ruta !== null) {
                $this->archivo->borrar($ruta);
            }

            throw $e;
        }
    }

    /** @param list<string> $observaciones ya recortadas y sin vacías */
    private function validar(string $resultado, Carbon $realizada, array $observaciones): void
    {
        $errores = [];
        if (! in_array($resultado, ['cumple', 'con_observaciones'], true)) {
            $errores['resultado'] = 'El resultado debe ser «cumple» o «con observaciones».';
        }
        if ($realizada->toDateString() > today()->toDateString()) {
            $errores['realizada_el'] = 'La fecha de la visita no puede ser futura.';
        }
        if ($resultado === 'con_observaciones' && $observaciones === []) {
            $errores['observaciones'] = 'Un acta con observaciones necesita al menos una observación.';
        } elseif ($resultado === 'cumple' && $observaciones !== []) {
            $errores['observaciones'] = 'Un acta que cumple no lleva observaciones.';
        } elseif (array_filter($observaciones, fn ($o) => mb_strlen($o) > 1000) !== []) {
            $errores['observaciones'] = 'Cada observación admite hasta 1000 caracteres.';
        }
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }
    }
}
