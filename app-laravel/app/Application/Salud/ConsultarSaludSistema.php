<?php

namespace App\Application\Salud;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Chequeos de salud del sistema para el admin: base de datos, cola, correo, disco de
 * documentos y migraciones. Solo lectura (el disco escribe y borra un archivo temporal).
 * Cada chequeo devuelve estado ok / aviso / error y un detalle sin datos sensibles.
 */
class ConsultarSaludSistema
{
    /** Un trabajo esperando más que esto indica que el worker no está corriendo. */
    private const ESPERA_MAXIMA_SEGUNDOS = 600;

    /** @return list<array{nombre: string, estado: string, detalle: string, siguiente: ?string}> */
    public function ejecutar(): array
    {
        return [
            $this->baseDeDatos(),
            $this->cola(),
            $this->correo(),
            $this->discoDocumentos(),
            $this->migraciones(),
        ];
    }

    /** @return array{nombre: string, estado: string, detalle: string, siguiente: ?string} */
    private function chequeo(string $nombre, string $estado, string $detalle, ?string $siguiente = null): array
    {
        return ['nombre' => $nombre, 'estado' => $estado, 'detalle' => $detalle, 'siguiente' => $siguiente];
    }

    /** @return array{nombre: string, estado: string, detalle: string, siguiente: ?string} */
    private function baseDeDatos(): array
    {
        try {
            DB::select('select 1');
        } catch (Throwable) {
            return $this->chequeo('Base de datos', 'error', 'No responde.', 'Revisa que PostgreSQL esté encendido y que las credenciales de .env sean correctas.');
        }

        return $this->chequeo('Base de datos', 'ok', 'Responde.');
    }

    /** @return array{nombre: string, estado: string, detalle: string, siguiente: ?string} */
    private function cola(): array
    {
        $nombre = 'Cola de correos';
        if (config('queue.default') !== 'database') {
            return $this->chequeo($nombre, 'aviso', 'La cola es «'.config('queue.default').'»: los correos se envían al momento, sin worker.', 'Para enviar correo en segundo plano usa QUEUE_CONNECTION=database en .env y deja corriendo php artisan queue:work.');
        }
        try {
            $fallidos = DB::table('failed_jobs')->count();
            $espera = DB::table('jobs')->whereNull('reserved_at')->min('available_at');
        } catch (Throwable) {
            return $this->chequeo($nombre, 'error', 'No se pudo leer la cola.', 'Revisa que existan las tablas jobs y failed_jobs (php artisan migrate).');
        }
        if ($fallidos > 0) {
            return $this->chequeo($nombre, 'error', "{$fallidos} trabajo(s) fallido(s). Revisa el log y reintenta.", 'Pulsa Reintentar fallidos. Si vuelven a fallar, revisa storage/logs/laravel.log y descártalos.');
        }
        if ($espera !== null && time() - (int) $espera > self::ESPERA_MAXIMA_SEGUNDOS) {
            return $this->chequeo($nombre, 'aviso', 'Hay correos esperando hace más de '.intdiv(self::ESPERA_MAXIMA_SEGUNDOS, 60).' minutos: ¿está corriendo el worker?', 'Pulsa Procesar cola ahora y deja corriendo php artisan queue:work.');
        }

        return $this->chequeo($nombre, 'ok', 'Sin trabajos fallidos ni atrasados.');
    }

    /** @return array{nombre: string, estado: string, detalle: string, siguiente: ?string} */
    private function correo(): array
    {
        $driver = (string) config('mail.default');
        if (in_array($driver, ['log', 'array'], true)) {
            return $this->chequeo('Correo', 'aviso', "El envío es «{$driver}»: no sale correo real.", 'Cambia MAIL_MAILER en .env (por ejemplo a smtp), limpia la caché de configuración y vuelve a revisar.');
        }

        return $this->chequeo('Correo', 'ok', "Envío por «{$driver}».");
    }

    /** @return array{nombre: string, estado: string, detalle: string, siguiente: ?string} */
    private function discoDocumentos(): array
    {
        $ruta = '.salud-'.bin2hex(random_bytes(4));
        try {
            Storage::disk('documentos')->put($ruta, 'ok');
            Storage::disk('documentos')->delete($ruta);
        } catch (Throwable) {
            return $this->chequeo('Disco de documentos', 'error', 'No se puede escribir en el disco de documentos.', 'Revisa que exista la carpeta DOCUMENTOS_PATH y que el servidor tenga permiso de escritura.');
        }

        return $this->chequeo('Disco de documentos', 'ok', 'Se puede escribir.');
    }

    /** @return array{nombre: string, estado: string, detalle: string, siguiente: ?string} */
    private function migraciones(): array
    {
        try {
            $migrator = app('migrator');
            $archivos = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));
            $pendientes = count(array_diff(array_keys($archivos), $migrator->getRepository()->getRan()));
        } catch (Throwable) {
            return $this->chequeo('Migraciones', 'error', 'No se pudo leer el estado de las migraciones.', 'Revisa la conexión a la base de datos.');
        }
        if ($pendientes > 0) {
            return $this->chequeo('Migraciones', 'aviso', "{$pendientes} migración(es) pendiente(s).", 'En el servidor, desde app-laravel, corre: php artisan migrate');
        }

        return $this->chequeo('Migraciones', 'ok', 'Al día.');
    }
}
