<?php

namespace App\Application\Revision;

use App\Application\Documentos\DocumentosCapturados;
use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\MetadatosDocumentales;
use App\Application\ResponsableLegal\TipoPersonaDeEscuela;
use App\Application\Tramite\ResumenTramite;
use App\Application\Tramite\TramiteEditable;
use App\Application\Validaciones\DTO\FilaValidacion;
use App\Application\Validaciones\DTO\SeccionCapacidad;
use App\Application\Validaciones\DTO\SeccionNivel;
use App\Application\Validaciones\UltimaValidacionFinal;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\Gestor;
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
 *
 * Desde 2026-10-20 también trae los metadatos de cada documento (MetadatosDocumentales), los datos de cada nivel
 * (turno, alumnado, matrícula, personal, avance de Paso 3) y el resultado de validación guardado al envío, acotado a
 * los niveles visibles y rotulado como tal. Todo es lectura: no evalúa reglas ni escribe.
 */
final class ConsultarTramiteSedeq
{
    /** @var array<string, string> */
    private const TEXTOS = [
        'matutino' => 'Matutino', 'vespertino' => 'Vespertino', 'mixto' => 'Mixto', 'femenino' => 'Femenino', 'masculino' => 'Masculino',
        'escolarizada' => 'Escolarizada', 'no_escolarizada' => 'No escolarizada', 'mixta' => 'Mixta', 'virtual' => 'Virtual',
    ];

    public function __construct(
        private readonly AccesoSedeq $acceso,
        private readonly DocumentosCapturados $documentosCapturados,
        private readonly TipoPersonaDeEscuela $tipoPersonaDeEscuela,
        private readonly DocumentosCompletos $documentosCompletos,
        private readonly MetadatosDocumentales $metadatos,
        private readonly UltimaValidacionFinal $ultimaValidacion,
    ) {}

    /**
     * @return array{
     *     nombre: string|null,
     *     plantel: array<string, string|null>,
     *     responsable: array<string, string|null>|null,
     *     terna: list<string>,
     *     documentos: list<array{clave: string, nombre: string, archivo: string, href: string, metadatos: list<array{etiqueta: string, valor: string, estado: string|null}>}>,
     *     niveles: list<array{escuelaNivelId: int, nombre: string, estado: string, folio: string|null, datos: array<string, string|int|null>, pasos: list<array{nombre: string, estado: string}>, documentos: list<array{clave: string, nombre: string, archivo: string, href: string, metadatos: list<array{etiqueta: string, valor: string, estado: string|null}>}>}>,
     *     validacion: array{origen: string, generadaEn: \DateTimeInterface, listaParaEnvio: bool, documental: list<FilaValidacion>, niveles: list<SeccionNivel>, capacidad: list<SeccionCapacidad>, reporteHref: string|null}|null
     * }
     *
     * @throws AuthorizationException
     */
    public function ejecutar(int $actorId, int $escuelaId): array
    {
        $actor = User::findOrFail($actorId);
        $nivelIds = $this->acceso->nivelesDeEscuela($actor, $escuelaId);
        if ($nivelIds === []) {
            throw new AuthorizationException;
        }

        $escuela = Escuela::with('plantel')->findOrFail($escuelaId);
        /** @var Plantel $plantel */
        $plantel = $escuela->plantel;
        $tipoPersona = $this->tipoPersonaDeEscuela->ejecutar($escuelaId);
        $estados = DB::table('estados_expediente')->pluck('nombre', 'id');
        $metadatosGenerales = $tipoPersona === null ? [] : $this->metadatos->paraEscuela($escuelaId, $this->documentosCompletos->clavesAplicables($tipoPersona));

        return [
            'nombre' => $escuela->nombre_aprobado ?? TernaNombre::where('escuela_id', $escuelaId)->orderBy('numero_propuesta')->value('nombre_propuesto'),
            'plantel' => [
                'Domicilio' => ResumenTramite::domicilio($plantel),
                'Localidad' => $plantel->localidad,
                // Del catálogo si el plantel está vinculado (ADR-008); si no, el texto capturado.
                'Municipio' => $plantel->municipio_id === null ? $plantel->municipio : (string) DB::table('municipios')->where('id', $plantel->municipio_id)->value('nombre'),
                'Municipio vinculado al catálogo' => $plantel->municipio_id === null ? 'No' : 'Sí',
                'Teléfono' => $plantel->telefono,
                'Correo electrónico' => $plantel->correo_electronico,
            ],
            'responsable' => $this->responsable($escuelaId),
            'terna' => TernaNombre::where('escuela_id', $escuelaId)->orderBy('numero_propuesta')->pluck('nombre_propuesto')->map(fn ($n) => (string) $n)->values()->all(),
            'documentos' => $tipoPersona === null ? [] : $this->filas(
                $this->documentosCapturados->paraEscuela($escuelaId, $tipoPersona),
                $metadatosGenerales,
                fn (string $clave) => route('tramite.paso2-documentos.descargar', ['escuela' => $escuelaId, 'clave' => $clave]),
            ),
            'niveles' => EscuelaNivel::with('nivelEducativo')->whereKey($nivelIds)->orderBy('id')->get()
                ->map(fn (EscuelaNivel $nivel) => [
                    'escuelaNivelId' => $nivel->id,
                    'nombre' => $nivel->nivelEducativo->nombre,
                    'estado' => (string) $estados[$nivel->estado_id],
                    'folio' => $nivel->folio_expediente,
                    'datos' => $this->datosNivel($nivel),
                    'pasos' => $this->pasos($nivel->id),
                    'documentos' => $this->filas(
                        $this->documentosCapturados->paraEscuelaNivel($nivel->id),
                        $this->metadatos->paraEscuelaNivel($nivel->id),
                        fn (string $clave) => route('tramite.paso2-nivel-documentos.descargar', ['escuelaNivel' => $nivel->id, 'clave' => $clave]),
                    ),
                ])->values()->all(),
            'validacion' => $this->validacionEnviada($actor, $escuelaId, $nivelIds),
        ];
    }

