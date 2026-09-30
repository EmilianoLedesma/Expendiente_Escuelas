<?php

namespace App\Application\Tramite;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\ResponsableLegal\TipoPersonaDeEscuela;
use App\Application\Tramite\DTO\NivelDelTramite;
use App\Application\Tramite\DTO\ResumenTramiteDTO;
use App\Application\Tramite\DTO\SeccionTramite;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\TernaNombre;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Estado de todo el trámite para el hub ("Resumen del trámite"), "Mis trámites"
 * y el "Paso X de N" de cada página. No decide nada nuevo: compone EstadoPaso2
 * (Paso 2 completo y vigente), EstadoPaso24 (Paso 2.4 de cada nivel), EstadoPaso3 (orden de sub-pasos),
 * DocumentosCompletos (cuántos faltan) y escuela_nivel_pasos. Reemplazó al
 * componente de vista Progreso (retirado en el rediseño UI): la lectura de
 * flujo pasa por Application (ADR-001).
 */
class ResumenTramite
{
    /** clave de pasos_captura => ruta, solo sub-pasos con página. CompuertaPaso3 lo reutiliza: un solo mapa. */
    public const RUTAS_PASO3 = [
        'inmueble' => 'tramite.paso3-inmueble',
        'infraestructura' => 'tramite.paso3-infraestructura',
        'mobiliario' => 'tramite.paso3-mobiliario',
    ];

    /** Paso 2.4 de cada nivel (WS-5b); CompuertaPaso3 y Paso2Responsable redirigen aquí. */
    public const RUTA_DOCUMENTOS_NIVEL = 'tramite.paso2-nivel-documentos';

    /** Mobiliario solo aplica a este nivel; MobiliarioNivel::mount() auto-completa los demás. */
    public const NIVEL_CON_MOBILIARIO = 'inicial';

    /**
     * Secciones completadas cuya página se puede volver a abrir hoy. Responsable,
     * Documentos, Niveles e Inmueble redirigen hacia adelante al estar completas;
     * revisarlas llega con WS-7 (docs/decisions/PENDIENTE-edicion-hasta-envio.md).
     * Documentos del nivel no redirige: el Formato firmado se puede reemplazar.
     */
    private const REVISABLES = ['documentos_nivel', 'infraestructura', 'mobiliario'];

    /** @var array<string, array{string, string}> */
    private const GENERALES = [
        'plantel' => ['Datos del plantel', 'Domicilio y datos de contacto del plantel.'],
        'responsable' => ['Responsable legal', 'Persona física o moral que solicita la incorporación y terna de nombres.'],
        'documentos' => ['Documentos', 'Identificación y documentos del inmueble, en PDF.'],
        'niveles' => ['Niveles educativos', 'Niveles que se solicita incorporar.'],
    ];

    /** Secciones de cada nivel: Paso 2.4 (Documentos del nivel) y los sub-pasos de Paso 3. @var array<string, array{string, string}> */
    private const PASO3 = [
        'documentos_nivel' => ['Documentos del nivel', 'Turno, tipo de alumnado, Formato de Solicitud, recibo de pago y documentos del nivel.'],
        'inmueble' => ['Datos del inmueble', 'Dimensiones, colindancias y servicios cercanos.'],
        'infraestructura' => ['Infraestructura', 'Espacios del plantel, sanitarios y aulas del nivel.'],
        'mobiliario' => ['Mobiliario', 'Mobiliario y equipo de cada sala.'],
        'plan_estudios' => ['Plan de estudios', 'Plan de estudios del nivel.'],
        'plantilla_docente' => ['Plantilla docente', 'Personal docente del nivel.'],
        'matricula' => ['Matrícula', 'Alumnos inscritos en el nivel.'],
    ];

    public function __construct(
        private readonly EstadoPaso2 $estadoPaso2,
        private readonly EstadoPaso3 $estadoPaso3,
        private readonly EstadoPaso24 $estadoPaso24,
        private readonly DocumentosCompletos $documentosCompletos,
        private readonly TipoPersonaDeEscuela $tipoPersonaDeEscuela,
    ) {}

