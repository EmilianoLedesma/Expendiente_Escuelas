<?php

namespace App\Application\Geografia;

use App\Models\EntidadFederativa;
use App\Models\Municipio;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Llena entidades_federativas y municipios desde la fuente. Primero trae y revisa
 * todo; solo si el catálogo es completo escribe, en una transacción, con upsert
 * (se puede repetir sin duplicar). No borra lo que la fuente deje de listar.
 */
class ImportarCatalogoGeografico
{
    /** Claves del origen que no son entidades federativas: NO ESPECIFICADO, NO APLICA, SE IGNORA. */
    private const CLAVES_NO_GEOGRAFICAS = [0, 88, 99];

    private readonly int $minimoEntidades;

    public function __construct(private readonly FuenteCatalogoGeografico $fuente, ?int $minimoEntidades = null)
    {
        $this->minimoEntidades = $minimoEntidades ?? (int) config('services.api_info_mexico.minimo_entidades', 32);
    }

    /**
     * @return array{entidades: int, municipios: int}
     *
     * @throws RuntimeException si la fuente falla o el catálogo viene incompleto (no se escribe nada)
     */
    public function ejecutar(): array
    {
        $entidades = array_values(array_filter(
            $this->fuente->entidades(),
            fn (array $entidad): bool => ! in_array($entidad['clave'], self::CLAVES_NO_GEOGRAFICAS, true),
        ));

        if (count($entidades) < $this->minimoEntidades) {
            throw new RuntimeException(sprintf('El catálogo trae solo %d entidades federativas (se esperaban al menos %d): no se importó nada.', count($entidades), $this->minimoEntidades));
        }

        $municipiosPorEntidad = [];
        foreach ($entidades as $entidad) {
            $municipios = $this->fuente->municipios($entidad['clave']);
            if ($municipios === []) {
                throw new RuntimeException("La entidad {$entidad['nombre']} no trae municipios: no se importó nada.");
            }
            $municipiosPorEntidad[$entidad['clave']] = $municipios;
        }

        DB::transaction(function () use ($entidades, $municipiosPorEntidad): void {
            foreach ($entidades as $entidad) {
                $modelo = EntidadFederativa::updateOrCreate(
                    ['clave' => $entidad['clave']],
                    ['nombre' => $entidad['nombre'], 'abreviatura' => $entidad['abreviatura']],
                );

                foreach ($municipiosPorEntidad[$entidad['clave']] as $municipio) {
                    Municipio::updateOrCreate(
                        ['entidad_federativa_id' => $modelo->id, 'clave' => $municipio['clave']],
                        ['nombre' => $municipio['nombre']],
                    );
                }
            }
        });

        return [
            'entidades' => count($entidades),
            'municipios' => array_sum(array_map('count', $municipiosPorEntidad)),
        ];
    }
}
