<?php

namespace Tests\Concerns;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\AsignaturasSeeder;
use Database\Seeders\CargosPuestosSeeder;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\GradosSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Fixture: an escuela_nivel with Paso 2 and its Paso 2.4 complete, and every Paso 3 sub-step
 * before $hasta already completed, so the sub-step under test is reachable.
 */
trait PreparaPaso3
{
    use CompletaPaso2;
    use CompletaPaso24;

    protected function nivelListoPara(string $nivelClave, string $hasta): EscuelaNivel
    {
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();
        (new GradosSeeder)->run();
        (new CargosPuestosSeeder)->run();
        (new AsignaturasSeeder)->run();

        $plantel = Plantel::create(['calle' => 'Calle 1', 'numero_ext' => '10', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
        $this->completarPaso2($escuela->id);

        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', $nivelClave)->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);

        // WS-5b: Paso 3 stays locked until the level's Paso 2.4 is complete.
        $this->completarPaso24($escuelaNivel->id);

        foreach (DB::table('pasos_captura')->orderBy('orden')->pluck('clave') as $clave) {
            if ($clave === $hasta) {
                break;
            }
            (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, (string) $clave);
        }

        return $escuelaNivel;
    }

    protected function cargoId(string $nivelClave, string $nombre): int
    {
        return (int) DB::table('cargos_puestos')
            ->join('niveles_educativos', 'niveles_educativos.id', '=', 'cargos_puestos.nivel_educativo_id')
            ->where('niveles_educativos.clave', $nivelClave)
            ->where('cargos_puestos.nombre', $nombre)
            ->value('cargos_puestos.id');
    }

    protected function pasoCompletado(EscuelaNivel $escuelaNivel, string $clave): bool
    {
        return DB::table('escuela_nivel_pasos')
            ->join('pasos_captura', 'pasos_captura.id', '=', 'escuela_nivel_pasos.paso_captura_id')
            ->where('escuela_nivel_id', $escuelaNivel->id)
            ->where('pasos_captura.clave', $clave)
            ->where('estado', 'completado')
            ->exists();
    }
}
