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

    /** @return list<array{nombre: string, estado: string, detalle: string}> */
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

    /** @return array{nombre: string, estado: string, detalle: string} */
    private function chequeo(string $nombre, string $estado, string $detalle): array
    {
        return ['nombre' => $nombre, 'estado' => $estado, 'detalle' => $detalle];
    }

    /** @return array{nombre: string, estado: string, detalle: string} */
    private function baseDeDatos(): array
    {
        try {
            DB::select('select 1');
        } catch (Throwable) {
            return $this->chequeo('Base de datos', 'error', 'No responde.');
        }

        return $this->chequeo('Base de datos', 'ok', 'Responde.');
    }

    /** @return array{nombre: string, estado: string, detalle: string} */
    private function cola(): array
    {
        $nombre = 'Cola de correos';
        if (config('queue.default') !== 'database') {
            return $this->chequeo($nombre, 'aviso', 'La cola es «'.config('queue.default').'»: los correos se envían al momento, sin worker.');
        }
        try {
            $fallidos = DB::table('failed_jobs')->count();
            $espera = DB::table('jobs')->whereNull('reserved_at')->min('available_at');
        } catch (Throwable) {
            return $this->chequeo($nombre, 'error', 'No se pudo leer la cola.');
        }
        if ($fallidos > 0) {
            return $this->chequeo($nombre, 'error', "{$fallidos} trabajo(s) fallido(s). Revisa el log y reintenta.");
        }
        if ($espera !== null && time() - (int) $espera > self::ESPERA_MAXIMA_SEGUNDOS) {
            return $this->chequeo($nombre, 'aviso', 'Hay correos esperando hace más de '.intdiv(self::ESPERA_MAXIMA_SEGUNDOS, 60).' minutos: ¿está corriendo el worker?');
        }

        return $this->chequeo($nombre, 'ok', 'Sin trabajos fallidos ni atrasados.');
    }

    /** @return array{nombre: string, estado: string, detalle: string} */
    private function correo(): array
    {
        $driver = (string) config('mail.default');
        if (in_array($driver, ['log', 'array'], true)) {
            return $this->chequeo('Correo', 'aviso', "El envío es «{$driver}»: no sale correo real.");
        }

        return $this->chequeo('Correo', 'ok', "Envío por «{$driver}».");
    }

    /** @return array{nombre: string, estado: string, detalle: string} */
    private function discoDocumentos(): array
    {
        $ruta = '.salud-'.bin2hex(random_bytes(4));
        try {
            Storage::disk('documentos')->put($ruta, 'ok');
            Storage::disk('documentos')->delete($ruta);
        } catch (Throwable) {
            return $this->chequeo('Disco de documentos', 'error', 'No se puede escribir en el disco de documentos.');
        }

        return $this->chequeo('Disco de documentos', 'ok', 'Se puede escribir.');
    }

    /** @return array{nombre: string, estado: string, detalle: string} */
    private function migraciones(): array
    {
        try {
            $migrator = app('migrator');
            $archivos = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));
            $pendientes = count(array_diff(array_keys($archivos), $migrator->getRepository()->getRan()));
        } catch (Throwable) {
            return $this->chequeo('Migraciones', 'error', 'No se pudo leer el estado de las migraciones.');
        }
        if ($pendientes > 0) {
            return $this->chequeo('Migraciones', 'aviso', "{$pendientes} migración(es) pendiente(s).");
        }

        return $this->chequeo('Migraciones', 'ok', 'Al día.');
    }
}
