<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;

/**
 * `insertOrIgnore` consume un valor de la secuencia por fila aunque el conflicto la descarte.
 * Las tablas de catálogo con id SMALLSERIAL (tope 32767) se agotaban al resembrar miles de
 * veces en la suite de pruebas; aquí solo se insertan las claves que aún no existen.
 */
trait InsertaFaltantes
{
    /** @param  list<array<string, mixed>>  $filas */
    private function insertarFaltantes(string $tabla, array $filas): void
    {
        $existentes = DB::table($tabla)->pluck('clave')->all();
        $nuevas = array_values(array_filter($filas, fn (array $fila): bool => ! in_array($fila['clave'], $existentes, true)));

        if ($nuevas !== []) {
            DB::table($tabla)->insertOrIgnore($nuevas);
        }
    }
}
