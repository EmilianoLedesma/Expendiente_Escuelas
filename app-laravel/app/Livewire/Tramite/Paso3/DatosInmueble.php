<?php

namespace App\Livewire\Tramite\Paso3;

use App\Application\Captura\ReglasCaptura;
use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Inmueble\DatosInmuebleYaCapturados;
use App\Application\Inmueble\DTO\DatosInmueble as DatosInmuebleDTO;
use App\Application\Inmueble\RegistrarDatosInmueble;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\RegistroCompartidoBloqueado;
use App\Application\Tramite\ResumenTramite;
use App\Application\Tramite\TramiteEditable;
use App\Livewire\Concerns\NotificaToast;
use App\Livewire\Tramite\Paso3\Concerns\CompuertaPaso3;
use App\Models\AcreditacionOcupacionLegal;
use App\Models\ConstanciaSeguridadEstructural;
use App\Models\DocumentoPlantel;
use App\Models\EscuelaNivel;
use App\Models\InmuebleEstudioActual;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\ServicioCercano;
use App\Models\TernaNombre;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Presentación pura de PRD §5 Paso 3, sub-paso 1 (Datos del inmueble).
 * RegistrarDatosInmueble es la única escritura (ADR-001); el auto-completado
 * multi-nivel pasa por MarcarPasoCompletado.
 *
 * Ese auto-completado es en GET: solo corre DESPUÉS de CompuertaPaso3 (Paso 2
 * completo; Inmueble es el primer sub-paso, así que la precondición de orden
 * siempre se cumple). Es idempotente (updateOrInsert).
 *
 * Los datos de Paso 2.2 (escritura del inmueble, constancia de seguridad
 * estructural) y la terna de nombres de Paso 2.1 se muestran como texto
 * plano de solo lectura, solo para contexto. Es el primer caso de
 * "mostrar datos capturados en otro paso" y es deliberadamente mínimo: NO es
 * precedente de una futura feature de revisión de documentos.
 *
 * No captura `modalidad` — COMPENDIO la circunscribe a Media
 * Superior/Superior, fuera del alcance de este MVP.
 */
#[Layout('layouts.tramite')]
class DatosInmueble extends Component
{
    use CompuertaPaso3;
    use NotificaToast;

    public EscuelaNivel $escuelaNivel;

    /** El responsable de un nivel no edita el inmueble: es del plantel, compartido. */
    #[Locked]
    public bool $soloLectura = false;

    public float|int|string $metrosTotales = '';

    public float|int|string|null $metrosConstruidos = null;

    public string $colindanciaNorte = '';

    public string $colindanciaSur = '';

    public string $colindanciaEste = '';

    public string $colindanciaOeste = '';

    public float|int|string|null $latitud = null;

    public float|int|string|null $longitud = null;

    public float|int|string|null $areaCivicaM2 = null;

    public bool $tieneAstaBandera = false;

    /** @var list<array<string, mixed>> */
    public array $serviciosCercanos = [];

    /** @var list<array<string, mixed>> */
    public array $estudiosActuales = [];

    public function mount(
        EscuelaNivel $escuelaNivel,
        DatosInmuebleYaCapturados $yaCapturados,
        MarcarPasoCompletado $marcarPasoCompletado,
        EstadoPaso3 $estadoPaso3,
    ): void {
        $this->escuelaNivel = $escuelaNivel;

        if ($this->redirigirSiNoAlcanzable($escuelaNivel, 'inmueble')) {
            return;
        }

        $this->soloLectura = ! Gate::allows('updateInmueble', $escuelaNivel);

        if ($yaCapturados->ejecutar($this->plantelId())) {
            // WS-7b-2: el dueño que ya completó este paso lo edita, precargado. Con el paso reabierto por una
            // devolución también: nunca se auto-completa ni se salta adelante.
            if (! $this->soloLectura && ($estadoPaso3->completado($escuelaNivel->id, 'inmueble') || $this->enDevolucion($escuelaNivel))) {
                $this->precargar();

                return;
            }

            // Auto-completado multi-nivel (spec §6): los datos son del plantel y ya
            // están capturados, así que este nivel no tiene nada que capturar aquí.
            $marcarPasoCompletado->ejecutar($escuelaNivel->id, 'inmueble');
            $this->redirectRoute('tramite.paso3-infraestructura', ['escuelaNivel' => $escuelaNivel->id]);
        }
    }

