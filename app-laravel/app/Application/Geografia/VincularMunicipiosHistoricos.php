<?php

namespace App\Application\Geografia;

use App\Domain\Validaciones\Documental\NormalizadorDomicilio;
use App\Models\Municipio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Vincula al catálogo geográfico local (ADR-017) los municipios capturados como texto antes de que existieran las FK
 * (spec docs/specs/2026-10-20-paso2-integracion-end-to-end.md §8). Solo asigna coincidencias inequívocas: el nombre
 * normalizado (acentos, mayúsculas, espacios, puntuación) coincide con exactamente un municipio del ámbito.
 *
 * - Planteles: ámbito Querétaro (ADR-008: el plantel está en Querétaro).
 * - Certificados de número oficial: la entidad elegida si existe; si no, todo el catálogo (un nombre que existe en
 *   varias entidades es ambiguo, no se asume Querétaro).
 *
 * Nunca sobrescribe el texto original ni un id ya asignado, no borra nada y, en simulación (por omisión), no escribe.
 * Una abreviatura («Qro.», «SJR») no se adivina: queda reportada sin correspondencia para revisión humana.
 */
class VincularMunicipiosHistoricos
{
    public function __construct(private readonly NormalizadorDomicilio $normalizador = new NormalizadorDomicilio) {}

    /**
     * @return array{planteles: array<string, list<array{id: int, texto: string, municipio_id?: int}>>, certificados: array<string, list<array{id: int, texto: string, municipio_id?: int}>>}
     *
     * @throws RuntimeException si el catálogo de Querétaro no está importado
     */
    public function ejecutar(bool $aplicar = false): array
    {
        $queretaro = (new ListarCatalogoGeografico)->idDeQueretaro();
        if ($queretaro === null) {
            throw new RuntimeException('El catálogo geográfico no está importado: corre primero php artisan geografia:importar.');
        }

        $todos = Municipio::query()->get(['id', 'entidad_federativa_id', 'nombre']);
        // Índice por nombre normalizado, armado una vez: cada fila se resuelve sin volver a normalizar el catálogo.
        $porNombre = $todos->groupBy(fn (Municipio $m) => $this->normalizador->normalizar($m->nombre))->toBase();

        $planteles = $this->clasificar(
            DB::table('planteles')->whereNull('municipio_id')->orderBy('id')->get(['id', 'municipio']),
            $porNombre,
            fn (\stdClass $fila) => $queretaro,
        );

        $certificados = $this->clasificar(
            DB::table('certificados_numero_oficial')->whereNull('municipio_id')->orderBy('documento_plantel_id')
                ->get(['documento_plantel_id as id', 'municipio', 'entidad_federativa_id']),
            $porNombre,
            fn (\stdClass $fila) => $fila->entidad_federativa_id === null ? null : (int) $fila->entidad_federativa_id,
        );

        if ($aplicar) {
            DB::transaction(function () use ($planteles, $certificados, $todos) {
                foreach ($planteles['vinculables'] as $fila) {
                    DB::table('planteles')->where('id', $fila['id'])->whereNull('municipio_id')->update(['municipio_id' => $fila['municipio_id']]);
                }

                foreach ($certificados['vinculables'] as $fila) {
                    $entidad = (int) $todos->firstWhere('id', $fila['municipio_id'])->entidad_federativa_id;
                    DB::table('certificados_numero_oficial')->where('documento_plantel_id', $fila['id'])->whereNull('municipio_id')
                        ->update(['municipio_id' => $fila['municipio_id'], 'entidad_federativa_id' => DB::raw("COALESCE(entidad_federativa_id, {$entidad})")]);
                }
            });
        }

        return ['planteles' => $planteles, 'certificados' => $certificados];
    }

    /**
     * @param  Collection<int, \stdClass>  $filas
     * @param  Collection<array-key, \Illuminate\Database\Eloquent\Collection<int, Municipio>>  $porNombre  municipios por nombre normalizado
     * @param  callable(\stdClass): ?int  $ambito  entidad en la que se busca; null = todo el catálogo
     * @return array<string, list<array{id: int, texto: string, municipio_id?: int}>>
     */
    private function clasificar(Collection $filas, Collection $porNombre, callable $ambito): array
    {
        $resultado = ['vinculables' => [], 'ambiguos' => [], 'sin_correspondencia' => []];

        foreach ($filas as $fila) {
            $texto = (string) $fila->municipio;
            $buscado = $this->normalizador->normalizar($texto);
            $entidad = $ambito($fila);
            $candidatos = $buscado === '' ? collect() : collect($porNombre->get($buscado, []))
                ->filter(fn (Municipio $m) => $entidad === null || $m->entidad_federativa_id === $entidad)->values();

            match ($candidatos->count()) {
                0 => $resultado['sin_correspondencia'][] = ['id' => (int) $fila->id, 'texto' => $texto],
                1 => $resultado['vinculables'][] = ['id' => (int) $fila->id, 'texto' => $texto, 'municipio_id' => (int) $candidatos->first()->id],
                default => $resultado['ambiguos'][] = ['id' => (int) $fila->id, 'texto' => $texto],
            };
        }

        return $resultado;
    }
}
