<?php

namespace App\Livewire\Tramite\Paso3;

use App\Application\Infraestructura\DTO\DatosInfraestructuraNivel;
use App\Application\Infraestructura\InfraestructuraYaCapturada;
use App\Application\Infraestructura\RegistrarInfraestructuraNivel;
use App\Models\AulaNivel;
use App\Models\EscuelaNivel;
use App\Models\InstalacionEspacio;
use App\Models\TipoEspacio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Presentación pura de PRD §5 Paso 3, sub-paso 2 (Infraestructura del nivel).
 * El formulario se genera desde niveles_tipos_espacios para el nivel en
 * curso. RegistrarInfraestructuraNivel es la única escritura (ADR-001).
 *
 * Categorías de sanitarios por nivel: COMPENDIO (línea 132) enumera cuatro
 * categorías para el formulario genérico de Básica; el DDL añade
 * alumnado_maternal y personal, que solo tienen sentido en Inicial. Ese
 * reparto es la inferencia aplicada aquí.
 *
 * Reutilización de plantel: cuando InfraestructuraYaCapturada es true no se
 * auto-completa el paso (a diferencia del sub-paso 1) porque aulas_nivel
 * sigue siendo por-nivel y este formulario siempre la debe. Narrowing
 * deliberado de spec §6, documentado en el reporte de la Tarea 8.
 */
#[Layout('layouts.tramite')]
class InfraestructuraNivel extends Component
{
    private const SANITARIOS_INICIAL = ['alumnado_maternal', 'personal'];

    private const SANITARIOS_BASICA = ['alumnado_masculino', 'alumnado_femenino', 'personal_masculino', 'personal_femenino'];

    public EscuelaNivel $escuelaNivel;

    /** Los datos a nivel plantel ya se capturaron: se muestran, no se re-capturan. */
    public bool $soloLectura = false;

    /** @var array<int, array<string, mixed>> tipo_espacio_id => campos del espacio */
    public array $espacios = [];

    /** @var array<int, array<string, mixed>> tipo_material_id => titulos/volumenes de biblioteca */
    public array $materialesBiblioteca = [];

    /** @var array<string, array<string, mixed>> categoria => campos del sanitario */
    public array $sanitarios = [];

    public int|string $numeroAulas = '';

    public float|int|string|null $superficieAulasM2 = null;

    public function mount(EscuelaNivel $escuelaNivel, InfraestructuraYaCapturada $yaCapturada): void
    {
        $this->escuelaNivel = $escuelaNivel;
        $this->soloLectura = $yaCapturada->ejecutar($this->plantelId());

        $aulas = AulaNivel::where('escuela_nivel_id', $escuelaNivel->id)->first();
        if ($aulas !== null) {
            $this->numeroAulas = $aulas->numero_aulas;
            $this->superficieAulasM2 = $aulas->superficie_m2;
        }
    }

    public function guardar(RegistrarInfraestructuraNivel $registrarInfraestructura): void
    {
        $bodegaId = $this->idTipoBodega();

        $this->validate([
            'numeroAulas' => ['required', 'integer', 'min:1', 'max:32767'],
            'superficieAulasM2' => ['nullable', 'numeric', 'min:0'],
            'espacios.*.cantidad' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'espacios.*.superficieM2' => ['nullable', 'numeric', 'min:0'],
            'espacios.*.capacidadPromedio' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'espacios.*.destinadoA' => ['nullable', 'string', 'max:200'],
            "espacios.{$bodegaId}.destinadoA" => ['nullable', 'in:limpieza,general,otro'],
            'espacios.*.campoFutbolTipoSuperficie' => ['nullable', 'string', 'max:50'],
            'espacios.*.campoFutbolFormato' => ['nullable', 'string', 'max:20'],
            'sanitarios.*.cantidadRetretes' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'sanitarios.*.cantidadMingitorios' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'sanitarios.*.cantidadLavabos' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'sanitarios.*.superficieM2' => ['nullable', 'numeric', 'min:0'],
            'sanitarios.*.cantidadBacinicas' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'materialesBiblioteca.*.numeroTitulos' => ['nullable', 'integer', 'min:0'],
            'materialesBiblioteca.*.numeroVolumenes' => ['nullable', 'integer', 'min:0'],
        ]);

        $registrarInfraestructura->ejecutar(
            $this->plantelId(),
            $this->escuelaNivel->id,
            new DatosInfraestructuraNivel(
                espacios: $this->soloLectura ? [] : $this->espaciosDeclarados(),
                sanitarios: $this->soloLectura ? [] : $this->sanitariosDeclarados(),
                numeroAulas: (int) $this->numeroAulas,
                superficieAulasM2: $this->superficieAulasM2 === null || $this->superficieAulasM2 === '' ? null : (float) $this->superficieAulasM2,
            ),
        );

        $this->redirectRoute('tramite.paso3-mobiliario', ['escuelaNivel' => $this->escuelaNivel->id]);
    }

