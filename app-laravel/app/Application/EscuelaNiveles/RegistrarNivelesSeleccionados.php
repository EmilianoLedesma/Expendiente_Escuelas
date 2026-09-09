<?php

namespace App\Application\EscuelaNiveles;

use App\Models\EscuelaNivel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Caso de uso de PRD §5 Paso 2.3: crea un escuela_niveles por cada nivel
 * marcado — esto es lo que "dispara Paso 3" para cada uno (PRD §5, texto
 * literal). Cada fila arranca en el estado 'en_captura' de
 * estados_expediente (el trámite ya dejó 'preregistro' al llegar aquí).
 */
class RegistrarNivelesSeleccionados
{
    public function ejecutar(int $escuelaId, array $nivelesEducativosIds): void
    {
        if ($nivelesEducativosIds === []) {
            throw new InvalidArgumentException('Debe seleccionarse al menos un nivel educativo.');
        }

        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        DB::transaction(function () use ($escuelaId, $nivelesEducativosIds, $estadoId) {
            foreach ($nivelesEducativosIds as $nivelEducativoId) {
                EscuelaNivel::create([
                    'escuela_id' => $escuelaId,
                    'nivel_educativo_id' => $nivelEducativoId,
                    'estado_id' => $estadoId,
                    'tipo_tramite' => 'alta_nueva',
                ]);
            }
        });
    }
}