    public function paraEscuela(int $escuelaId): ResumenTramiteDTO
    {
        $escuela = Escuela::with(['plantel', 'escuelaNiveles.nivelEducativo', 'ternasNombres'])->findOrFail($escuelaId);
        $etapaFaltante = $this->estadoPaso2->etapaFaltante($escuelaId);

        $generales = $this->generales($escuela, $etapaFaltante);

        /** @var Collection<int, EscuelaNivel> $escuelaNiveles */
        $escuelaNiveles = $escuela->escuelaNiveles->sortBy('id')->values();
        $niveles = $escuelaNiveles
            ->map(fn (EscuelaNivel $escuelaNivel) => new NivelDelTramite(
                escuelaNivelId: $escuelaNivel->id,
                clave: $escuelaNivel->nivelEducativo->clave,
                nombre: $escuelaNivel->nivelEducativo->nombre,
                secciones: $this->seccionesDeNivel($escuelaNivel, $etapaFaltante),
            ))
            ->all();

        $todas = array_merge($generales, ...array_map(fn (NivelDelTramite $nivel) => $nivel->secciones, $niveles));

        /** @var Plantel $plantel */
        $plantel = $escuela->plantel;

        /** @var Collection<int, TernaNombre> $ternasNombres */
        $ternasNombres = $escuela->ternasNombres;

        return new ResumenTramiteDTO(
            escuelaId: $escuela->id,
            domicilio: self::domicilio($plantel),
            plantel: self::datosPlantel($plantel),
            generales: $generales,
            niveles: $niveles,
            completo: $niveles !== [] && collect($todas)->every(
                fn (SeccionTramite $s) => in_array($s->estado, ['completado', 'no_disponible', 'no_aplica'], true)
            ),
            nombre: $escuela->nombre_aprobado ?? $ternasNombres->sortBy('numero_propuesta')->first()?->nombre_propuesto,
            iniciadoEl: $escuela->created_at,
        );
    }

    /** @return array{paso: int, total: int}|null null si la sección no cuenta (no aplica / no disponible / desconocida). */
    public static function posicion(string $clave, ?string $nivelClave = null): ?array
    {
        $generales = array_keys(self::GENERALES);
        $indice = array_search($clave, $generales, true);

        if ($indice !== false) {
            return ['paso' => $indice + 1, 'total' => count($generales)];
        }

        $disponibles = array_filter(
            ['documentos_nivel', ...array_keys(self::RUTAS_PASO3)],
            fn (string $c) => $c !== 'mobiliario' || $nivelClave === self::NIVEL_CON_MOBILIARIO,
        );
        $indice = array_search($clave, $disponibles, true);

        return $indice === false
            ? null
            : ['paso' => count($generales) + $indice + 1, 'total' => count($generales) + count($disponibles)];
    }

    /** "{Nivel | Datos generales} · Paso X de N" para el eyebrow de cada página, o null si la sección no cuenta. */
    public static function encabezado(string $clave, ?NivelEducativo $nivel = null): ?string
    {
        $posicion = self::posicion($clave, $nivel?->clave);

        return $posicion === null
            ? null
            : ($nivel === null ? 'Datos generales' : $nivel->nombre)." · Paso {$posicion['paso']} de {$posicion['total']}";
    }

    public static function domicilio(Plantel $plantel): string
    {
        $numeroExt = $plantel->numero_ext !== null ? trim($plantel->numero_ext) : '';
        $numeroInt = $plantel->numero_int !== null ? trim($plantel->numero_int) : '';

        $partes = array_filter([
            trim($plantel->calle)
                .($numeroExt !== '' ? ' #'.$numeroExt : '')
                .($numeroInt !== '' ? ' Int. '.$numeroInt : ''),
            trim($plantel->colonia),
            trim($plantel->municipio),
        ], fn (string $p) => $p !== '');

        return implode(', ', $partes).', C.P. '.trim($plantel->codigo_postal);
    }

    /** @return array<string, string|null> */
    private static function datosPlantel(Plantel $plantel): array
    {
        return [
            'Calle y número' => $plantel->calle,
            'Número exterior' => $plantel->numero_ext,
            'Número interior' => $plantel->numero_int,
            'Colonia' => $plantel->colonia,
            'Localidad' => $plantel->localidad,
            'Municipio' => $plantel->municipio,
            'Código postal' => $plantel->codigo_postal,
            'Teléfono' => $plantel->telefono,
            'Correo electrónico' => $plantel->correo_electronico,
        ];
    }

    /** @return array<int, SeccionTramite> */
    private function generales(Escuela $escuela, ?string $etapaFaltante): array
    {
        $tipoPersona = $this->tipoPersonaDeEscuela->ejecutar($escuela->id);
        $rutaPaso2 = route('tramite.paso2', ['escuela' => $escuela->id]);

        return [
            $this->seccion('plantel', 'completado', null, null),
            $this->seccion('responsable', $tipoPersona !== null ? 'completado' : 'pendiente', $rutaPaso2, null),
            $this->seccion(
                'documentos',
                $this->estadoDocumentos($escuela->id, $tipoPersona, $etapaFaltante),
                route('tramite.paso2-documentos', ['escuela' => $escuela->id]),
                $tipoPersona === null ? self::completaPrimero('responsable') : null,
            ),
            $escuela->escuelaNiveles->isNotEmpty()
                ? $this->seccion('niveles', 'completado', $rutaPaso2, null)
                : $this->seccion('niveles', 'pendiente', $rutaPaso2, $etapaFaltante === null ? null : self::completaPrimero($etapaFaltante)),
        ];
    }

