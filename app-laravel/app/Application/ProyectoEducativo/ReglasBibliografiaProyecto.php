<?php

namespace App\Application\ProyectoEducativo;

/**
 * Única lista de reglas de la sección 20 (Bibliografía del Proyecto): de 1 a 50 referencias; cada una con autores (hasta 300
 * caracteres), año de cuatro dígitos (de 1000 al año que viene), título (hasta 300), fuente (editorial, revista o sitio,
 * hasta 200) y una URL o DOI opcional que, si se escribe, empieza con http:// o https:// (hasta 500). Los límites de
 * cantidad y de longitud no los dio el dueño: son una propuesta aprobada. Los errores llevan la clave
 * "referencias.{índice}.{campo}", igual que los nombres de los campos del formulario.
 */
final class ReglasBibliografiaProyecto
{
    public const MIN = 1;

    public const MAX = 50;

    public const AUTORES_MAX = 300;

    public const TITULO_MAX = 300;

    public const FUENTE_MAX = 200;

    public const URL_MAX = 500;

    public const ANIO_MIN = 1000;

    public const CAMPOS = ['autores', 'anio', 'titulo', 'fuente', 'url'];

    /** @return array{autores: string, anio: string, titulo: string, fuente: string, url: string} */
    public static function referenciaVacia(): array
    {
        return ['autores' => '', 'anio' => '', 'titulo' => '', 'fuente' => '', 'url' => ''];
    }

    /**
     * Lo que llega del navegador puede ser cualquier cosa: se vuelve una lista de referencias con todos sus campos como texto
     * recortado (lo que no es texto o número cuenta como vacío; lo que no es una referencia, como una referencia en blanco).
     * Se corta en MAX + 1 para que el error de "demasiadas" salte sin trabajar de más.
     *
     * @return list<array{autores: string, anio: string, titulo: string, fuente: string, url: string}>
     */
    public static function normalizar(mixed $referencias): array
    {
        if (! is_array($referencias)) {
            return [];
        }

        $lista = [];
        foreach (array_slice(array_values($referencias), 0, self::MAX + 1) as $referencia) {
            $fila = self::referenciaVacia();
            if (is_array($referencia)) {
                foreach (self::CAMPOS as $campo) {
                    $valor = $referencia[$campo] ?? '';
                    $fila[$campo] = is_scalar($valor) ? trim((string) $valor) : '';
                }
            }
            $lista[] = $fila;
        }

        return $lista;
    }

    /**
     * @param  list<array{autores: string, anio: string, titulo: string, fuente: string, url: string}>  $lista  ya normalizada
     * @return array<string, string> clave => primer mensaje
     */
    public static function errores(array $lista): array
    {
        $cuantas = count($lista);
        if ($cuantas < self::MIN) {
            return ['referencias' => 'Agrega al menos una referencia.'];
        }
        if ($cuantas > self::MAX) {
            return ['referencias' => 'No puede haber más de '.self::MAX.' referencias.'];
        }

        $anioMax = (int) date('Y') + 1;
        $errores = [];
        foreach ($lista as $i => $r) {
            $p = "referencias.{$i}.";

            foreach (['autores' => self::AUTORES_MAX, 'titulo' => self::TITULO_MAX, 'fuente' => self::FUENTE_MAX] as $campo => $max) {
                if ($r[$campo] === '') {
                    $errores[$p.$campo] = 'Este campo es obligatorio.';
                } elseif (mb_strlen($r[$campo]) > $max) {
                    $errores[$p.$campo] = "No debe pasar de {$max} caracteres.";
                }
            }

            if (preg_match('/^\d{4}$/', $r['anio']) !== 1 || (int) $r['anio'] < self::ANIO_MIN || (int) $r['anio'] > $anioMax) {
                $errores[$p.'anio'] = 'Escribe un año de cuatro dígitos (de '.self::ANIO_MIN." a {$anioMax}).";
            }

            if ($r['url'] !== '') {
                if (mb_strlen($r['url']) > self::URL_MAX) {
                    $errores[$p.'url'] = 'No debe pasar de '.self::URL_MAX.' caracteres.';
                } elseif (preg_match('#^https?://\S+$#i', $r['url']) !== 1) {
                    $errores[$p.'url'] = 'Escribe un enlace completo que empiece con http:// o https://.';
                }
            }
        }

        return $errores;
    }
}
