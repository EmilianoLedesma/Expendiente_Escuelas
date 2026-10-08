<?php

namespace Tests\Concerns;

use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\ResponsableNivel;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\DB;

/** Fixture: niveles de un solicitante y responsables asignados a ellos. */
trait ConNivelParaResponsables
{
    protected function nivelDe(Solicitante $solicitante, string $nivelClave = 'primaria', string $estado = 'en_captura'): EscuelaNivel
    {
        (new CatalogoMinimoSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Calle '.uniqid(), 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);

        return $this->nivelEn($escuela, $nivelClave, $estado);
    }

    protected function nivelEn(Escuela $escuela, string $nivelClave, string $estado = 'en_captura'): EscuelaNivel
    {
        return EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', $nivelClave)->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', $estado)->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
    }

    protected function responsableDe(EscuelaNivel ...$niveles): User
    {
        (new RolesSeeder)->run();
        $usuario = User::factory()->create();
        $usuario->assignRole('responsable_nivel');

        foreach ($niveles as $nivel) {
            ResponsableNivel::create([
                'user_id' => $usuario->id,
                'escuela_nivel_id' => $nivel->id,
                'invitado_por_solicitante_id' => $nivel->escuela->solicitante_id,
            ]);
        }

        return $usuario;
    }
}
