<?php

namespace App\Livewire\Tramite\Paso3;

use App\Application\Captura\ReglasCaptura;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Infraestructura\CatalogosInfraestructura;
use App\Application\Infraestructura\CategoriasSanitariosPorNivel;
use App\Application\Infraestructura\DTO\DatosEdicionInfraestructura;
use App\Application\Infraestructura\DTO\DatosInfraestructuraNivel;
use App\Application\Infraestructura\EditarInfraestructuraCompartida;
use App\Application\Infraestructura\InfraestructuraYaCapturada;
use App\Application\Infraestructura\RegistrarInfraestructuraNivel;
use App\Application\Tramite\RegistroCompartidoBloqueado;
use App\Application\Tramite\ResumenTramite;
use App\Application\Tramite\TramiteEditable;
use App\Livewire\Concerns\NotificaToast;
use App\Livewire\Tramite\Paso3\Concerns\CompuertaPaso3;
use App\Models\AulaNivel;
use App\Models\EscuelaNivel;
use App\Models\InstalacionEspacio;
use App\Models\TipoEspacio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
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
 * Reutilización de plantel: nunca se auto-completa el paso (a diferencia del
 * sub-paso 1) porque aulas_nivel sigue siendo por-nivel y este formulario
 * siempre la debe. Narrowing deliberado de spec §6, documentado en el
 * reporte de la Tarea 8.
 *
 * ADR-005: lo capturable no es "todo o nada" por plantel — es una unión por
 * nivel. La pantalla se parte en dos: "ya capturado" (solo lectura, lo que
 * cualquier nivel de este plantel ya escribió) y "por capturar" (editable,
 * solo lo aplicable a este nivel que aún no existe). tiposAplicables() y
 * categoriasSanitarios() ya excluyen lo capturado, así que lo que se
 * renderiza como editable es exactamente lo que se envía en guardar().
 *
 * WS-7b-2: para el dueño (EscuelaNivelPolicy::editarCompartido) "ya capturado" es
 * un segundo formulario: edicion* → guardarEdicion() → EditarInfraestructuraCompartida.
 * El responsable por nivel lo sigue viendo de solo lectura.
 */
#[Layout('layouts.tramite')]
class InfraestructuraNivel extends Component
{
    use CompuertaPaso3;
    use NotificaToast;

    public EscuelaNivel $escuelaNivel;

    /** @var list<int> ids de tipos_espacios ya capturados por este plantel (cualquier nivel). */
    public array $tiposCapturados = [];

    /** @var list<string> categorías de sanitario ya capturadas por este plantel (cualquier nivel). */
    public array $categoriasCapturadas = [];

    /** @var array<int, array<string, mixed>> tipo_espacio_id => campos del espacio */
    public array $espacios = [];

    /** @var array<int, array<string, mixed>> tipo_material_id => titulos/volumenes de biblioteca */
    public array $materialesBiblioteca = [];

    /** @var array<string, array<string, mixed>> categoria => campos del sanitario */
    public array $sanitarios = [];

    public int|string $numeroAulas = '';

    public float|int|string|null $superficieAulasM2 = null;

    /** Dueño del trámite: "ya capturado" se edita (si no, solo lectura). */
    #[Locked]
    public bool $puedeEditar = false;

    /** @var array<int, array<string, mixed>> tipo_espacio_id => campos de la fila ya capturada (+ quitar) */
    public array $edicionEspacios = [];

    /** @var array<string, array<string, mixed>> categoria => campos del sanitario ya capturado (+ quitar) */
    public array $edicionSanitarios = [];

    /** @var array<int, array<string, mixed>> tipo_material_id => acervo ya capturado de la biblioteca */
    public array $edicionMateriales = [];

    public function mount(EscuelaNivel $escuelaNivel, InfraestructuraYaCapturada $yaCapturada): void
    {
        $this->escuelaNivel = $escuelaNivel;

        if ($this->redirigirSiNoAlcanzable($escuelaNivel, 'infraestructura')) {
            return;
        }

        $this->tiposCapturados = $yaCapturada->tiposCapturados($this->plantelId());
        $this->categoriasCapturadas = $yaCapturada->categoriasCapturadas($this->plantelId());

        $this->puedeEditar = Gate::allows('editarCompartido', $escuelaNivel);
        if ($this->puedeEditar) {
            $edicion = app(CatalogosInfraestructura::class)->edicionCapturada($this->plantelId());
            $this->edicionEspacios = $edicion['espacios'];
            $this->edicionSanitarios = $edicion['sanitarios'];
            $this->edicionMateriales = $edicion['materiales'];
        }

        $aulas = AulaNivel::where('escuela_nivel_id', $escuelaNivel->id)->first();
        if ($aulas !== null) {
            $this->numeroAulas = $aulas->numero_aulas;
            $this->superficieAulasM2 = $aulas->superficie_m2;
        }
    }

