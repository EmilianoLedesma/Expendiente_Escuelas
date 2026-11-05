<?php

namespace App\Application\Salud;

use App\Application\Archivo\RegistrarAccion;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\RolesSedeq;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Acciones de mantenimiento del admin desde "Estado del sistema": procesar la cola,
 * reintentar o descartar trabajos fallidos y mandar un correo de prueba. Solo admin.
 * Cada acción que hace algo deja una fila en bitacora_acciones (objeto 'sistema', solo conteos).
 */
class OperarSistema
{
    /** Tope de la corrida manual de la cola, para no dejar la petición web colgada. */
    private const MAX_SEGUNDOS = 25;

    public function __construct(private readonly RegistrarAccion $registrar) {}

    /** Procesa los trabajos pendientes ahora, sin esperar al worker. Devuelve cuántos había. */
    public function procesarCola(int $actorId): int
    {
        $this->exigirAdmin($actorId);
        $antes = DB::table('jobs')->count();
        Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => self::MAX_SEGUNDOS, '--tries' => 3]);
        $procesados = max(0, $antes - DB::table('jobs')->count());
        if ($procesados > 0) {
            $this->registrar->ejecutar($actorId, RegistrarAccion::PROCESAR_COLA, 'sistema', 0, null, ['trabajos' => $procesados]);
        }

        return $procesados;
    }

    /** Vuelve a encolar todos los trabajos fallidos. Devuelve cuántos eran. */
    public function reintentarFallidos(int $actorId): int
    {
        $this->exigirAdmin($actorId);

        return $this->conFallidos($actorId, 'all', RegistrarAccion::REINTENTAR_FALLIDOS, 'queue:retry');
    }

    /** Borra todos los trabajos fallidos. Devuelve cuántos eran. */
    public function descartarFallidos(int $actorId): int
    {
        $this->exigirAdmin($actorId);

        return $this->conFallidos($actorId, null, RegistrarAccion::DESCARTAR_FALLIDOS, 'queue:flush');
    }

    /** Manda un correo simple al propio correo del admin para probar el envío. Lanza si el envío falla. */
    public function enviarCorreoDePrueba(int $actorId): void
    {
        $admin = $this->exigirAdmin($actorId);
        Mail::raw('Este es un correo de prueba del sistema de incorporación. Si lo lees, el envío funciona.', function ($m) use ($admin) {
            $m->to($admin->email)->subject('Correo de prueba');
        });
        $this->registrar->ejecutar($actorId, RegistrarAccion::CORREO_PRUEBA, 'sistema', 0);
    }

    private function conFallidos(int $actorId, ?string $argumento, string $accion, string $comando): int
    {
        $n = DB::table('failed_jobs')->count();
        if ($n === 0) {
            return 0;
        }
        Artisan::call($comando, $argumento === null ? [] : ['id' => [$argumento]]);
        $this->registrar->ejecutar($actorId, $accion, 'sistema', 0, null, ['trabajos' => $n]);

        return $n;
    }

    /** @throws PrecondicionIncumplida */
    private function exigirAdmin(int $actorId): User
    {
        $actor = User::findOrFail($actorId);
        RolesSedeq::exigir($actor, RolesSedeq::ADMIN);

        return $actor;
    }
}
