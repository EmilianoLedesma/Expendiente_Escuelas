<?php

namespace App\Console\Commands;

use App\Application\Geografia\VincularMunicipiosHistoricos;
use Illuminate\Console\Command;
use Throwable;

class VincularMunicipios extends Command
{
    protected $signature = 'geografia:vincular-municipios {--aplicar : Escribe los vínculos inequívocos; sin esta opción solo simula}';

    protected $description = 'Vincula al catálogo geográfico los municipios de planteles y certificados capturados como texto (solo coincidencias inequívocas)';

    public function handle(VincularMunicipiosHistoricos $vincular): int
    {
        $aplicar = (bool) $this->option('aplicar');

        try {
            $reporte = $vincular->ejecutar($aplicar);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($aplicar ? 'Vínculos aplicados.' : 'Simulación: no se escribió nada (usa --aplicar para escribir).');

        foreach ($reporte as $tabla => $grupos) {
            $this->line(sprintf('%s: %d vinculables, %d ambiguos, %d sin correspondencia.', $tabla, count($grupos['vinculables']), count($grupos['ambiguos']), count($grupos['sin_correspondencia'])));
            foreach (['ambiguos', 'sin_correspondencia'] as $grupo) {
                foreach ($grupos[$grupo] as $fila) {
                    $this->line("  [{$grupo}] {$tabla} #{$fila['id']}: «{$fila['texto']}»");
                }
            }
        }

        return self::SUCCESS;
    }
}
