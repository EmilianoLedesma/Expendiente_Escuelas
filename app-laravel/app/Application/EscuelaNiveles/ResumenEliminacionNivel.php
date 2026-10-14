<?php

namespace App\Application\EscuelaNiveles;

use App\Models\EscuelaNivel;
use Illuminate\Support\Facades\DB;

/**
 * WS-7b-1 U3: lo que se borraría al quitar niveles, para la confirmación. Lectura pura;
 * RegistrarNivelesSeleccionados vuelve a contar dentro de su transacción y rechaza si hay
 * más de lo confirmado.
 */
class ResumenEliminacionNivel
{
    public const CONCEPTOS = ['datos', 'documentos', 'recibos', 'espacios', 'personal', 'matricula', 'mobiliario', 'pasos', 'responsables'];

    /** Columnas de la propia fila que capturan RegistrarDatosNivel y RegistrarPlanEstudios. */
    private const COLUMNAS_NIVEL = ['turno', 'tipo_alumnado', 'modalidad', 'plan_estudios_referencia', 'plataforma_educativa_tipo'];

    /** @var array<string, string> */
    public const ETIQUETAS = [
        'datos' => 'Datos generales del nivel (turno, alumnado, plan de estudios)',
        'documentos' => 'Documentos del nivel subidos',
        'recibos' => 'Recibos de pago capturados',
        'espacios' => 'Aulas del nivel capturadas',
        'personal' => 'Personas de la plantilla',
        'matricula' => 'Grupos de matrícula',
        'mobiliario' => 'Conceptos de mobiliario',
        'pasos' => 'Pasos de captura completados',
        'responsables' => 'Responsables invitados (pierden su acceso a este nivel)',
    ];

    /**
     * @param  list<int>  $nivelesEducativosIds
     * @return array<int, array<string, int>> por nivel_educativo_id; solo niveles que la escuela tiene
     */
    public function ejecutar(int $escuelaId, array $nivelesEducativosIds): array
    {
        $niveles = EscuelaNivel::where('escuela_id', $escuelaId)
            ->whereIn('nivel_educativo_id', $nivelesEducativosIds)
            ->orderBy('id')
            ->pluck('id', 'nivel_educativo_id');

        $resumen = [];
        foreach ($niveles as $nivelEducativoId => $escuelaNivelId) {
            $resumen[(int) $nivelEducativoId] = $this->conteos((int) $escuelaNivelId);
        }

        return $resumen;
    }

    /** @return array<string, int> en el orden de CONCEPTOS */
    public function conteos(int $escuelaNivelId): array
    {
        $contar = fn (string $tabla): int => DB::table($tabla)->where('escuela_nivel_id', $escuelaNivelId)->count();

        $fila = (array) DB::table('escuela_niveles')->where('id', $escuelaNivelId)->first(self::COLUMNAS_NIVEL);

        return [
            'datos' => array_filter($fila, fn ($valor) => $valor !== null) !== [] ? 1 : 0,
            'documentos' => $contar('documentos_escuela_nivel'),
            'recibos' => DB::table('recibos_pago_derechos')
                ->join('documentos_escuela_nivel', 'documentos_escuela_nivel.id', '=', 'recibos_pago_derechos.documento_escuela_nivel_id')
                ->where('documentos_escuela_nivel.escuela_nivel_id', $escuelaNivelId)
                ->count(),
            'espacios' => $contar('aulas_nivel'),
            'personal' => $contar('personal'),
            'matricula' => $contar('matricula_salas') + $contar('matricula_grados') + $contar('matricula_semestres') + $contar('matricula_cuatrimestres'),
            'mobiliario' => $contar('mobiliario_nivel'),
            'pasos' => $contar('escuela_nivel_pasos'),
            'responsables' => $contar('responsables_nivel'),
        ];
    }

    /** @param array<int, array<string, int>> $resumen */
    public static function conDatos(array $resumen): bool
    {
        foreach ($resumen as $conteos) {
            if (array_sum($conteos) > 0) {
                return true;
            }
        }

        return false;
    }
}
