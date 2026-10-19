<?php

namespace App\Console\Commands;

use App\Application\Geografia\ImportarCatalogoGeografico;
use Illuminate\Console\Command;
use Throwable;

class ImportarGeografia extends Command
{
    protected $signature = 'geografia:importar';

    protected $description = 'Importa entidades federativas y municipios desde la API configurada (services.api_info_mexico.url) a las tablas locales';

    public function handle(ImportarCatalogoGeografico $importar): int
    {
        try {
            $conteo = $importar->ejecutar();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Catálogo geográfico importado: {$conteo['entidades']} entidades y {$conteo['municipios']} municipios.");

        return self::SUCCESS;
    }
}
