<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de mobiliario y equipo obligatorio de Educación Inicial, por sala
 * (COMPENDIO, "Tercera dimensión del motor de validación", tabla de ratios
 * verificada palabra por palabra contra req_inicial.docx líneas 133-190).
 *
 * Inicial es el único nivel con catálogo confirmado; los demás niveles están
 * deliberadamente fuera del alcance de este MVP (decisión 2026-09-11), no es
 * un vacío accidental.
 *
 * Los conceptos de la Sala de Usos Múltiples llevan sala_id NULL — el DDL
 * documenta ese NULL como "el concepto no depende de una sala específica", y
 * Usos Múltiples no es una sala por grupo de edad como las otras cinco.
 */
class MobiliarioConceptosSeeder extends Seeder
{
    private const FUENTE = 'COMPENDIO §Motor de Validación, ratios por sala (req_inicial.docx líneas 133-190)';

    private const FUENTE_NO_CUANTIFICADA = 'COMPENDIO §Motor de Validación (req_inicial.docx líneas 133-190): "suficiente para los menores de la sala" — cantidad no cuantificada en la norma; se captura como concepto contable de 1 por sala.';

    public function run(): void
    {
        // ponytail: mobiliario_conceptos no tiene índice único, así que insertOrIgnore
        // no puede deduplicar. Una tabla no vacía significa que ya se sembró.
        if (DB::table('mobiliario_conceptos')->exists()) {
            return;
        }

        $idsSala = DB::table('salas')->pluck('id', 'clave');

        $filas = [];

        $agregar = function (?string $claveSala, string $nombre, string $tipoRatio, float $valorRatio, ?string $fuente = null) use (&$filas, $idsSala): void {
            $filas[] = [
                'sala_id' => $claveSala === null ? null : $idsSala[$claveSala],
                'nombre' => $nombre,
                'tipo_ratio' => $tipoRatio,
                'valor_ratio' => $valorRatio,
                'fuente' => $fuente ?? self::FUENTE,
            ];
        };

        // --- Lactantes A (6) ---
        $agregar('lactantes_a', 'Cuna con barandal', 'por_alumno_ratio', 2);
        $agregar('lactantes_a', 'Colchoneta con forro de vinil (gateo)', 'por_alumno_ratio', 1);
        $agregar('lactantes_a', 'Mueble para cambio de pañal con colchoneta', 'fijo_por_sala', 1);
        $agregar('lactantes_a', 'Silla para adulto con antebrazo (espacio de lactancia materna)', 'fijo_por_sala', 1);
        $agregar('lactantes_a', 'Silla porta bebé', 'por_alumno_ratio', 1);
        $agregar('lactantes_a', 'Baño de artesa (incluye regadera de teléfono)', 'fijo_por_sala', 1);

        // --- Lactantes B (9) ---
        $agregar('lactantes_b', 'Cuna con barandal', 'por_alumno_ratio', 2);
        $agregar('lactantes_b', 'Colchoneta con forro de vinil (gateo)', 'por_alumno_ratio', 1);
        $agregar('lactantes_b', 'Mueble para cambio de pañal con colchoneta', 'fijo_por_sala', 1);
        $agregar('lactantes_b', 'Espejo infantil (60x100 cm, puntas redondeadas)', 'fijo_por_sala', 1);
        $agregar('lactantes_b', 'Barra de apoyo', 'fijo_por_sala', 1);
        $agregar('lactantes_b', 'Silla porta bebé', 'por_alumno_ratio', 1);
        $agregar('lactantes_b', 'Baño de artesa (incluye regadera de teléfono)', 'fijo_por_sala', 1);
        $agregar('lactantes_b', 'Repisa/mueble para material didáctico', 'fijo_por_sala', 1);
        $agregar('lactantes_b', 'Material didáctico adecuado a la edad', 'fijo_por_sala', 1, self::FUENTE_NO_CUANTIFICADA);

        // --- Lactantes C (7) ---
        $agregar('lactantes_c', 'Colchoneta con forro de vinil (gateo)', 'por_alumno_ratio', 1);
        $agregar('lactantes_c', 'Mueble para cambio de pañal con colchoneta', 'fijo_por_sala', 1);
        $agregar('lactantes_c', 'Espejo infantil (60x100 cm)', 'fijo_por_sala', 1);
        $agregar('lactantes_c', 'Barra de apoyo', 'fijo_por_sala', 1);
        $agregar('lactantes_c', 'Baño de artesa (incluye regadera de teléfono)', 'fijo_por_sala', 1);
        $agregar('lactantes_c', 'Repisa/mueble para material didáctico', 'fijo_por_sala', 1);
        $agregar('lactantes_c', 'Material didáctico adecuado a la edad', 'fijo_por_sala', 1, self::FUENTE_NO_CUANTIFICADA);

        // --- Maternal A y Maternal B (8 cada una) ---
        // COMPENDIO agrupa las 5 piezas "1 por sala" en una sola celda; se siembran
        // como conceptos contables separados porque se cuentan por separado.
        foreach (['maternal_a', 'maternal_b'] as $claveSala) {
            $agregar($claveSala, 'Colchoneta con forro de vinil', 'por_alumno_ratio', 1);
            $agregar($claveSala, 'Silla infantil', 'por_alumno_ratio', 1);
            $agregar($claveSala, 'Mesa infantil', 'por_alumno_ratio', 6);
            $agregar($claveSala, 'Mueble para cambio de pañal con colchoneta', 'fijo_por_sala', 1);
            $agregar($claveSala, 'Espejo infantil', 'fijo_por_sala', 1);
            $agregar($claveSala, 'Repisa para material didáctico', 'fijo_por_sala', 1);
            $agregar($claveSala, 'Mueble guarda-mochilas', 'fijo_por_sala', 1);
            $agregar($claveSala, 'Material didáctico adecuado a la edad', 'fijo_por_sala', 1, self::FUENTE_NO_CUANTIFICADA);
        }

        // --- Sala de Usos Múltiples (3), sin sala de grupo de edad ---
        $agregar(null, 'Silla infantil con cinturón (lactantes)', 'por_alumno_ratio', 1);
        $agregar(null, 'Silla infantil (maternal)', 'por_alumno_ratio', 1);
        $agregar(null, 'Mesa infantil', 'por_alumno_ratio', 6);

        DB::table('mobiliario_conceptos')->insert($filas);
    }
}