    private function precargar(): void
    {
        $plantel = $this->plantel();

        $this->metrosTotales = $plantel->metros_totales ?? '';
        $this->metrosConstruidos = $plantel->metros_construidos;
        $this->colindanciaNorte = (string) $plantel->colindancia_norte;
        $this->colindanciaSur = (string) $plantel->colindancia_sur;
        $this->colindanciaEste = (string) $plantel->colindancia_este;
        $this->colindanciaOeste = (string) $plantel->colindancia_oeste;
        $this->latitud = $plantel->latitud;
        $this->longitud = $plantel->longitud;
        $this->areaCivicaM2 = $plantel->area_civica_m2;
        $this->tieneAstaBandera = (bool) $plantel->tiene_asta_bandera;

        $this->serviciosCercanos = [];
        foreach (ServicioCercano::where('plantel_id', $plantel->id)->orderBy('id')->get() as $s) {
            $this->serviciosCercanos[] = [
                'nombre' => $s->nombre,
                'tipo' => $s->tipo,
                'esPublico' => (bool) $s->es_publico,
                'distanciaValor' => $s->distancia_valor,
                'distanciaUnidad' => $s->distancia_unidad ?? 'm',
            ];
        }

        $this->estudiosActuales = [];
        foreach (InmuebleEstudioActual::where('plantel_id', $plantel->id)->orderBy('id')->get() as $e) {
            $this->estudiosActuales[] = [
                'nivelEducativoId' => $e->nivel_educativo_id ?? '',
                'otroNivelTexto' => (string) $e->otro_nivel_texto,
                'numeroAlumnos' => $e->numero_alumnos,
            ];
        }
    }

    public function agregarServicio(): void
    {
        $this->serviciosCercanos[] = ['nombre' => '', 'tipo' => 'salud', 'esPublico' => false, 'distanciaValor' => null, 'distanciaUnidad' => 'm'];
    }

    public function quitarServicio(int $indice): void
    {
        unset($this->serviciosCercanos[$indice]);
        $this->serviciosCercanos = array_values($this->serviciosCercanos);
    }

    public function agregarEstudio(): void
    {
        $this->estudiosActuales[] = ['nivelEducativoId' => '', 'otroNivelTexto' => '', 'numeroAlumnos' => null];
    }

    public function quitarEstudio(int $indice): void
    {
        unset($this->estudiosActuales[$indice]);
        $this->estudiosActuales = array_values($this->estudiosActuales);
    }

    /**
     * Límites = tipo de la columna en el DDL (planteles NUMERIC(10,2) y
     * NUMERIC(10,7), servicios_cercanos.distancia_valor NUMERIC(6,2),
     * inmueble_estudio_actual.numero_alumnos SMALLINT): un valor fuera de
     * rango es un mensaje en el campo, no un error de base de datos.
     */
    protected function rules(): array
    {
        return [
            'metrosTotales' => ReglasCaptura::decimal(ReglasCaptura::MAX_NUMERIC_10_2, min: 0.01, requerido: true),
            'metrosConstruidos' => ReglasCaptura::decimal(ReglasCaptura::MAX_NUMERIC_10_2, requerido: true),
            'colindanciaNorte' => ReglasCaptura::texto(max: 150),
            'colindanciaSur' => ReglasCaptura::texto(max: 150),
            'colindanciaEste' => ReglasCaptura::texto(max: 150),
            'colindanciaOeste' => ReglasCaptura::texto(max: 150),
            // Una ubicación es el par completo: una coordenada sola no ubica nada.
            'latitud' => ReglasCaptura::decimal(90, decimales: 7, min: -90, requerido: 'required_with:longitud'),
            'longitud' => ReglasCaptura::decimal(180, decimales: 7, min: -180, requerido: 'required_with:latitud'),
            'areaCivicaM2' => ReglasCaptura::decimal(ReglasCaptura::MAX_NUMERIC_10_2),
            'tieneAstaBandera' => ['boolean'],
            'serviciosCercanos.*.nombre' => ReglasCaptura::texto(requerido: true, max: 200),
            'serviciosCercanos.*.tipo' => ['required', 'in:salud,emergencia'],
            'serviciosCercanos.*.esPublico' => ['nullable', 'boolean'],
            'serviciosCercanos.*.distanciaValor' => ReglasCaptura::decimal(ReglasCaptura::MAX_NUMERIC_6_2),
            'serviciosCercanos.*.distanciaUnidad' => ['nullable', 'in:m,km'],
            'estudiosActuales.*.nivelEducativoId' => ['nullable', 'integer', 'exists:niveles_educativos,id'],
            'estudiosActuales.*.otroNivelTexto' => ReglasCaptura::texto(max: 150),
            'estudiosActuales.*.numeroAlumnos' => ReglasCaptura::entero(ReglasCaptura::MAX_SMALLINT, requerido: true),
        ];
    }