    /**
     * El resultado guardado con el envío (UltimaValidacionFinal, solo lectura), acotado a los niveles visibles. No se
     * vuelve a evaluar aquí: una evaluación calculada después no es la que se envió, y un GET no escribe.
     *
     * @param  list<int>  $nivelIds
     * @return array{origen: string, generadaEn: \DateTimeInterface, listaParaEnvio: bool, documental: list<FilaValidacion>, niveles: list<SeccionNivel>, capacidad: list<SeccionCapacidad>, reporteHref: string|null}|null
     */
    private function validacionEnviada(User $actor, int $escuelaId, array $nivelIds): ?array
    {
        if (! TramiteEditable::enviado(EscuelaNivel::where('escuela_id', $escuelaId)->pluck('estado_id'), TramiteEditable::idEnCaptura())) {
            return null;
        }

        $validacion = $this->ultimaValidacion->paraEscuela($escuelaId);
        if ($validacion === null) {
            return null;
        }

        return [
            'origen' => 'almacenada_al_envio',
            'generadaEn' => $validacion->generadaEn,
            'listaParaEnvio' => $validacion->listaParaEnvio,
            'documental' => $validacion->filas,
            'niveles' => array_values(array_filter($validacion->niveles, fn (SeccionNivel $s) => in_array($s->escuelaNivelId, $nivelIds, true))),
            'capacidad' => array_values(array_filter($validacion->capacidad, fn (SeccionCapacidad $s) => in_array($s->escuelaNivelId, $nivelIds, true))),
            'reporteHref' => $this->acceso->puedeVerReporteEnviado($actor, $escuelaId) ? route('sedeq.tramite.reporte', ['escuela' => $escuelaId]) : null,
        ];
    }

