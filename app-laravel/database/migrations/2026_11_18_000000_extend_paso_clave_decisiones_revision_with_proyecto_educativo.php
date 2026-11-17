<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Revisión del proyecto educativo (media superior) por el evaluador: sus 20 decisiones por sección viven en las mismas
 * tablas que las del revisor, con claves pe_01 a pe_20 (el orden del índice; caben en paso_clave VARCHAR(30)). Así la
 * devolución, el reenvío y las observaciones del solicitante leen un solo lugar. Solo se amplía el CHECK de paso_clave;
 * las filas existentes siguen siendo válidas. Plan 2026-11-17-proyecto-educativo-revision, T3.
 */
return new class extends Migration
{
    private const TABLAS = ['decisiones_revision', 'historial_decisiones_revision'];

    private const DEL_REVISOR = ['plantel', 'responsable', 'documentos', 'niveles', 'documentos_nivel', 'inmueble', 'infraestructura', 'mobiliario', 'plan_estudios', 'plantilla_docente', 'matricula'];

    /** @return list<string> */
    private function claves(bool $conProyecto): array
    {
        $pe = $conProyecto ? array_map(fn (int $n) => sprintf('pe_%02d', $n), range(1, 20)) : [];

        return [...self::DEL_REVISOR, ...$pe];
    }

    /** @param list<string> $claves */
    private function reemplazar(string $tabla, array $claves): void
    {
        $lista = implode(',', array_map(fn (string $c) => "'{$c}'", $claves));
        DB::statement("ALTER TABLE {$tabla} DROP CONSTRAINT IF EXISTS {$tabla}_paso_clave_check");
        DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$tabla}_paso_clave_check CHECK (paso_clave IN ({$lista}))");
    }

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            $this->reemplazar($tabla, $this->claves(true));
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            DB::statement("DELETE FROM {$tabla} WHERE paso_clave LIKE 'pe\\_%'");
            $this->reemplazar($tabla, $this->claves(false));
        }
    }
};