    /**
     * Límites = tipo de la columna en el DDL: SMALLINT para cantidades,
     * NUMERIC(10,2) para superficies de espacios y aulas, NUMERIC(8,2) para
     * sanitarios, INTEGER para el acervo de biblioteca.
     */
    protected function rules(): array
    {
        $bodegaId = $this->idTipoBodega();

        return [
            'numeroAulas' => ReglasCaptura::entero(ReglasCaptura::MAX_SMALLINT, min: 1, requerido: true),
            'superficieAulasM2' => ReglasCaptura::decimal(ReglasCaptura::MAX_NUMERIC_10_2, requerido: true),
            'espacios.*.cantidad' => ReglasCaptura::entero(ReglasCaptura::MAX_SMALLINT),
            'espacios.*.superficieM2' => ReglasCaptura::decimal(ReglasCaptura::MAX_NUMERIC_10_2),
            'espacios.*.capacidadPromedio' => ReglasCaptura::entero(ReglasCaptura::MAX_SMALLINT),
            'espacios.*.destinadoA' => ReglasCaptura::texto(max: 200),
            "espacios.{$bodegaId}.destinadoA" => ['nullable', 'in:limpieza,general,otro'],
            'espacios.*.ventilacionNatural' => ['nullable', 'boolean'],
            'espacios.*.iluminacionNatural' => ['nullable', 'boolean'],
            'espacios.*.campoFutbolTipoSuperficie' => ReglasCaptura::texto(max: 50),
            'espacios.*.campoFutbolFormato' => ReglasCaptura::texto(max: 20),
            'sanitarios.*.cantidadRetretes' => ReglasCaptura::entero(ReglasCaptura::MAX_SMALLINT),
            'sanitarios.*.cantidadMingitorios' => ReglasCaptura::entero(ReglasCaptura::MAX_SMALLINT),
            'sanitarios.*.cantidadLavabos' => ReglasCaptura::entero(ReglasCaptura::MAX_SMALLINT),
            'sanitarios.*.superficieM2' => ReglasCaptura::decimal(ReglasCaptura::MAX_NUMERIC_8_2),
            'sanitarios.*.ventilacionNatural' => ['nullable', 'boolean'],
            'sanitarios.*.iluminacionNatural' => ['nullable', 'boolean'],
            'sanitarios.*.cantidadBacinicas' => ReglasCaptura::entero(ReglasCaptura::MAX_SMALLINT),
            'materialesBiblioteca.*.numeroTitulos' => ReglasCaptura::entero(ReglasCaptura::MAX_INTEGER),
            'materialesBiblioteca.*.numeroVolumenes' => ReglasCaptura::entero(ReglasCaptura::MAX_INTEGER),
        ];
    }

