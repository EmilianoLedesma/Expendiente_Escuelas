<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Grades of escolarizada Básica, needed by matricula_grados (Paso 3,
 * Matrícula). COMPENDIO §5.2 counts them: Preescolar "los tres grados",
 * Primaria "los 6 grados", Secundaria "los 3 grados". Inicial is organised by
 * salas (matricula_salas), not grades. Media Superior/Superior/Posgrado are
 * out of MVP scope.
 *
 * insertOrIgnore relies on the DDL's UNIQUE (nivel_educativo_id, orden).
 */
class GradosSeeder extends Seeder
{
    private const GRADOS = ['preescolar' => 3, 'primaria' => 6, 'secundaria' => 3];

    public function run(): void
    {
        $niveles = DB::table('niveles_educativos')->pluck('id', 'clave');
        $filas = [];

        foreach (self::GRADOS as $nivel => $total) {
            if (! isset($niveles[$nivel])) {
                throw new \RuntimeException('GradosSeeder requiere que CatalogoMinimoSeeder haya corrido antes (niveles_educativos).');
            }

            for ($orden = 1; $orden <= $total; $orden++) {
                $filas[] = ['nivel_educativo_id' => $niveles[$nivel], 'nombre' => "{$orden}°", 'orden' => $orden];
            }
        }

        DB::table('grados')->insertOrIgnore($filas);
    }
}