    /** Tipos de espacio aplicables al nivel en curso, en orden de captura. */
    public function tiposAplicables(): Collection
    {
        return TipoEspacio::query()
            ->join('niveles_tipos_espacios', 'niveles_tipos_espacios.tipo_espacio_id', '=', 'tipos_espacios.id')
            ->where('niveles_tipos_espacios.nivel_educativo_id', $this->escuelaNivel->nivel_educativo_id)
            ->orderBy('tipos_espacios.categoria')
            ->orderBy('tipos_espacios.nombre')
            ->select('tipos_espacios.*')
            ->get();
    }

    /** @return list<string> */
    public function categoriasSanitarios(): array
    {
        return $this->escuelaNivel->nivelEducativo->clave === 'inicial'
            ? self::SANITARIOS_INICIAL
            : self::SANITARIOS_BASICA;
    }

    /** Solo se consulta cuando $soloLectura: lo ya capturado, para mostrarlo. */
    public function espaciosCapturados(): Collection
    {
        return InstalacionEspacio::with('tipoEspacio')
            ->where('plantel_id', $this->plantelId())
            ->get();
    }

    /** Solo se consulta cuando $soloLectura. */
    public function sanitariosCapturados(): Collection
    {
        return DB::table('sanitarios')->where('plantel_id', $this->plantelId())->orderBy('categoria')->get();
    }

    public function materialesDisponibles(): Collection
    {
        return DB::table('tipos_material_biblioteca')->orderBy('id')->get();
    }

    private function plantelId(): int
    {
        return (int) $this->escuelaNivel->escuela->plantel_id;
    }

    /** Id de tipos_espacios cuya clave es 'bodega', resuelto por clave (nunca hardcodeado). */
    private function idTipoBodega(): ?int
    {
        return TipoEspacio::where('clave', 'bodega')->value('id');
    }

    /** @return list<array<string, mixed>> */
    private function espaciosDeclarados(): array
    {
        $declarados = [];

        foreach ($this->tiposAplicables() as $tipo) {
            $entrada = $this->espacios[$tipo->id] ?? [];
            $cantidad = $entrada['cantidad'] ?? null;

            if ($cantidad === null || $cantidad === '') {
                continue;
            }

            $declarados[] = [
                'tipoEspacioId' => (int) $tipo->id,
                'cantidad' => (int) $cantidad,
                'superficieM2' => $this->numeroONull($entrada['superficieM2'] ?? null),
                'capacidadPromedio' => ($entrada['capacidadPromedio'] ?? '') === '' ? null : (int) $entrada['capacidadPromedio'],
                'ventilacionNatural' => isset($entrada['ventilacionNatural']) ? (bool) $entrada['ventilacionNatural'] : null,
                'iluminacionNatural' => isset($entrada['iluminacionNatural']) ? (bool) $entrada['iluminacionNatural'] : null,
                'destinadoA' => ($entrada['destinadoA'] ?? '') === '' ? null : (string) $entrada['destinadoA'],
                'campoFutbol' => $tipo->permite_campo_futbol
                    ? [
                        'tipoSuperficie' => ($entrada['campoFutbolTipoSuperficie'] ?? '') === '' ? null : (string) $entrada['campoFutbolTipoSuperficie'],
                        'formato' => ($entrada['campoFutbolFormato'] ?? '') === '' ? null : (string) $entrada['campoFutbolFormato'],
                    ]
                    : null,
                'materialesBiblioteca' => $tipo->permite_material_biblioteca ? $this->materialesDeclarados() : [],
            ];
        }

        return $declarados;
    }