    public function guardar(RegistrarInfraestructuraNivel $registrarInfraestructura): void
    {
        // guardar() no escribe lo ya registrado: si se editó y no se guardó aparte, se perdería en silencio.
        if ($this->edicionPendiente()) {
            $mensaje = 'Tienes cambios sin guardar en lo ya registrado. Usa «Guardar cambios a lo ya registrado» antes de continuar.';
            $this->addError('edicionPendiente', $mensaje);
            $this->toast('No se guardó', $mensaje, 'error');

            return;
        }

        $this->validate();

        try {
            $registrarInfraestructura->ejecutar(
                $this->plantelId(),
                $this->escuelaNivel->id,
                new DatosInfraestructuraNivel(
                    espacios: $this->espaciosDeclarados(),
                    sanitarios: $this->sanitariosDeclarados(),
                    numeroAulas: (int) $this->numeroAulas,
                    superficieAulasM2: $this->superficieAulasM2 === null || $this->superficieAulasM2 === '' ? null : (float) $this->superficieAulasM2,
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
            $this->redirigirSiNoAlcanzable($this->escuelaNivel, 'infraestructura');

            return;
        }

        $this->redirigirTrasGuardar($this->escuelaNivel, 'tramite.paso3-mobiliario', 'Infraestructura guardada', 'Continúa con el mobiliario.');
    }

    public function guardarEdicion(EditarInfraestructuraCompartida $editarInfraestructura): void
    {
        // El caso de uso no autoriza: solo el dueño edita lo compartido (403 al responsable por nivel).
        Gate::authorize('editarCompartido', $this->escuelaNivel);

        $this->validate($this->conPrefijoEdicion($this->rules()), [], $this->conPrefijoEdicion((array) trans('validation.attributes')));

        try {
            $editarInfraestructura->ejecutar(
                $this->plantelId(),
                $this->escuelaNivel->id,
                $this->datosEdicion($this->edicionEspacios, $this->edicionSanitarios, $this->edicionMateriales),
            );
        } catch (DatosInvalidos $e) {
            foreach ($e->errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }
            $this->toastDatosInvalidos();

            return;
        } catch (PrecondicionIncumplida $e) {
            // WS-7a §5.4: otro trámite del plantel ya se envió (o, con bloqueo, se pidió quitar).
            $this->addError('plantel', $e->getMessage());
            $this->toast('No se guardó', $e->getMessage(), 'error');

            return;
        }

        // Recarga la pantalla: lo quitado vuelve a ofrecerse en «por capturar».
        $this->toastAlRedirigir('Infraestructura actualizada', 'Los cambios a lo ya capturado se guardaron.');
        $this->redirectRoute('tramite.paso3-infraestructura', ['escuelaNivel' => $this->escuelaNivel->id]);
    }

    /**
     * Arma la edición de lo ya capturado. Sirve también para comparar el formulario con lo
     * guardado: misma normalización en ambos lados (casts, nulos, casillas de quitar).
     *
     * @param  array<int|string, array<string, mixed>>  $espacios
     * @param  array<int|string, array<string, mixed>>  $sanitarios
     * @param  array<int|string, array<string, mixed>>  $materiales
     */
    private function datosEdicion(array $espacios, array $sanitarios, array $materiales): DatosEdicionInfraestructura
    {
        $filasEspacios = $quitarEspacios = $filasSanitarios = $quitarSanitarios = [];

        foreach ($this->espaciosCapturados() as $capturado) {
            $tipo = $capturado->tipoEspacio;
            $entrada = $espacios[$tipo->id] ?? [];

            if (! empty($entrada['quitar'])) {
                $quitarEspacios[] = (int) $tipo->id;

                continue;
            }

            // La edición reemplaza la fila entera: se reenvía todo, hijos incluidos.
            $filasEspacios[] = $this->filaEspacio($tipo, $entrada, $tipo->permite_material_biblioteca ? $this->materialesDeclarados($materiales) : []);
        }

        foreach ($this->categoriasCapturadas as $categoria) {
            $entrada = $sanitarios[$categoria] ?? [];

            if (! empty($entrada['quitar'])) {
                $quitarSanitarios[] = $categoria;

                continue;
            }

            $filasSanitarios[] = $this->filaSanitario($categoria, $entrada);
        }

        return new DatosEdicionInfraestructura($filasEspacios, $filasSanitarios, $quitarEspacios, $quitarSanitarios);
    }

    /** ¿El dueño cambió algo en «lo ya registrado» sin guardarlo con guardarEdicion()? */
    private function edicionPendiente(): bool
    {
        if (! $this->puedeEditar) {
            return false;
        }

        $guardado = app(CatalogosInfraestructura::class)->edicionCapturada($this->plantelId());

        return (array) $this->datosEdicion($this->edicionEspacios, $this->edicionSanitarios, $this->edicionMateriales)
            !== (array) $this->datosEdicion($guardado['espacios'], $guardado['sanitarios'], $guardado['materiales']);
    }

    /**
     * Mismas reglas (o nombres de atributo) que el formulario «por capturar», con las claves edicion*.
     *
     * @param  array<string, mixed>  $porClave  claves espacios.* / sanitarios.* / materialesBiblioteca.*
     * @return array<string, mixed>
     */
    private function conPrefijoEdicion(array $porClave): array
    {
        $prefijos = ['espacios.' => 'edicionEspacios.', 'sanitarios.' => 'edicionSanitarios.', 'materialesBiblioteca.' => 'edicionMateriales.'];
        $edicion = [];

        foreach ($porClave as $clave => $valor) {
            foreach ($prefijos as $de => $a) {
                if (str_starts_with((string) $clave, $de)) {
                    $edicion[$a.substr((string) $clave, strlen($de))] = $valor;
                }
            }
        }

        return $edicion;
    }

    /** Tipos de espacio aplicables al nivel en curso y aún no capturados por el plantel, en orden de captura. */
    public function tiposAplicables(): Collection
    {
        return TipoEspacio::query()
            ->join('niveles_tipos_espacios', 'niveles_tipos_espacios.tipo_espacio_id', '=', 'tipos_espacios.id')
            ->where('niveles_tipos_espacios.nivel_educativo_id', $this->escuelaNivel->nivel_educativo_id)
            ->whereNotIn('tipos_espacios.id', $this->tiposCapturados)
            ->orderBy('tipos_espacios.categoria')
            ->orderBy('tipos_espacios.nombre')
            ->select('tipos_espacios.*')
            ->get();
    }

    /**
     * Categorías de sanitario aplicables al nivel en curso y aún no capturadas por el plantel.
     *
     * @return list<string>
     */
    public function categoriasSanitarios(): array
    {
        $aplicables = app(CategoriasSanitariosPorNivel::class)->paraNivel($this->escuelaNivel->nivelEducativo->clave);

        return array_values(array_diff($aplicables, $this->categoriasCapturadas));
    }

    /** Lo que el plantel ya capturó (en este nivel o en otro), para mostrarlo de solo lectura. */
    public function espaciosCapturados(): Collection
    {
        return InstalacionEspacio::with('tipoEspacio')
            ->where('plantel_id', $this->plantelId())
            ->get();
    }

    public function sanitariosCapturados(): Collection
    {
        return app(CatalogosInfraestructura::class)->sanitariosCapturados($this->plantelId());
    }

    public function materialesDisponibles(): Collection
    {
        return app(CatalogosInfraestructura::class)->materialesBibliotecaDisponibles();
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
            // WS-2 item 1: si el "¿trae dato?" se decidiera aquí Y en
            // FilaSignificativa::espacio(), las dos copias podían divergir
            // (como pasó con campoFutbol, que esta regla contaba pero la del
            // caso de uso ignoraba). Ahora se construye la entrada para todo
            // tipo aplicable y el caso de uso decide solo, con una única regla.
            $declarados[] = $this->filaEspacio(
                $tipo,
                $this->espacios[$tipo->id] ?? [],
                $tipo->permite_material_biblioteca ? $this->materialesDeclarados($this->materialesBiblioteca) : [],
            );
        }

        return $declarados;
    }

    /**
     * Fila de espacio con la forma de DatosInfraestructuraNivel y DatosEdicionInfraestructura.
     *
     * @param  array<string, mixed>  $entrada
     * @param  list<array<string, mixed>>  $materiales
     * @return array<string, mixed>
     */
    private function filaEspacio(TipoEspacio $tipo, array $entrada, array $materiales): array
    {
        $cantidad = $entrada['cantidad'] ?? null;

        return [
            'tipoEspacioId' => (int) $tipo->id,
            'cantidad' => ($cantidad === null || $cantidad === '') ? null : (int) $cantidad,
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
            'materialesBiblioteca' => $materiales,
        ];
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $fuente  tipo_material_id => titulos/volumenes
     * @return list<array<string, mixed>>
     */
    private function materialesDeclarados(array $fuente): array
    {
        $declarados = [];
        $idsValidos = $this->materialesDisponibles()->pluck('id')->all();

        foreach ($fuente as $tipoMaterialId => $entrada) {
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
            // WS-2 item 1 / Minor 8: misma razón que espaciosDeclarados() —
            // FilaSignificativa::sanitario() es la única fuente de "¿trae dato?".
            $declarados[] = $this->filaSanitario($categoria, $this->sanitarios[$categoria] ?? []);
        }

        return $declarados;
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @return array<string, mixed>
     */
    private function filaSanitario(string $categoria, array $entrada): array
    {
        return [
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

    private function numeroONull(mixed $valor): ?float
    {
        return ($valor === null || $valor === '') ? null : (float) $valor;
    }

    public function render(RegistroCompartidoBloqueado $registroCompartidoBloqueado)
    {
        $bloqueado = $this->puedeEditar && $registroCompartidoBloqueado->bloqueado($this->plantelId(), $this->escuelaNivel->id);

        return view('livewire.tramite.paso3.infraestructura-nivel', [
            'bloqueado' => $bloqueado,
            // Con bloqueo, lo ya guardado (no nulo) queda de solo lectura: el caso de uso solo completa nulos.
            'guardado' => $bloqueado ? app(CatalogosInfraestructura::class)->edicionCapturada($this->plantelId()) : null,
            'encabezado' => ResumenTramite::encabezado('infraestructura', $this->escuelaNivel->nivelEducativo),
            'tipos' => $this->tiposAplicables(),
            'categorias' => $this->categoriasSanitarios(),
            'materiales' => $this->materialesDisponibles(),
            'espaciosCapturados' => $this->espaciosCapturados(),
            'sanitariosCapturados' => $this->sanitariosCapturados(),
        ])->layoutData(['escuelaId' => $this->escuelaNivel->escuela_id, 'escuelaNivelId' => $this->escuelaNivel->id, 'seccionActual' => 'infraestructura']);
    }
}
