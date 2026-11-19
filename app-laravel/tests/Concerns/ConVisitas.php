<?php

namespace Tests\Concerns;

use App\Models\EscuelaNivel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Fixture: visitas de inspección, respuestas y archivos en el disco `documentos` falso. Requiere ConTramitesEnRevision y GeneraPdfs. */
trait ConVisitas
{
    protected function visitaDe(EscuelaNivel $nivel, int $numero = 1, string $estado = 'programada', string $resultado = 'cumple', array $extra = []): int
    {
        $datos = ['escuela_nivel_id' => $nivel->id, 'numero' => $numero, 'programada_para' => now()->addDays(3),
            'inspector' => 'Inspector Uno', 'estado' => $estado, 'programada_por' => $this->supervisor()->id];
        if ($estado === 'realizada') {
            $datos += ['resultado' => $resultado, 'realizada_el' => now()->subDay()->toDateString(), 'registrada_por' => $datos['programada_por'],
                'subsanar_hasta' => $resultado === 'con_observaciones' ? now()->addDays(5)->toDateString() : null];
        }

        return DB::table('visitas_inspeccion')->insertGetId($extra + $datos);
    }

    /** Escribe un PDF en el disco falso y devuelve su ruta. */
    protected function archivoEnDisco(string $ruta = 'visitas/1/acta.pdf'): string
    {
        Storage::disk('documentos')->put($ruta, $this->pdfDe(1));

        return $ruta;
    }

    /** @return array{0: int, 1: int} id de la respuesta y de su archivo */
    protected function respuestaDe(int $visitaId, string $nombre, string $ruta, $creada = null): array
    {
        $respuesta = DB::table('respuestas_visita')->insertGetId(['visita_id' => $visitaId, 'nota' => 'Subsanado', 'creada_por' => $this->supervisor()->id,
            'created_at' => $creada ?? now()]);
        $archivo = DB::table('respuestas_visita_archivos')->insertGetId(['respuesta_id' => $respuesta, 'archivo_path' => $ruta, 'archivo_nombre' => $nombre]);

        return [$respuesta, $archivo];
    }
}
