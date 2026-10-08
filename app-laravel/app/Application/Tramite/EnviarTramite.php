<?php

namespace App\Application\Tramite;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Validaciones\DTO\ValidacionFinal;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WS-7a: envía el trámite completo a SEDEQ. Nunca confía en una evaluación
 * guardada (ADR-007 P1): dentro de una sola transacción bloquea, comprueba que
 * todo siga en captura, vuelve a evaluar y solo entonces pasa cada nivel a
 * en_revision con su fila de historial; al final guarda el reporte que se envió.
 * La propiedad la decide EscuelaPolicy::update (solo el dueño; un responsable
 * del nivel no la tiene) antes de llegar aquí.
 *
 * Orden de bloqueo (TramiteEditable): primero los escuela_niveles, luego la
 * escuela, el mismo que EliminarTramite y que las guardas de escritura.
 */
class EnviarTramite
{
    public const EN_REVISION = 'en_revision';

    public function __construct(private readonly EjecutarValidacionFinal $validacionFinal) {}

    /** @throws PrecondicionIncumplida si ya se envió, si falta capturar algo o si algo bloquea el envío. */
    public function ejecutar(int $escuelaId, int $userId): ValidacionFinal
    {
        return DB::transaction(function () use ($escuelaId, $userId) {
            $leerNiveles = fn () => EscuelaNivel::where('escuela_id', $escuelaId)->orderBy('id')->lockForUpdate()->pluck('estado_id', 'id');
            $leerNiveles();
            Escuela::whereKey($escuelaId)->lockForUpdate()->firstOrFail();
            // Se vuelven a leer con la escuela ya bloqueada: un nivel confirmado entre los dos
            // bloqueos no estaba en la primera lectura, y desde aquí ningún insert con FK a la
            // escuela puede confirmarse. La comprobación, la evaluación y el UPDATE usan este conjunto.
            $niveles = $leerNiveles();

            if (! TramiteEditable::todosEnCaptura($niveles, TramiteEditable::idEnCaptura())) {
                throw new PrecondicionIncumplida(TramiteEditable::ENVIADO, 'El trámite ya fue enviado.');
            }

            // Sin niveles o con alguna sección pendiente, evaluar() lanza su propia PrecondicionIncumplida.
            $evaluada = $this->validacionFinal->evaluar($escuelaId);

            if (! $evaluada->listaParaEnvio) {
                throw new PrecondicionIncumplida(EjecutarValidacionFinal::ETAPA, 'El trámite aún no puede enviarse. Corrige: '.implode('; ', $evaluada->motivosBloqueo()).'.');
            }

            $enRevision = (int) DB::table('estados_expediente')->where('clave', self::EN_REVISION)->value('id');
            EscuelaNivel::whereIn('id', $niveles->keys())->update(['estado_id' => $enRevision]);
            DB::table('historial_estados_expediente')->insert($niveles->keys()->map(fn ($escuelaNivelId) => [
                'escuela_nivel_id' => $escuelaNivelId,
                'estado_id' => $enRevision,
                'comentario' => 'Enviado por el solicitante',
                // La columna es NOT NULL REFERENCES users(id): aquí es el users.id del solicitante.
                'usuario_sedeq_id' => $userId,
                'fecha' => now(),
            ])->all());

            DB::afterCommit(fn () => Log::info('Trámite enviado a SEDEQ', [
                'escuela_id' => $escuelaId,
                'niveles' => $niveles->count(),
                'usuario_id' => $userId,
            ]));

            // ponytail: el PDF se escribe antes del COMMIT; solo un COMMIT fallido lo deja
            // huérfano (ninguna fila lo apunta). Limpiar validaciones/{escuela} si alguna vez aparece en los logs.
            return $this->validacionFinal->guardar($escuelaId, $evaluada);
        });
    }
}
