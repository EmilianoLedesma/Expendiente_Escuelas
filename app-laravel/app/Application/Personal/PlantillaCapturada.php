<?php

namespace App\Application\Personal;

use App\Models\Asignatura;
use App\Models\CargoPuesto;
use App\Models\EscuelaNivel;
use App\Models\Personal;
use App\Models\Sala;
use Illuminate\Support\Facades\DB;

/** Read side of Paso 3 Plantilla docente: prefill rows and the level's catalogs (ADR-006: flow reads live in Application). */
class PlantillaCapturada
{
    /** @return list<array{cargoPuestoId: string, nombre: string, nacionalidad: string, sexo: string, estudios: string, cedulaODocumento: string, salaId: string, asignaturaId: string}> */
    public function personas(int $escuelaNivelId): array
    {
        $salas = DB::table('personal_salas')->pluck('sala_id', 'personal_id');
        $asignaturas = DB::table('personal_asignaturas')->pluck('asignatura_id', 'personal_id');

        return Personal::where('escuela_nivel_id', $escuelaNivelId)->orderBy('id')->get()
            ->map(fn (Personal $p) => [
                'cargoPuestoId' => (string) $p->cargo_puesto_id,
                'nombre' => (string) $p->nombre,
                'nacionalidad' => (string) $p->nacionalidad,
                'sexo' => (string) $p->sexo,
                'estudios' => (string) $p->estudios,
                'cedulaODocumento' => (string) $p->cedula_o_documento,
                'salaId' => (string) ($salas[$p->id] ?? ''),
                'asignaturaId' => (string) ($asignaturas[$p->id] ?? ''),
            ])->values()->all();
    }

    /** @return array<int, array{nombre: string, requiereSala: bool, requiereAsignatura: bool}> */
    public function cargos(int $escuelaNivelId): array
    {
        $nivelId = EscuelaNivel::whereKey($escuelaNivelId)->value('nivel_educativo_id');

        return CargoPuesto::where('nivel_educativo_id', $nivelId)->orderBy('id')->get()
            ->mapWithKeys(fn (CargoPuesto $c) => [$c->id => ['nombre' => $c->nombre, 'requiereSala' => $c->requiere_sala, 'requiereAsignatura' => $c->requiere_asignatura]])
            ->all();
    }

    /** @return array<int, string> */
    public function salas(): array
    {
        return Sala::orderBy('orden')->pluck('nombre', 'id')->all();
    }

    /** @return array<int, string> */
    public function asignaturas(): array
    {
        return Asignatura::orderBy('nombre')->pluck('nombre', 'id')->all();
    }
}
