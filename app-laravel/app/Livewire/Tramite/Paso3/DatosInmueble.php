<?php

namespace App\Livewire\Tramite\Paso3;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Inmueble\DatosInmuebleYaCapturados;
use App\Application\Inmueble\DTO\DatosInmueble as DatosInmuebleDTO;
use App\Application\Inmueble\RegistrarDatosInmueble;
use App\Models\AcreditacionOcupacionLegal;
use App\Models\ConstanciaSeguridadEstructural;
use App\Models\DocumentoPlantel;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\TernaNombre;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Presentación pura de PRD §5 Paso 3, sub-paso 1 (Datos del inmueble).
 * RegistrarDatosInmueble es la única escritura (ADR-001); el auto-completado
 * multi-nivel pasa por MarcarPasoCompletado.
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
    public EscuelaNivel $escuelaNivel;

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
    ): void {
        $this->escuelaNivel = $escuelaNivel;

        // Auto-completado multi-nivel (spec §6): los datos son del plantel y ya
        // están capturados, así que este nivel no tiene nada que capturar aquí.
        if ($yaCapturados->ejecutar($this->plantelId())) {
            $marcarPasoCompletado->ejecutar($escuelaNivel->id, 'inmueble');
            $this->redirectRoute('tramite.paso3-infraestructura', ['escuelaNivel' => $escuelaNivel->id]);
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

    public function guardar(RegistrarDatosInmueble $registrarDatosInmueble): void
    {
        $this->validate([
            'metrosTotales' => ['required', 'numeric', 'min:0.01'],
            'metrosConstruidos' => ['nullable', 'numeric', 'min:0'],
            'colindanciaNorte' => ['nullable', 'string', 'max:150'],
            'colindanciaSur' => ['nullable', 'string', 'max:150'],
            'colindanciaEste' => ['nullable', 'string', 'max:150'],
            'colindanciaOeste' => ['nullable', 'string', 'max:150'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'areaCivicaM2' => ['nullable', 'numeric', 'min:0'],
            'serviciosCercanos.*.nombre' => ['required', 'string', 'max:200'],
            'serviciosCercanos.*.tipo' => ['required', 'in:salud,emergencia'],
            'serviciosCercanos.*.distanciaValor' => ['nullable', 'numeric', 'min:0'],
            'serviciosCercanos.*.distanciaUnidad' => ['nullable', 'in:m,km'],
            'estudiosActuales.*.nivelEducativoId' => ['nullable', 'integer', 'exists:niveles_educativos,id'],
            'estudiosActuales.*.otroNivelTexto' => ['nullable', 'string', 'max:150'],
            'estudiosActuales.*.numeroAlumnos' => ['required', 'integer', 'min:0', 'max:32767'],
        ]);

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

        $this->redirectRoute('tramite.paso3-infraestructura', ['escuelaNivel' => $this->escuelaNivel->id]);
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

    public function render()
    {
        return view('livewire.tramite.paso3.datos-inmueble', [
            'terna' => $this->terna(),
            'acreditacion' => $this->acreditacion(),
            'constancia' => $this->constancia(),
            'niveles' => $this->nivelesDisponibles(),
        ])->layoutData(['escuelaId' => $this->escuelaNivel->escuela_id, 'escuelaNivelId' => $this->escuelaNivel->id]);
    }
}