    public function guardar(RegistrarDatosInmueble $registrarDatosInmueble): void
    {
        abort_unless(Gate::allows('updateInmueble', $this->escuelaNivel), 403);

        $this->validate();

        try {
            $registrarDatosInmueble->ejecutar(
                $this->plantelId(),
                $this->escuelaNivel->id,
                new DatosInmuebleDTO(
                    metrosTotales: (float) $this->metrosTotales,
                    metrosConstruidos: $this->numeroONull($this->metrosConstruidos),
                    colindanciaNorte: $this->textoONull($this->colindanciaNorte),
                    colindanciaSur: $this->textoONull($this->colindanciaSur),
                    colindanciaEste: $this->textoONull($this->colindanciaEste),
                    colindanciaOeste: $this->textoONull($this->colindanciaOeste),
                    latitud: $this->numeroONull($this->latitud),
                    longitud: $this->numeroONull($this->longitud),
                    areaCivicaM2: $this->numeroONull($this->areaCivicaM2),
                    tieneAstaBandera: $this->tieneAstaBandera,
                    serviciosCercanos: $this->serviciosDeclarados(),
                    estudiosActuales: $this->estudiosDeclarados(),
                ),
            );
        } catch (DatosInvalidos $e) {
            foreach ($e->errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }
            $this->toastDatosInvalidos();

            return;
        } catch (PrecondicionIncumplida $e) {
            // WS-7a §5.4: otro trámite del plantel ya se envió; los datos compartidos no cambian.
            if ($e->etapaFaltante === TramiteEditable::ENVIADO) {
                $this->addError('plantel', $e->getMessage());
                $this->toast('No se guardó', $e->getMessage(), 'error');

                return;
            }

            // WS-2.4b: reintenta la compuerta de mount() en vez de un 500.
            $this->redirigirSiNoAlcanzable($this->escuelaNivel, 'inmueble');

            return;
        }

        $this->redirigirTrasGuardar($this->escuelaNivel, 'tramite.paso3-infraestructura', 'Datos del inmueble guardados', 'Continúa con la infraestructura.');
    }

    /** Terna de nombres de Paso 2.1 — solo lectura, solo contexto. */
    public function terna(): Collection
    {
        return TernaNombre::where('escuela_id', $this->escuelaNivel->escuela_id)
            ->orderBy('numero_propuesta')
            ->get();
    }

    /** Acreditación de ocupación legal de Paso 2.2 — solo lectura, solo contexto. */
    public function acreditacion(): ?AcreditacionOcupacionLegal
    {
        return AcreditacionOcupacionLegal::whereIn(
            'documento_plantel_id',
            DocumentoPlantel::where('plantel_id', $this->plantelId())->pluck('id')
        )->first();
    }

    /** Constancia de seguridad estructural de Paso 2.2 — solo lectura, solo contexto. */
    public function constancia(): ?ConstanciaSeguridadEstructural
    {
        return ConstanciaSeguridadEstructural::whereIn(
            'documento_plantel_id',
            DocumentoPlantel::where('plantel_id', $this->plantelId())->pluck('id')
        )->first();
    }

