<?php

namespace App\Application\EscuelaNiveles;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\EstadoPaso2;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
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
    public function __construct(private readonly EstadoPaso2 $estadoPaso2) {}

    /** @throws PrecondicionIncumplida si Paso 2 (responsable + documentos vigentes) no está completo. */
    public function ejecutar(int $escuelaId, array $nivelesEducativosIds): void
    {
        if ($nivelesEducativosIds === []) {
            throw new InvalidArgumentException('Debe seleccionarse al menos un nivel educativo.');
        }

        $permitidos = NivelEducativo::educacionBasica()->pluck('id')->all();
        if (array_diff($nivelesEducativosIds, $permitidos) !== []) {
            throw new InvalidArgumentException('Nivel educativo fuera de Educación Básica.');
        }

        // WS-1.2: sin esto, seleccionar niveles justo después de Paso 1 creaba
        // escuela_niveles sin responsable ni documentos y saltaba Paso 2 entero.
        $etapaFaltante = $this->estadoPaso2->etapaFaltante($escuelaId);
        if ($etapaFaltante !== null) {
            throw new PrecondicionIncumplida($etapaFaltante, 'Completa el responsable legal y los documentos (Paso 2) antes de seleccionar niveles.');
        }

        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        DB::transaction(function () use ($escuelaId, $nivelesEducativosIds, $estadoId) {
            foreach ($nivelesEducativosIds as $nivelEducativoId) {
                // ponytail: firstOrCreate makes a repeated submit of the same nivel a no-op
                // instead of tripping the UNIQUE(escuela_id, nivel_educativo_id) constraint.
                EscuelaNivel::firstOrCreate(
                    ['escuela_id' => $escuelaId, 'nivel_educativo_id' => $nivelEducativoId],
                    ['estado_id' => $estadoId, 'tipo_tramite' => 'alta_nueva'],
                );
            }
        });
    }
}
