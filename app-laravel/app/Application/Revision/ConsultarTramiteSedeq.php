<?php

namespace App\Application\Revision;

use App\Application\Documentos\DocumentosCapturados;
use App\Application\ResponsableLegal\TipoPersonaDeEscuela;
use App\Application\Tramite\ResumenTramite;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\PersonaFisica;
use App\Models\PersonaMoral;
use App\Models\Plantel;
use App\Models\ResponsableLegal;
use App\Models\TernaNombre;
use App\Models\TipoDocumento;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vista de solo lectura de un trámite para SEDEQ. No reutiliza ResumenTramite::paraEscuela($id, null):
 * ese contexto es del dueño y trae enlaces de edición. Revalida AccesoSedeq aquí (no basta el `can:view`
 * de la ruta) y solo muestra los niveles que el actor puede ver; los enlaces son las descargas existentes,
 * protegidas por `view`.
 */
final class ConsultarTramiteSedeq
{
    public function __construct(
        private readonly AccesoSedeq $acceso,
        private readonly DocumentosCapturados $documentosCapturados,
        private readonly TipoPersonaDeEscuela $tipoPersonaDeEscuela,
    ) {}

    /**
     * @return array{
     *     nombre: string|null,
     *     plantel: array<string, string|null>,
     *     responsable: array<string, string|null>|null,
     *     documentos: list<array{nombre: string, archivo: string, href: string}>,
     *     niveles: list<array{nombre: string, estado: string, folio: string|null, documentos: list<array{nombre: string, archivo: string, href: string}>}>
     * }
     *
     * @throws AuthorizationException
     */
    public function ejecutar(int $actorId, int $escuelaId): array
    {
        $nivelIds = $this->acceso->nivelesDeEscuela(User::findOrFail($actorId), $escuelaId);
        if ($nivelIds === []) {
            throw new AuthorizationException;
        }

        $escuela = Escuela::with('plantel')->findOrFail($escuelaId);
        /** @var Plantel $plantel */
        $plantel = $escuela->plantel;
        $tipoPersona = $this->tipoPersonaDeEscuela->ejecutar($escuelaId);
        $estados = DB::table('estados_expediente')->pluck('nombre', 'id');

        return [
            'nombre' => $escuela->nombre_aprobado ?? TernaNombre::where('escuela_id', $escuelaId)->orderBy('numero_propuesta')->value('nombre_propuesto'),
            'plantel' => [
                'Domicilio' => ResumenTramite::domicilio($plantel),
                'Localidad' => $plantel->localidad,
                'Teléfono' => $plantel->telefono,
                'Correo electrónico' => $plantel->correo_electronico,
            ],
            'responsable' => $this->responsable($escuelaId),
            'documentos' => $tipoPersona === null ? [] : $this->filas(
                $this->documentosCapturados->paraEscuela($escuelaId, $tipoPersona),
                fn (string $clave) => route('tramite.paso2-documentos.descargar', ['escuela' => $escuelaId, 'clave' => $clave]),
            ),
            'niveles' => EscuelaNivel::with('nivelEducativo')->whereKey($nivelIds)->orderBy('id')->get()
                ->map(fn (EscuelaNivel $nivel) => [
                    'nombre' => $nivel->nivelEducativo->nombre,
                    'estado' => (string) $estados[$nivel->estado_id],
                    'folio' => $nivel->folio_expediente,
                    'documentos' => $this->filas(
                        $this->documentosCapturados->paraEscuelaNivel($nivel->id),
                        fn (string $clave) => route('tramite.paso2-nivel-documentos.descargar', ['escuelaNivel' => $nivel->id, 'clave' => $clave]),
                    ),
                ])->values()->all(),
        ];
    }

    /** @return array<string, string|null>|null */
    private function responsable(int $escuelaId): ?array
    {
        $responsable = ResponsableLegal::where('escuela_id', $escuelaId)->first();
        if ($responsable === null) {
            return null;
        }

        $comun = ['Domicilio para notificaciones' => $responsable->domicilio_notificaciones];

        if ($responsable->tipo_persona !== 'moral') {
            return ['Tipo de persona' => 'Persona física', 'Nombre' => PersonaFisica::whereKey($responsable->id)->value('nombre')] + $comun;
        }

        $moral = PersonaMoral::find($responsable->id);

        return ['Tipo de persona' => 'Persona moral', 'Razón social' => $moral?->razon_social, 'Representante legal' => $moral?->nombre_representante_legal] + $comun;
    }

    /**
     * @param  Collection<string, array{archivoPath: string, nombreArchivo: string, subidoEn: Carbon}>  $capturados
     * @param  callable(string): string  $href
     * @return list<array{nombre: string, archivo: string, href: string}>
     */
    private function filas(Collection $capturados, callable $href): array
    {
        $nombres = TipoDocumento::whereIn('clave', $capturados->keys())->pluck('nombre', 'clave');

        return $capturados->map(fn (array $doc, string $clave) => [
            'nombre' => (string) ($nombres[$clave] ?? $clave),
            'archivo' => $doc['nombreArchivo'],
            'href' => $href($clave),
        ])->values()->all();
    }
}