    /** Domicilio del plantel, capturado en Paso 1 — solo lectura (PRD §5 Paso 3.1). */
    public function plantel(): Plantel
    {
        return Plantel::findOrFail($this->plantelId());
    }

    public function nivelesDisponibles(): Collection
    {
        return NivelEducativo::orderBy('orden')->get();
    }

    private function plantelId(): int
    {
        return (int) $this->escuelaNivel->escuela->plantel_id;
    }

    /** @return list<array<string, mixed>> */
    private function serviciosDeclarados(): array
    {
        return array_map(fn (array $servicio): array => [
            'nombre' => (string) $servicio['nombre'],
            'tipo' => (string) $servicio['tipo'],
            'esPublico' => isset($servicio['esPublico']) ? (bool) $servicio['esPublico'] : null,
            'distanciaValor' => $this->numeroONull($servicio['distanciaValor'] ?? null),
            'distanciaUnidad' => $this->textoONull((string) ($servicio['distanciaUnidad'] ?? '')),
        ], $this->serviciosCercanos);
    }

    /** @return list<array<string, mixed>> */
    private function estudiosDeclarados(): array
    {
        return array_map(fn (array $estudio): array => [
            'nivelEducativoId' => ($estudio['nivelEducativoId'] ?? '') === '' ? null : (int) $estudio['nivelEducativoId'],
            'otroNivelTexto' => $this->textoONull((string) ($estudio['otroNivelTexto'] ?? '')),
            'numeroAlumnos' => (int) $estudio['numeroAlumnos'],
        ], $this->estudiosActuales);
    }

    private function numeroONull(mixed $valor): ?float
    {
        return ($valor === null || $valor === '') ? null : (float) $valor;
    }

    private function textoONull(string $valor): ?string
    {
        return $valor === '' ? null : $valor;
    }

    /**
     * Con otro nivel con datos propios, RegistrarDatosInmueble solo completa NULL (false es un valor):
     * los campos que ya tienen valor se muestran de solo lectura.
     *
     * @return array<string, bool> propiedad del componente => fijo
     */
    private function camposFijos(Plantel $plantel): array
    {
        $columnas = [
            'metrosTotales' => 'metros_totales', 'metrosConstruidos' => 'metros_construidos',
            'colindanciaNorte' => 'colindancia_norte', 'colindanciaSur' => 'colindancia_sur',
            'colindanciaEste' => 'colindancia_este', 'colindanciaOeste' => 'colindancia_oeste',
            'latitud' => 'latitud', 'longitud' => 'longitud', 'areaCivicaM2' => 'area_civica_m2',
            'tieneAstaBandera' => 'tiene_asta_bandera',
        ];

        return array_map(fn (string $columna): bool => $plantel->getAttribute($columna) !== null, $columnas);
    }

    public function render(RegistroCompartidoBloqueado $registroCompartidoBloqueado)
    {
        $plantel = $this->plantel();
        $bloqueado = ! $this->soloLectura && $registroCompartidoBloqueado->bloqueado($this->plantelId(), $this->escuelaNivel->id);

        return view('livewire.tramite.paso3.datos-inmueble', [
            'bloqueado' => $bloqueado,
            'fijo' => $bloqueado ? $this->camposFijos($plantel) : [],
            'serviciosFijos' => $bloqueado && ServicioCercano::where('plantel_id', $plantel->id)->exists(),
            'estudiosFijos' => $bloqueado && InmuebleEstudioActual::where('plantel_id', $plantel->id)->exists(),
            'encabezado' => ResumenTramite::encabezado('inmueble', $this->escuelaNivel->nivelEducativo),
            'domicilio' => ResumenTramite::domicilio($this->plantel()),
            'plantel' => $plantel,
            'terna' => $this->terna(),
            'acreditacion' => $this->acreditacion(),
            'constancia' => $this->constancia(),
            'niveles' => $this->nivelesDisponibles(),
        ])->layoutData(['escuelaId' => $this->escuelaNivel->escuela_id, 'escuelaNivelId' => $this->escuelaNivel->id, 'seccionActual' => 'inmueble']);
    }
}