    /** @return array<string, string|int|null> */
    private function datosNivel(EscuelaNivel $nivel): array
    {
        $alumnos = (int) DB::table('matricula_grados')->where('escuela_nivel_id', $nivel->id)->sum('cantidad_alumnos')
            + (int) DB::table('matricula_salas')->where('escuela_nivel_id', $nivel->id)->sum('cantidad_alumnos');
        $conMatricula = DB::table('matricula_grados')->where('escuela_nivel_id', $nivel->id)->exists()
            || DB::table('matricula_salas')->where('escuela_nivel_id', $nivel->id)->exists();

        return [
            'Turno' => self::TEXTOS[$nivel->turno] ?? $nivel->turno,
            'Tipo de alumnado' => self::TEXTOS[$nivel->tipo_alumnado] ?? $nivel->tipo_alumnado,
            'Modalidad' => self::TEXTOS[$nivel->modalidad] ?? $nivel->modalidad,
            'Alumnos inscritos' => $conMatricula ? $alumnos : null,
            'Personal registrado' => (int) DB::table('personal')->where('escuela_nivel_id', $nivel->id)->count(),
        ];
    }

    /** @return list<array{nombre: string, estado: string}> sub-pasos de Paso 3 con su estado (pendiente si no hay fila) */
    private function pasos(int $escuelaNivelId): array
    {
        $estados = DB::table('escuela_nivel_pasos')->where('escuela_nivel_id', $escuelaNivelId)->pluck('estado', 'paso_captura_id');

        return DB::table('pasos_captura')->orderBy('orden')->get(['id', 'nombre'])
            ->map(fn (object $paso) => ['nombre' => (string) $paso->nombre, 'estado' => (string) ($estados[$paso->id] ?? 'pendiente')])
            ->values()->all();
    }

    /** @return array<string, string|null>|null */
    private function responsable(int $escuelaId): ?array
    {
        $responsable = ResponsableLegal::where('escuela_id', $escuelaId)->first();
        if ($responsable === null) {
            return null;
        }

        $comun = [
            'Domicilio para notificaciones' => $responsable->domicilio_notificaciones,
            'Persona autorizada para recoger notificaciones' => $responsable->persona_autorizada_recoger,
        ];

        if ($responsable->tipo_persona !== 'moral') {
            $fisica = PersonaFisica::find($responsable->id);
            $datos = [
                'Tipo de persona' => 'Persona física',
                'Nombre' => $fisica?->nombre,
                'RFC' => $fisica?->rfc,
                'CURP' => $fisica?->curp,
            ];

            if ($responsable->tipo_persona === 'fisica_con_gestor') {
                $gestor = Gestor::find($responsable->id);
                $datos += [
                    'Gestor' => $gestor?->nombre,
                    'CURP del gestor' => $gestor?->curp,
                    'Poder del gestor' => $gestor?->numero_poder,
                ];
            }

            return $datos + $comun;
        }

        $moral = PersonaMoral::find($responsable->id);

        return [
            'Tipo de persona' => 'Persona moral',
            'Razón social' => $moral?->razon_social,
            'Representante legal' => $moral?->nombre_representante_legal,
            'Escritura constitutiva' => $moral?->numero_escritura_constitutiva,
            'Folio del Registro Público' => $moral?->folio_registro_publico,
        ] + $comun;
    }

    /**
     * @param  Collection<string, array{archivoPath: string, nombreArchivo: string, subidoEn: Carbon}>  $capturados
     * @param  array<string, list<array{etiqueta: string, valor: string, estado: string|null}>>  $metadatos
     * @param  callable(string): string  $href
     * @return list<array{clave: string, nombre: string, archivo: string, href: string, metadatos: list<array{etiqueta: string, valor: string, estado: string|null}>}>
     */
    private function filas(Collection $capturados, array $metadatos, callable $href): array
    {
        $nombres = TipoDocumento::whereIn('clave', $capturados->keys())->pluck('nombre', 'clave');

        return $capturados->map(fn (array $doc, string $clave) => [
            'clave' => $clave,
            'nombre' => (string) ($nombres[$clave] ?? $clave),
            'archivo' => $doc['nombreArchivo'],
            'href' => $href($clave),
            'metadatos' => $metadatos[$clave] ?? [],
        ])->values()->all();
    }
}
