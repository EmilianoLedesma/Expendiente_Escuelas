<?php

namespace App\Application\ProyectoEducativo;

use Illuminate\Support\Str;

/**
 * Formato APA (7.ª edición) de una referencia de la bibliografía: "Autores (Año). Título. Fuente. URL", con el título aparte
 * para que la vista lo ponga en cursiva sin armar HTML con texto del usuario. Cada parte termina en punto salvo que ya
 * termine en punto, signo de interrogación o de exclamación. También da el orden alfabético por autor que pide APA.
 */
final class FormatoReferenciaApa
{
    /**
     * @param  array{autores: string, anio: string, titulo: string, fuente: string, url: string}  $r
     * @return array{string, string, string} lo de antes del título, el título (para la cursiva) y lo de después
     */
    public static function partes(array $r): array
    {
        $url = trim($r['url']);

        return [
            trim($r['autores']).' ('.trim($r['anio']).'). ',
            self::cerrar($r['titulo']),
            ' '.self::cerrar($r['fuente']).($url === '' ? '' : ' '.$url),
        ];
    }

    /** @param  array{autores: string, anio: string, titulo: string, fuente: string, url: string}  $r */
    public static function texto(array $r): string
    {
        return implode('', self::partes($r));
    }

    /**
     * Orden alfabético por autor (sin importar mayúsculas ni acentos); con el mismo autor, por año y luego por título.
     *
     * @param  list<array{autores: string, anio: string, titulo: string, fuente: string, url: string}>  $lista
     * @return list<array{autores: string, anio: string, titulo: string, fuente: string, url: string}>
     */
    public static function ordenar(array $lista): array
    {
        $clave = fn (string $texto): string => Str::ascii(mb_strtolower($texto));
        usort($lista, fn (array $a, array $b): int => [$clave($a['autores']), (int) $a['anio'], $clave($a['titulo'])] <=> [$clave($b['autores']), (int) $b['anio'], $clave($b['titulo'])]);

        return $lista;
    }

    private static function cerrar(string $texto): string
    {
        $texto = trim($texto);

        return preg_match('/[.?!]$/u', $texto) === 1 ? $texto : $texto.'.';
    }
}
