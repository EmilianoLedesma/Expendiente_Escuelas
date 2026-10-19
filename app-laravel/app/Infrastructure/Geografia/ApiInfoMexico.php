<?php

namespace App\Infrastructure\Geografia;

use App\Application\Geografia\FuenteCatalogoGeografico;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente de api_info_mexico (https://github.com/angellof18/api_info_mexico).
 * GET /api/estados y GET /api/estados/{EFE_KEY}/municipios. Cualquier fallo o
 * respuesta con otra forma es un RuntimeException: la importación no escribe nada.
 */
final class ApiInfoMexico implements FuenteCatalogoGeografico
{
    public function __construct(private readonly string $urlBase) {}

    public function entidades(): array
    {
        return array_map(
            fn (array $fila): array => [
                'clave' => (int) $this->campo($fila, 'EFE_KEY'),
                'nombre' => (string) $this->campo($fila, 'ENTIDAD_FEDERATIVA'),
                'abreviatura' => isset($fila['ABREVIATURA']) ? (string) $fila['ABREVIATURA'] : null,
            ],
            $this->lista('/api/estados'),
        );
    }

    public function municipios(int $claveEntidad): array
    {
        return array_map(
            fn (array $fila): array => [
                'clave' => (int) $this->campo($fila, 'MUN_KEY'),
                'nombre' => (string) $this->campo($fila, 'MUNICIPIO'),
            ],
            $this->lista("/api/estados/{$claveEntidad}/municipios"),
        );
    }

    /** @return list<array<string, mixed>> */
    private function lista(string $ruta): array
    {
        $url = rtrim($this->urlBase, '/').$ruta;

        try {
            $respuesta = Http::timeout(15)->retry(2, 500, throw: false)->acceptJson()->get($url);
        } catch (ConnectionException $e) {
            throw new RuntimeException("No se pudo conectar con la API geográfica ({$url}): {$e->getMessage()}", 0, $e);
        }

        if (! $respuesta->successful()) {
            throw new RuntimeException("La API geográfica respondió {$respuesta->status()} en {$url}.");
        }

        $datos = $respuesta->json();
        if (! is_array($datos) || ! array_is_list($datos) || array_filter($datos, fn ($fila) => ! is_array($fila)) !== []) {
            throw new RuntimeException("La API geográfica devolvió una respuesta inesperada en {$url}.");
        }

        return $datos;
    }

    /** @param  array<string, mixed>  $fila */
    private function campo(array $fila, string $nombre): mixed
    {
        if (! array_key_exists($nombre, $fila) || $fila[$nombre] === null || $fila[$nombre] === '') {
            throw new RuntimeException("La API geográfica devolvió un registro sin {$nombre}.");
        }

        return $fila[$nombre];
    }
}