    /** @return list<array<string, mixed>> */
    private function materialesDeclarados(): array
    {
        $declarados = [];
        $idsValidos = $this->materialesDisponibles()->pluck('id')->all();

        foreach ($this->materialesBiblioteca as $tipoMaterialId => $entrada) {
            if (! in_array((int) $tipoMaterialId, $idsValidos, true)) {
                continue;
            }

            $titulos = $entrada['numeroTitulos'] ?? null;
            $volumenes = $entrada['numeroVolumenes'] ?? null;

            if (($titulos === null || $titulos === '') && ($volumenes === null || $volumenes === '')) {
                continue;
            }

            $declarados[] = [
                'tipoMaterialId' => (int) $tipoMaterialId,
                'numeroTitulos' => ($titulos === null || $titulos === '') ? null : (int) $titulos,
                'numeroVolumenes' => ($volumenes === null || $volumenes === '') ? null : (int) $volumenes,
            ];
        }

        return $declarados;
    }

    /** @return list<array<string, mixed>> */
    private function sanitariosDeclarados(): array
    {
        $declarados = [];

        foreach ($this->categoriasSanitarios() as $categoria) {
            $entrada = $this->sanitarios[$categoria] ?? [];

            $tieneAlgo = collect($entrada)->contains(fn ($valor) => $valor !== null && $valor !== '');
            if (! $tieneAlgo) {
                continue;
            }

            $declarados[] = [
                'categoria' => $categoria,
                'cantidadRetretes' => ($entrada['cantidadRetretes'] ?? '') === '' ? null : (int) $entrada['cantidadRetretes'],
                'cantidadMingitorios' => ($entrada['cantidadMingitorios'] ?? '') === '' ? null : (int) $entrada['cantidadMingitorios'],
                'cantidadLavabos' => ($entrada['cantidadLavabos'] ?? '') === '' ? null : (int) $entrada['cantidadLavabos'],
                'superficieM2' => $this->numeroONull($entrada['superficieM2'] ?? null),
                'ventilacionNatural' => isset($entrada['ventilacionNatural']) ? (bool) $entrada['ventilacionNatural'] : null,
                'iluminacionNatural' => isset($entrada['iluminacionNatural']) ? (bool) $entrada['iluminacionNatural'] : null,
                'cantidadBacinicas' => $categoria === 'alumnado_maternal' && ($entrada['cantidadBacinicas'] ?? '') !== ''
                    ? (int) $entrada['cantidadBacinicas']
                    : null,
            ];
        }

        return $declarados;
    }

    private function numeroONull(mixed $valor): ?float
    {
        return ($valor === null || $valor === '') ? null : (float) $valor;
    }

    public function render()
    {
        return view('livewire.tramite.paso3.infraestructura-nivel', [
            'tipos' => $this->soloLectura ? collect() : $this->tiposAplicables(),
            'categorias' => $this->soloLectura ? [] : $this->categoriasSanitarios(),
            'materiales' => $this->soloLectura ? collect() : $this->materialesDisponibles(),
            'espaciosCapturados' => $this->soloLectura ? $this->espaciosCapturados() : collect(),
            'sanitariosCapturados' => $this->soloLectura ? $this->sanitariosCapturados() : collect(),
        ])->layoutData(['escuelaNivelId' => $this->escuelaNivel->id]);
    }
}