    /** en_curso = algunos documentos pero no todos, o todos con una vigencia vencida (EstadoPaso2 decide qué es completo). */
    private function estadoDocumentos(int $escuelaId, ?string $tipoPersona, ?string $etapaFaltante): string
    {
        if ($tipoPersona === null) {
            return 'pendiente';
        }

        if ($etapaFaltante === null) {
            return 'completado';
        }

        $faltan = count($this->documentosCompletos->clavesPendientes($escuelaId, $tipoPersona));

        return $faltan < count($this->documentosCompletos->clavesAplicables($tipoPersona)) ? 'en_curso' : 'pendiente';
    }

    /** @return array<int, SeccionTramite> */
    private function seccionesDeNivel(EscuelaNivel $escuelaNivel, ?string $etapaFaltante): array
    {
        $nivelClave = $escuelaNivel->nivelEducativo->clave;
        $estados = DB::table('escuela_nivel_pasos')
            ->join('pasos_captura', 'pasos_captura.id', '=', 'escuela_nivel_pasos.paso_captura_id')
            ->where('escuela_nivel_pasos.escuela_nivel_id', $escuelaNivel->id)
            ->pluck('escuela_nivel_pasos.estado', 'pasos_captura.clave');

        // WS-5b: cada nivel empieza por su Paso 2.4; Paso 3 queda detrás (EstadoPaso3).
        $etapa24 = $this->estadoPaso24->etapaFaltante($escuelaNivel->id);
        $secciones = [$this->seccion(
            'documentos_nivel',
            match ($etapa24) {
                null => 'completado',
                EstadoPaso24::DOCUMENTOS_NIVEL => 'en_curso',
                default => 'pendiente',
            },
            route(self::RUTA_DOCUMENTOS_NIVEL, ['escuelaNivel' => $escuelaNivel->id]),
            $etapaFaltante === null ? null : self::completaPrimero($etapaFaltante),
            $nivelClave,
        )];
        $anterior = self::PASO3['documentos_nivel'][0];

        foreach (DB::table('pasos_captura')->orderBy('orden')->pluck('nombre', 'clave') as $clave => $nombreCatalogo) {
            $clave = (string) $clave;

            if (! isset(self::RUTAS_PASO3[$clave])) {
                $secciones[] = $this->seccion($clave, 'no_disponible', null, null, $nivelClave, (string) $nombreCatalogo);

                continue;
            }

            if ($clave === 'mobiliario' && $nivelClave !== self::NIVEL_CON_MOBILIARIO) {
                $secciones[] = $this->seccion($clave, 'no_aplica', null, null, $nivelClave);

                continue;
            }

            $motivo = match (true) {
                $etapaFaltante !== null => self::completaPrimero($etapaFaltante),
                ! $this->estadoPaso3->puedeAcceder($escuelaNivel->id, $clave) => 'Completa primero: '.$anterior,
                default => null,
            };

            $secciones[] = $this->seccion(
                $clave,
                match ($estados->get($clave)) {
                    'completado' => 'completado',
                    'en_progreso' => 'en_curso',
                    default => 'pendiente',
                },
                route(self::RUTAS_PASO3[$clave], ['escuelaNivel' => $escuelaNivel->id]),
                $motivo,
                $nivelClave,
            );
            $anterior = self::PASO3[$clave][0];
        }

        return $secciones;
    }

    private function seccion(string $clave, string $estado, ?string $href, ?string $motivoBloqueo, ?string $nivelClave = null, string $nombreCatalogo = ''): SeccionTramite
    {
        [$nombre, $descripcion] = self::GENERALES[$clave] ?? self::PASO3[$clave] ?? [$nombreCatalogo, ''];

        $accion = match (true) {
            $href === null || $motivoBloqueo !== null => null,
            $estado === 'completado' => in_array($clave, self::REVISABLES, true) ? 'revisar' : null,
            $estado === 'en_curso' => 'continuar',
            $estado === 'pendiente' => 'comenzar',
            default => null,
        };
        $posicion = self::posicion($clave, $nivelClave);

        return new SeccionTramite(
            clave: $clave,
            nombre: $nombre,
            descripcion: $descripcion,
            estado: $estado,
            accion: $accion,
            href: $accion === null ? null : $href,
            motivoBloqueo: $motivoBloqueo,
            paso: $posicion['paso'] ?? null,
            totalPasos: $posicion['total'] ?? null,
        );
    }

    /** @param string $etapa EstadoPaso2::RESPONSABLE|EstadoPaso2::DOCUMENTOS — coinciden con claves de GENERALES. */
    private static function completaPrimero(string $etapa): string
    {
        return 'Completa primero: '.self::GENERALES[$etapa][0];
    }
}
