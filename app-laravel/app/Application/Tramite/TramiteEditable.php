<?php

namespace App\Application\Tramite;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Única definición de "este trámite todavía se puede modificar" (WS-7a §5.1): todos
 * sus niveles siguen en captura, y sin niveles también (Paso 2.1/2.2, antes de
 * elegirlos). Enviado = al menos un nivel y ninguno en captura. Un estado mezclado
 * (la app no lo produce) no es editable: se falla cerrado. La usan EliminarTramite,
 * ResumenTramite, ResponsablesNivel, EscuelaPolicy/EscuelaNivelPolicy::update y las
 * guardas de escritura de los casos de uso.
 */
class TramiteEditable
{
    public const EN_CAPTURA = 'en_captura';

    /** Etapa de PrecondicionIncumplida cuando el trámite (o un trámite del mismo plantel) ya se envió. */
    public const ENVIADO = 'enviado';

    public static function idEnCaptura(): ?int
    {
        $id = DB::table('estados_expediente')->where('clave', self::EN_CAPTURA)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Sin niveles también es true.
     *
     * @param  Collection<array-key, mixed>  $estadoIds  estado_id de cada escuela_nivel
     * @param  int|null  $enCapturaId  idEnCaptura(); quien evalúa muchos trámites lo busca una vez y lo pasa
     */
    public static function todosEnCaptura(Collection $estadoIds, ?int $enCapturaId): bool
    {
        return $estadoIds->every(fn ($estadoId) => (int) $estadoId === $enCapturaId);
    }

    /**
     * Enviado = al menos un nivel y ninguno en captura (WS-7a §5.1).
     *
     * @param  Collection<array-key, mixed>  $estadoIds
     */
    public static function enviado(Collection $estadoIds, ?int $enCapturaId): bool
    {
        return $estadoIds->isNotEmpty() && ! $estadoIds->contains(fn ($estadoId) => (int) $estadoId === $enCapturaId);
    }

    public function esEditable(int $escuelaId): bool
    {
        return self::todosEnCaptura(EscuelaNivel::where('escuela_id', $escuelaId)->pluck('estado_id'), self::idEnCaptura());
    }

    /**
     * Guarda de escritura (WS-7a §5.3). Se llama DENTRO de la transacción del caso de
     * uso y DESPUÉS de cualquier bloqueo que ese caso de uso tome sobre escuela_niveles
     * (explícito, o el FOR KEY SHARE de un insert con FK al nivel). FOR SHARE sobre la
     * escuela: EnviarTramite toma FOR UPDATE sobre la misma fila, así que una escritura
     * no puede confirmarse dentro de un trámite que se está enviando (READ COMMITTED).
     * Orden de bloqueo en todo el proyecto: escuela_niveles antes que escuelas
     * (EnviarTramite, EliminarTramite); invertirlo produce deadlocks (40P01).
     *
     * @throws PrecondicionIncumplida si el trámite ya no es editable.
     */
    public function asegurarEscuela(int $escuelaId): void
    {
        Escuela::whereKey($escuelaId)->sharedLock()->value('id');

        if (! $this->esEditable($escuelaId)) {
            throw new PrecondicionIncumplida(self::ENVIADO, 'El trámite ya se envió a SEDEQ y no puede modificarse.');
        }
    }

    /**
     * Escrituras de ámbito plantel (documentos del plantel, espacios, sanitarios): se
     * rechazan mientras cualquier escuela del plantel esté enviada (spec WS-7a §5.4, D8),
     * porque alimentan los datos y la capacidad del trámite enviado.
     *
     * @throws PrecondicionIncumplida
     */
    public function asegurarPlantel(int $plantelId): void
    {
        $escuelas = Escuela::where('plantel_id', $plantelId)->orderBy('id')->sharedLock()->pluck('id');

        if (! self::todosEnCaptura(EscuelaNivel::whereIn('escuela_id', $escuelas)->pluck('estado_id'), self::idEnCaptura())) {
            throw new PrecondicionIncumplida(self::ENVIADO, 'Un trámite de este plantel ya se envió a SEDEQ: los datos compartidos del plantel (documentos, espacios y sanitarios) ya no pueden modificarse.');
        }
    }
}
