<?php

namespace App\Livewire\Tramite\Paso3;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\CurriculumProyectoCapturada;
use App\Application\ProyectoEducativo\DTO\DatosCurriculumProyecto;
use App\Application\ProyectoEducativo\DTO\DatosEgresoProyecto;
use App\Application\ProyectoEducativo\DTO\DatosEnfoqueProyecto;
use App\Application\ProyectoEducativo\DTO\DatosEvaluacionProyecto;
use App\Application\ProyectoEducativo\DTO\DatosFilosofiaProyecto;
use App\Application\ProyectoEducativo\DTO\DatosFormacionContinuaProyecto;
use App\Application\ProyectoEducativo\DTO\DatosFormacionInicialProyecto;
use App\Application\ProyectoEducativo\DTO\DatosIngresoProyecto;
use App\Application\ProyectoEducativo\DTO\DatosObjetivosProyecto;
use App\Application\ProyectoEducativo\DTO\DatosPaecProyecto;
use App\Application\ProyectoEducativo\DTO\DatosPerfilDocenteProyecto;
use App\Application\ProyectoEducativo\DTO\DatosPresentacionProyecto;
use App\Application\ProyectoEducativo\EgresoProyectoCapturada;
use App\Application\ProyectoEducativo\EnfoqueProyectoCapturada;
use App\Application\ProyectoEducativo\EvaluacionProyectoCapturada;
use App\Application\ProyectoEducativo\FilosofiaProyectoCapturada;
use App\Application\ProyectoEducativo\FormacionContinuaProyectoCapturada;
use App\Application\ProyectoEducativo\FormacionInicialProyectoCapturada;
use App\Application\ProyectoEducativo\GuardarCurriculumProyecto;
use App\Application\ProyectoEducativo\GuardarEgresoProyecto;
use App\Application\ProyectoEducativo\GuardarEnfoqueProyecto;
use App\Application\ProyectoEducativo\GuardarEvaluacionProyecto;
use App\Application\ProyectoEducativo\GuardarFilosofiaProyecto;
use App\Application\ProyectoEducativo\GuardarFormacionContinuaProyecto;
use App\Application\ProyectoEducativo\GuardarFormacionInicialProyecto;
use App\Application\ProyectoEducativo\GuardarIngresoProyecto;
use App\Application\ProyectoEducativo\GuardarObjetivosProyecto;
use App\Application\ProyectoEducativo\GuardarPaecProyecto;
use App\Application\ProyectoEducativo\GuardarPerfilDocenteProyecto;
use App\Application\ProyectoEducativo\GuardarPresentacionProyecto;
use App\Application\ProyectoEducativo\IngresoProyectoCapturada;
use App\Application\ProyectoEducativo\ObjetivosProyectoCapturada;
use App\Application\ProyectoEducativo\PaecProyectoCapturada;
use App\Application\ProyectoEducativo\PerfilDocenteProyectoCapturada;
use App\Application\ProyectoEducativo\PresentacionProyectoCapturada;
use App\Application\ProyectoEducativo\ReglasCurriculumProyecto;
use App\Application\ProyectoEducativo\ReglasEnfoqueProyecto;
use App\Application\ProyectoEducativo\ReglasEvaluacionProyecto;
use App\Application\ProyectoEducativo\ReglasFormacionProyecto;
use App\Application\ProyectoEducativo\ReglasIngresoProyecto;
use App\Application\ProyectoEducativo\ReglasPresentacionProyecto;
use App\Application\ProyectoEducativo\RevisarPdfProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\ResumenTramite;
use App\Application\Tramite\TramiteEditable;
use App\Livewire\Concerns\NotificaToast;
use App\Livewire\Tramite\Paso3\Concerns\CompuertaPaso3;
use App\Models\EscuelaNivel;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Una sección de la Presentación del Proyecto Educativo. La primera (presentación del proyecto académico) es un
 * formulario guardable como borrador; las demás siguen con el marcador "Contenido por definir". Los campos del
 * formulario se llaman igual que las columnas (y que las claves de error del caso de uso).
 */
#[Layout('layouts.tramite')]
class ProyectoEducativoSeccion extends Component
{
    use CompuertaPaso3;
    use NotificaToast;
    use WithFileUploads;

    #[Locked]
    public EscuelaNivel $escuelaNivel;

    #[Locked]
    public string $seccion = '';

    /** Paso del formulario de la sección 1 (1 a 3): solo lo cambian siguiente()/anterior(), nunca el cliente. */
    #[Locked]
    public int $paso = 1;

    public string $fecha_entrega = '';

    public string $nombre_institucion = '';

    public string $fecha_apertura = '';

    public string $domicilio_completo = '';

    public string $representante_legal = '';

    public string $correo = '';

    public string $telefono = '';

    public string $celular = '';

    public string $pagina_web = '';

    public string $modalidad = '';

    public string $ciclo_escolar = '';

    public string $ciclo_escolar_otro = '';

    public string $duracion = '';

    public string $duracion_otro = '';

    public string $sintesis_factibilidad = '';

    // Sección 2 (Filosofía Institucional).
    public string $mision = '';

    public string $vision = '';

    public string $valores = '';

    // Sección 3 (Objetivos Institucionales).
    public string $objetivo_general = '';

    public string $objetivos_especificos = '';

    // Sección 4 (Enfoque Pedagógico): un campo por pantalla; `pantalla` (1 a N) solo lo cambian las acciones del servidor.
    #[Locked]
    public int $pantalla = 1;

    public string $propuesta_global = '';

    public string $apropiacion_enfoque = '';

    /** @var array<array-key, string> llega del navegador: puede no ser una lista; pantallas() la normaliza */
    public array $estrategias_centrales = ['', ''];

    /** @var array<array-key, string> */
    public array $actividades_sustantivas = ['', ''];

    // Sección 5 (Modelo de Evaluación): un bloque por pantalla (ReglasEvaluacionProyecto::BLOQUES); `bloque` solo lo cambian las acciones del servidor.
    #[Locked]
    public int $bloque = 1;

    public string $evaluacion_diagnostica = '';

    public string $evaluacion_formativa = '';

    public string $evaluacion_sumativa = '';

    public string $heteroevaluacion = '';

    public string $coevaluacion = '';

    public string $autoevaluacion = '';

    public string $elementos_porcentajes = '';

    // Sección 6 (PAEC): un solo bloque. Los dos archivos son subidas temporales de Livewire (null = conservar el guardado).
    public string $diagnostico = '';

    public mixed $hoja_ruta = null;

    public mixed $planeacion = null;

    /** @var array<string, string|int|null> nombre y páginas de los PDF ya guardados (nunca las rutas de disco) */
    public array $paecGuardado = [];

    // Sección 7 (Currículum Ampliado): un ámbito por pantalla (ReglasCurriculumProyecto::BLOQUES), con el mismo `bloque`; la respuesta es 'si' o 'no'.
    public string $practica_ciudadana_trabaja = '';

    public string $practica_ciudadana_justificacion = '';

    public string $salud_trabaja = '';

    public string $salud_justificacion = '';

    public string $actividad_fisica_trabaja = '';

    public string $actividad_fisica_justificacion = '';

    public string $sexualidad_genero_trabaja = '';

    public string $sexualidad_genero_justificacion = '';

    public string $artistico_cultural_trabaja = '';

    public string $artistico_cultural_justificacion = '';

    // Sección 8 (Perfil de Ingreso).
    public string $perfil_ingreso = '';

    public string $mecanismo_ingreso = '';

    public string $costo_usuario = '';

    public string $instrumentos_evaluacion = '';

    // Sección 9 (Perfil de Egreso).
    public string $perfil_egreso = '';

    // Sección 11 (Formación Docente), bloque inicial: la política y la lista de acciones. `pantalla` (1 = política, 2 en adelante = una acción por pantalla) solo la cambian las acciones del servidor.
    public string $politica_inicial = '';

    /** @var array<array-key, mixed> llega del navegador: puede no ser una lista de acciones completas; normalizarAcciones() la corrige */
    public array $acciones = [];

    // Bloque continuo: la política, el PDF de planeación (subida temporal de Livewire; null = conservar el guardado) y sus acciones.
    public string $politica_continua = '';

    public mixed $planeacion_continua = null;

    /** @var array<array-key, mixed> como */
    public array $acciones_continua = [];

    /** @var array<string, string|int|null> nombre y páginas del PDF de planeación ya guardado (nunca la ruta de disco) */
    public array $formacionGuardado = [];

    // Sección 10 (Perfil Docente).
    public string $habilidades_virtudes = '';

    public function mount(EscuelaNivel $escuelaNivel, string $seccion): void
    {
        $this->escuelaNivel = $escuelaNivel;
        $this->seccion = $seccion;

        abort_unless(SeccionesProyectoEducativo::aplicaA($escuelaNivel->nivelEducativo->clave), 404);
        abort_unless(app(EstadoPaso3::class)->tieneEnCatalogo($escuelaNivel->id, SeccionesProyectoEducativo::CLAVE_PASO), 404);
        abort_unless(SeccionesProyectoEducativo::existe($seccion), 404);

        if ($this->redirigirSiNoAlcanzable($escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO)) {
            return;
        }

        if ($this->esPresentacion()) {
            $this->cargar();
            $this->paso = app(PresentacionProyectoCapturada::class)->pasoInicial($escuelaNivel->id);
        } elseif ($this->esTexto()) {
            $this->cargarTexto();
        } elseif ($this->esEnfoque()) {
            $this->cargarEnfoque();
        } elseif ($this->esPaec()) {
            $this->cargarPaec();
        } elseif ($this->esFormacion()) {
            $this->cargarFormacion();
        }
    }

    private function esFormacion(): bool
    {
        return $this->seccion === SeccionesProyectoEducativo::formacion();
    }

    private function cargarFormacion(): void
    {
        $this->cargarBloqueInicial();
        $this->cargarBloqueContinuo();
    }

    private function cargarBloqueInicial(): void
    {
        $guardado = app(FormacionInicialProyectoCapturada::class)->datos($this->escuelaNivel->id);
        $this->politica_inicial = (string) $guardado['politica_inicial'];
        $this->acciones = $this->accionesComoTexto($guardado['acciones']);
        $this->normalizarAcciones();
    }

    private function cargarBloqueContinuo(): void
    {
        $guardado = app(FormacionContinuaProyectoCapturada::class)->datos($this->escuelaNivel->id);
        $this->politica_continua = (string) $guardado['politica_continua'];
        $this->acciones_continua = $this->accionesComoTexto($guardado['acciones_continua']);
        $this->formacionGuardado = ['planeacion_nombre' => $guardado['planeacion_nombre'], 'planeacion_paginas' => $guardado['planeacion_paginas']];
        $this->normalizarAcciones();
    }

    /**
     * @param  list<array<string, mixed>>  $acciones
     * @return list<array<string, mixed>>
     */
    private function accionesComoTexto(array $acciones): array
    {
        return array_map(function (array $accion): array {
            foreach ($accion as $campo => $valor) {
                $accion[$campo] = $campo === 'dias' ? (array) $valor : (string) $valor;
            }

            return $accion;
        }, $acciones);
    }

    /** Las listas llegan del navegador: se vuelven listas de acciones con todos sus campos, y nunca quedan vacías (arrancan con una). */
    private function normalizarAcciones(): void
    {
        foreach (['acciones', 'acciones_continua'] as $lista) {
            $this->{$lista} = array_values(array_map(
                fn (mixed $accion) => is_array($accion) ? array_merge(ReglasFormacionProyecto::accionVacia(), $accion) : ReglasFormacionProyecto::accionVacia(),
                $this->{$lista},
            ));
            if ($this->{$lista} === []) {
                $this->{$lista} = [ReglasFormacionProyecto::accionVacia()];
            }
        }
    }

    /** @return string nombre de la propiedad con las acciones de ese bloque */
    private function listaDeAcciones(string $bloque): string
    {
        return $bloque === 'inicial' ? 'acciones' : 'acciones_continua';
    }

    /**
     * Las pantallas de la sección 11, en orden: política inicial, una por acción inicial, política continua, planeación y una
     * por acción continua.
     *
     * @return list<array{bloque: string, tipo: string, indice: int|null}>
     */
    private function pantallasFormacion(): array
    {
        $this->normalizarAcciones();

        $pantallas = [['bloque' => 'inicial', 'tipo' => 'politica', 'indice' => null]];
        foreach (array_keys($this->acciones) as $i) {
            $pantallas[] = ['bloque' => 'inicial', 'tipo' => 'accion', 'indice' => $i];
        }
        $pantallas[] = ['bloque' => 'continua', 'tipo' => 'politica', 'indice' => null];
        $pantallas[] = ['bloque' => 'continua', 'tipo' => 'planeacion', 'indice' => null];
        foreach (array_keys($this->acciones_continua) as $i) {
            $pantallas[] = ['bloque' => 'continua', 'tipo' => 'accion', 'indice' => $i];
        }

        return $pantallas;
    }

    /** @return array{int, array{bloque: string, tipo: string, indice: int|null}, list<array{bloque: string, tipo: string, indice: int|null}>} número, pantalla actual y todas */
    private function pantallaFormacion(): array
    {
        $pantallas = $this->pantallasFormacion();
        $numero = min(max($this->pantalla, 1), count($pantallas));

        return [$numero, $pantallas[$numero - 1], $pantallas];
    }

    private function numeroDePantalla(string $bloque, string $tipo, ?int $indice): int
    {
        foreach ($this->pantallasFormacion() as $posicion => $pantalla) {
            if ($pantalla === ['bloque' => $bloque, 'tipo' => $tipo, 'indice' => $indice]) {
                return $posicion + 1;
            }
        }

        return 1;
    }

    /** Número de la pantalla donde se muestra el error de esa clave (null si no tiene una, como el número de acciones). */
    private function pantallaDeError(string $clave): ?int
    {
        return match (true) {
            $clave === 'politica_inicial' => $this->numeroDePantalla('inicial', 'politica', null),
            $clave === 'politica_continua' => $this->numeroDePantalla('continua', 'politica', null),
            $clave === 'planeacion_continua' => $this->numeroDePantalla('continua', 'planeacion', null),
            (bool) preg_match('/^acciones_continua\.(\d+)\./', $clave, $m) => $this->numeroDePantalla('continua', 'accion', (int) $m[1]),
            (bool) preg_match('/^acciones\.(\d+)\./', $clave, $m) => $this->numeroDePantalla('inicial', 'accion', (int) $m[1]),
            default => null,
        };
    }

    private function planeacionSubida(): ?UploadedFile
    {
        return $this->planeacion_continua instanceof UploadedFile ? $this->planeacion_continua : null;
    }

    /** @return array<string, string> errores del bloque (sin revisar el PDF: eso lo hace la pantalla de la planeación y el guardado) */
    private function erroresDelBloque(string $bloque): array
    {
        if ($bloque === 'inicial') {
            return ReglasFormacionProyecto::errores(ReglasFormacionProyecto::normalizar($this->politica_inicial, $this->acciones));
        }

        return ReglasFormacionProyecto::errores(ReglasFormacionProyecto::normalizar($this->politica_continua, $this->acciones_continua), 'politica_continua', 'acciones_continua');
    }

    /** @return array<string, string> */
    private function erroresDePlaneacion(): array
    {
        $archivo = $this->planeacionSubida();
        if ($archivo === null) {
            return ($this->formacionGuardado['planeacion_nombre'] ?? null) === null ? ['planeacion_continua' => 'Este campo es obligatorio.'] : [];
        }

        try {
            app(RevisarPdfProyecto::class)->revisar($archivo);
        } catch (RuntimeException $e) {
            return ['planeacion_continua' => $e->getMessage()];
        }

        return [];
    }

    /** Sección 11: valida solo lo de la pantalla actual (una política, la planeación o una acción); nada se escribe; con error se queda. */
    public function siguienteFormacion(): void
    {
        abort_unless($this->esFormacion(), 404);
        $this->resetErrorBag();

        [$numero, $actual, $pantallas] = $this->pantallaFormacion();
        $errores = $actual['tipo'] === 'planeacion' ? $this->erroresDePlaneacion() : array_filter(
            $this->erroresDelBloque($actual['bloque']),
            fn (string $clave) => $actual['tipo'] === 'politica'
                ? $clave === 'politica_'.$actual['bloque']
                : str_starts_with($clave, $this->listaDeAcciones($actual['bloque']).'.'.$actual['indice'].'.'),
            ARRAY_FILTER_USE_KEY,
        );
        if ($errores !== []) {
            foreach ($errores as $clave => $mensaje) {
                $this->addError($clave, $mensaje);
            }
            $this->toastDatosInvalidos();

            return;
        }

        $this->pantalla = min($numero + 1, count($pantallas));
    }

    /** Sección 11: vuelve una pantalla sin validar ni perder lo tecleado. */
    public function anteriorFormacion(): void
    {
        abort_unless($this->esFormacion(), 404);
        $this->resetErrorBag();
        [$numero] = $this->pantallaFormacion();
        $this->pantalla = max(1, $numero - 1);
    }

    /** Sección 11: suma una acción en blanco al bloque de la pantalla actual (hasta el máximo) y pasa a ella. */
    public function agregarAccion(): void
    {
        abort_unless($this->esFormacion(), 404);
        [, $actual] = $this->pantallaFormacion();
        $lista = $this->listaDeAcciones($actual['bloque']);
        if ($actual['tipo'] !== 'accion' || count($this->{$lista}) >= ReglasFormacionProyecto::MAX_ACCIONES) {
            return;
        }

        $this->resetErrorBag();
        $this->{$lista}[] = ReglasFormacionProyecto::accionVacia();
        $this->pantalla = $this->numeroDePantalla($actual['bloque'], 'accion', count($this->{$lista}) - 1);
    }

    /** Sección 11: quita la acción de la pantalla actual (el bloque conserva al menos una). */
    public function quitarAccion(): void
    {
        abort_unless($this->esFormacion(), 404);
        [, $actual] = $this->pantallaFormacion();
        $lista = $this->listaDeAcciones($actual['bloque']);
        if ($actual['tipo'] !== 'accion' || count($this->{$lista}) <= 1) {
            return;
        }

        $this->resetErrorBag();
        unset($this->{$lista}[$actual['indice']]);
        $this->{$lista} = array_values($this->{$lista});
        $this->pantalla = $this->numeroDePantalla($actual['bloque'], 'accion', min((int) $actual['indice'], count($this->{$lista}) - 1));
    }

    /** "Guardar" de la sección 11: guarda el bloque de la pantalla actual, todo o nada; con errores no escribe y lleva a la primera pantalla con error. */
    private function guardarFormacion(): void
    {
        $this->resetErrorBag();
        [$numero, $actual] = $this->pantallaFormacion();
        $bloque = $actual['bloque'];

        try {
            if ($bloque === 'inicial') {
                app(GuardarFormacionInicialProyecto::class)->ejecutar($this->escuelaNivel->id, new DatosFormacionInicialProyecto($this->politica_inicial, $this->acciones));
            } else {
                app(GuardarFormacionContinuaProyecto::class)->ejecutar($this->escuelaNivel->id, new DatosFormacionContinuaProyecto($this->politica_continua, $this->planeacionSubida(), $this->acciones_continua));
            }
        } catch (DatosInvalidos $e) {
            $primera = null;
            foreach ($e->errores as $clave => $mensaje) {
                $this->addError($clave, $mensaje);
                $pantalla = $this->pantallaDeError($clave);
                if ($pantalla !== null && ($primera === null || $pantalla < $primera)) {
                    $primera = $pantalla;
                }
            }
            $this->pantalla = $primera ?? $numero;
            $this->toastDatosInvalidos();

            return;
        } catch (PrecondicionIncumplida $e) {
            if ($e->etapaFaltante === TramiteEditable::ENVIADO) {
                $this->toast('No se guardó', $e->getMessage(), 'error');
            } else {
                $this->redirigirSiNoAlcanzable($this->escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);
            }

            return;
        }

        if ($bloque === 'inicial') {
            $this->cargarBloqueInicial();
            $this->pantalla = $this->numeroDePantalla('continua', 'politica', null);
        } else {
            $this->planeacion_continua = null;
            $this->cargarBloqueContinuo();
        }

        $completa = app(FormacionInicialProyectoCapturada::class)->completa($this->escuelaNivel->id)
            && app(FormacionContinuaProyectoCapturada::class)->completa($this->escuelaNivel->id);
        $this->toast(
            $completa ? 'Sección completa' : 'Bloque guardado',
            $completa ? 'Puedes seguir con la siguiente sección.' : ($bloque === 'inicial' ? 'Continúa con la formación docente continua.' : 'Falta guardar la formación docente inicial.'),
        );
    }

    /** Lo que la vista de la sección 11 necesita para pintar la pantalla actual. @return array<string, mixed> */
    private function datosPantallaFormacion(): array
    {
        [$numero, $actual, $pantallas] = $this->pantallaFormacion();
        $lista = $this->listaDeAcciones($actual['bloque']);
        $cuantas = count($this->{$lista});
        $ultima = $actual['bloque'] === 'inicial' ? $numero === 1 + $cuantas : $numero === count($pantallas);

        return [
            'pantalla' => $numero,
            'total' => count($pantallas),
            'bloque' => $actual['bloque'],
            'tipo' => $actual['tipo'],
            'accion' => $actual['indice'],
            'lista' => $lista,
            'acciones' => $cuantas,
            'ultima' => $ultima,
            'continuar' => $ultima && $actual['bloque'] === 'inicial',
            'puedeAgregar' => $cuantas < ReglasFormacionProyecto::MAX_ACCIONES,
            'puedeQuitar' => $cuantas > 1,
        ];
    }

    /** Sección 4: valida solo el campo de la pantalla actual (nada se escribe) y avanza; con error se queda. */
    public function siguienteEnfoque(): void
    {
        abort_unless($this->esEnfoque(), 404);
        $this->resetErrorBag();

        $clave = $this->claveActual();
        $errores = ReglasEnfoqueProyecto::errores($this->only(DatosEnfoqueProyecto::campos()));
        if (isset($errores[$clave])) {
            $this->addError($clave, $errores[$clave]);
            $this->toastDatosInvalidos();

            return;
        }

        $this->pantalla = min($this->pantalla + 1, count($this->pantallas()));
    }

    /** Sección 4: vuelve una pantalla sin validar ni perder lo tecleado. */
    public function anteriorEnfoque(): void
    {
        abort_unless($this->esEnfoque(), 404);
        $this->resetErrorBag();
        $this->pantalla = max(1, $this->pantalla - 1);
    }

    /** Sección 4: suma un ejemplo vacío a la lista de la pantalla actual (hasta el máximo) y pasa a él. */
    public function agregarEjemplo(): void
    {
        abort_unless($this->esEnfoque(), 404);
        [$lista] = $this->ubicarEjemplo();
        if ($lista === null || count($this->{$lista}) >= ReglasEnfoqueProyecto::EJEMPLOS[1]) {
            return;
        }

        $this->resetErrorBag();
        $this->{$lista}[] = '';
        $this->pantalla = array_search($lista.'.'.(count($this->{$lista}) - 1), $this->pantallas(), true) + 1;
    }

    /** Sección 4: quita el ejemplo de la pantalla actual (hasta el mínimo). */
    public function quitarEjemplo(): void
    {
        abort_unless($this->esEnfoque(), 404);
        [$lista, $indice] = $this->ubicarEjemplo();
        if ($lista === null || count($this->{$lista}) <= ReglasEnfoqueProyecto::EJEMPLOS[0]) {
            return;
        }

        $this->resetErrorBag();
        unset($this->{$lista}[$indice]);
        $this->{$lista} = array_values($this->{$lista});
        $this->pantalla = min($this->pantalla, count($this->pantallas()));
    }

    /** Las listas llegan del navegador: se vuelven lista (0..n-1) para que cada pantalla tenga su campo. @return list<string> */
    private function pantallas(): array
    {
        $pantallas = ['propuesta_global', 'apropiacion_enfoque'];
        foreach (array_keys(ReglasEnfoqueProyecto::LISTAS) as $lista) {
            $this->{$lista} = array_values($this->{$lista});
            foreach (array_keys($this->{$lista}) as $i) {
                $pantallas[] = "{$lista}.{$i}";
            }
        }

        return $pantallas;
    }

    private function claveActual(): string
    {
        $pantallas = $this->pantallas();

        return $pantallas[min(max($this->pantalla, 1), count($pantallas)) - 1];
    }

    /** @return array{string|null, int} lista e índice del ejemplo de la pantalla actual (null si no es un ejemplo) */
    private function ubicarEjemplo(): array
    {
        $partes = explode('.', $this->claveActual());

        return count($partes) === 2 ? [$partes[0], (int) $partes[1]] : [null, 0];
    }

    /** Sección 5: valida solo los campos del bloque actual (nada se escribe) y avanza; con error se queda. */
    public function siguienteEvaluacion(): void
    {
        abort_unless($this->esEvaluacion(), 404);
        $this->resetErrorBag();

        $errores = array_intersect_key(
            ReglasEvaluacionProyecto::errores($this->only(DatosEvaluacionProyecto::campos())),
            array_flip(ReglasEvaluacionProyecto::BLOQUES[$this->bloque]['campos']),
        );
        if ($errores !== []) {
            foreach ($errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }
            $this->toastDatosInvalidos();

            return;
        }

        $this->bloque = min($this->bloque + 1, count(ReglasEvaluacionProyecto::BLOQUES));
    }

    /** Sección 5: vuelve un bloque sin validar ni perder lo tecleado. */
    public function anteriorEvaluacion(): void
    {
        abort_unless($this->esEvaluacion(), 404);
        $this->resetErrorBag();
        $this->bloque = max(1, $this->bloque - 1);
    }

    /** Sección 7: valida solo la respuesta y la justificación del ámbito actual (nada se escribe) y avanza; con error se queda. */
    public function siguienteCurriculum(): void
    {
        abort_unless($this->esCurriculum(), 404);
        $this->resetErrorBag();

        $errores = array_intersect_key(
            ReglasCurriculumProyecto::errores($this->only(DatosCurriculumProyecto::campos())),
            array_flip(ReglasCurriculumProyecto::BLOQUES[$this->bloque]['campos']),
        );
        if ($errores !== []) {
            foreach ($errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }
            $this->toastDatosInvalidos();

            return;
        }

        $this->bloque = min($this->bloque + 1, count(ReglasCurriculumProyecto::BLOQUES));
    }

    /** Sección 7: vuelve un ámbito sin validar ni perder lo tecleado. */
    public function anteriorCurriculum(): void
    {
        abort_unless($this->esCurriculum(), 404);
        $this->resetErrorBag();
        $this->bloque = max(1, $this->bloque - 1);
    }

    /** Sección 8: valida solo los campos del bloque actual (nada se escribe) y avanza; con error se queda. */
    public function siguienteIngreso(): void
    {
        abort_unless($this->esIngreso(), 404);
        $this->resetErrorBag();

        $errores = array_intersect_key(
            ReglasIngresoProyecto::errores($this->only(DatosIngresoProyecto::campos())),
            array_flip(ReglasIngresoProyecto::BLOQUES[$this->bloque]['campos']),
        );
        if ($errores !== []) {
            foreach ($errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }
            $this->toastDatosInvalidos();

            return;
        }

        $this->bloque = min($this->bloque + 1, count(ReglasIngresoProyecto::BLOQUES));
    }

    /** Sección 8: vuelve un bloque sin validar ni perder lo tecleado. */
    public function anteriorIngreso(): void
    {
        abort_unless($this->esIngreso(), 404);
        $this->resetErrorBag();
        $this->bloque = max(1, $this->bloque - 1);
    }

    private function esIngreso(): bool
    {
        return $this->seccion === SeccionesProyectoEducativo::ingreso();
    }

    private function esCurriculum(): bool
    {
        return $this->seccion === SeccionesProyectoEducativo::curriculum();
    }

    /** Si el "Guardar" final falla, se muestra el primer bloque que tiene un error (secciones 5, 7 y 8). @param  list<string>  $campos */
    private function irAlPrimerBloqueConErrores(array $campos): void
    {
        $bloques = match (true) {
            $this->esCurriculum() => ReglasCurriculumProyecto::BLOQUES,
            $this->esIngreso() => ReglasIngresoProyecto::BLOQUES,
            default => ReglasEvaluacionProyecto::BLOQUES,
        };
        foreach ($bloques as $numero => $bloque) {
            if (array_intersect($campos, $bloque['campos']) !== []) {
                $this->bloque = $numero;

                return;
            }
        }
    }

    private function esEvaluacion(): bool
    {
        return $this->seccion === SeccionesProyectoEducativo::evaluacion();
    }

    private function esPaec(): bool
    {
        return $this->seccion === SeccionesProyectoEducativo::paec();
    }

    private function cargarPaec(): void
    {
        $guardado = app(PaecProyectoCapturada::class)->datos($this->escuelaNivel->id);
        $this->diagnostico = (string) $guardado['diagnostico'];
        unset($guardado['diagnostico']);
        $this->paecGuardado = $guardado;
    }

    /** "Guardar" de la sección 6: todo o nada; sin archivo nuevo se conserva el guardado. Un valor que no sea una subida cuenta como "sin archivo". */
    private function guardarPaec(): void
    {
        $this->resetErrorBag();
        $subida = fn (mixed $valor) => $valor instanceof UploadedFile ? $valor : null;

        try {
            app(GuardarPaecProyecto::class)->ejecutar($this->escuelaNivel->id, new DatosPaecProyecto($this->diagnostico, $subida($this->hoja_ruta), $subida($this->planeacion)));
        } catch (DatosInvalidos $e) {
            foreach ($e->errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }
            $this->toastDatosInvalidos();

            return;
        } catch (PrecondicionIncumplida $e) {
            if ($e->etapaFaltante === TramiteEditable::ENVIADO) {
                $this->toast('No se guardó', $e->getMessage(), 'error');
            } else {
                $this->redirigirSiNoAlcanzable($this->escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);
            }

            return;
        }

        $this->hoja_ruta = null;
        $this->planeacion = null;
        $this->cargarPaec();
        $this->toast('Sección completa', 'Puedes seguir con la siguiente sección.');
    }

    private function esEnfoque(): bool
    {
        return $this->seccion === SeccionesProyectoEducativo::enfoque();
    }

    private function cargarEnfoque(): void
    {
        foreach (app(EnfoqueProyectoCapturada::class)->datos($this->escuelaNivel->id) as $campo => $valor) {
            if (array_key_exists($campo, ReglasEnfoqueProyecto::LISTAS)) {
                // Sin nada guardado, la lista arranca con el mínimo de ejemplos vacíos.
                $this->{$campo} = is_array($valor) && $valor !== [] ? $valor : array_fill(0, ReglasEnfoqueProyecto::EJEMPLOS[0], '');
            } else {
                $this->{$campo} = (string) $valor;
            }
        }
    }

    /** "Guardar" de la sección 4: todo o nada; con errores no escribe y lleva a la primera pantalla con error. */
    private function guardarEnfoque(): void
    {
        $this->resetErrorBag();

        try {
            app(GuardarEnfoqueProyecto::class)->ejecutar($this->escuelaNivel->id, new DatosEnfoqueProyecto(...$this->only(DatosEnfoqueProyecto::campos())));
        } catch (DatosInvalidos $e) {
            foreach ($e->errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }
            foreach ($this->pantallas() as $numero => $clave) {
                if (isset($e->errores[$clave]) || isset($e->errores[explode('.', $clave)[0]])) {
                    $this->pantalla = $numero + 1;
                    break;
                }
            }
            $this->toastDatosInvalidos();

            return;
        } catch (PrecondicionIncumplida $e) {
            if ($e->etapaFaltante === TramiteEditable::ENVIADO) {
                $this->toast('No se guardó', $e->getMessage(), 'error');
            } else {
                $this->redirigirSiNoAlcanzable($this->escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);
            }

            return;
        }

        $this->cargarEnfoque();
        $this->toast('Sección completa', 'Puedes seguir con la siguiente sección.');
    }

    /** Valida y guarda el paso actual (borrador del resto) y avanza; con errores se queda en el paso. */
    public function siguiente(GuardarPresentacionProyecto $guardar): void
    {
        abort_unless($this->esPresentacion(), 404);

        if ($this->paso < $this->totalPasos() && $this->persistir($guardar)) {
            $this->paso++;
            $this->toast('Paso guardado', 'Continúa con el siguiente paso.');
        }
    }

    /** Vuelve un paso sin validar ni perder lo tecleado (el estado del componente lo conserva). */
    public function anterior(): void
    {
        abort_unless($this->esPresentacion(), 404);
        $this->resetErrorBag();
        $this->paso = max(1, $this->paso - 1);
    }

    /** "Guardar" del último paso. */
    public function guardar(GuardarPresentacionProyecto $guardar): void
    {
        if ($this->esTexto()) {
            $this->guardarTexto();

            return;
        }

        if ($this->esEnfoque()) {
            $this->guardarEnfoque();

            return;
        }

        if ($this->esPaec()) {
            $this->guardarPaec();

            return;
        }

        if ($this->esFormacion()) {
            $this->guardarFormacion();

            return;
        }

        abort_unless($this->esPresentacion(), 404);

        if ($this->paso === $this->totalPasos() && $this->persistir($guardar)) {
            $this->toast('Sección guardada', 'Puedes seguir con la siguiente sección.');
        }
    }

    /** Si el error cae en un paso que no se está pintando (el "Guardar" final exige todo), se muestra ese paso. */
    private function irAlPrimerPasoConErrores(array $campos): void
    {
        foreach (ReglasPresentacionProyecto::PASOS as $numero => $paso) {
            if (array_intersect($campos, $paso['campos']) !== []) {
                $this->paso = min($this->paso, $numero);

                return;
            }
        }
    }

    private function totalPasos(): int
    {
        return count(ReglasPresentacionProyecto::PASOS);
    }

    /** @return bool true si se guardó */
    private function persistir(GuardarPresentacionProyecto $guardar): bool
    {
        $this->resetErrorBag();

        try {
            $guardar->ejecutar($this->escuelaNivel->id, new DatosPresentacionProyecto(...$this->only(DatosPresentacionProyecto::campos())), $this->paso);
        } catch (DatosInvalidos $e) {
            foreach ($e->errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }
            $this->irAlPrimerPasoConErrores(array_keys($e->errores));
            $this->toastDatosInvalidos();

            return false;
        } catch (PrecondicionIncumplida $e) {
            if ($e->etapaFaltante === TramiteEditable::ENVIADO) {
                $this->toast('No se guardó', $e->getMessage(), 'error');
            } else {
                $this->redirigirSiNoAlcanzable($this->escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);
            }

            return false;
        }

        $this->cargar();

        return true;
    }

    /** Secciones de una sola página con textos de rango de palabras: [guardar, DTO, lectura]. */
    private function esTexto(): bool
    {
        return in_array($this->seccion, [SeccionesProyectoEducativo::filosofia(), SeccionesProyectoEducativo::objetivos(), SeccionesProyectoEducativo::evaluacion(), SeccionesProyectoEducativo::curriculum(), SeccionesProyectoEducativo::ingreso(), SeccionesProyectoEducativo::egreso(), SeccionesProyectoEducativo::perfilDocente()], true);
    }

    /** @return array{object, class-string, object} */
    private function seccionTexto(): array
    {
        return match ($this->seccion) {
            SeccionesProyectoEducativo::filosofia() => [app(GuardarFilosofiaProyecto::class), DatosFilosofiaProyecto::class, app(FilosofiaProyectoCapturada::class)],
            SeccionesProyectoEducativo::objetivos() => [app(GuardarObjetivosProyecto::class), DatosObjetivosProyecto::class, app(ObjetivosProyectoCapturada::class)],
            SeccionesProyectoEducativo::evaluacion() => [app(GuardarEvaluacionProyecto::class), DatosEvaluacionProyecto::class, app(EvaluacionProyectoCapturada::class)],
            SeccionesProyectoEducativo::curriculum() => [app(GuardarCurriculumProyecto::class), DatosCurriculumProyecto::class, app(CurriculumProyectoCapturada::class)],
            SeccionesProyectoEducativo::ingreso() => [app(GuardarIngresoProyecto::class), DatosIngresoProyecto::class, app(IngresoProyectoCapturada::class)],
            SeccionesProyectoEducativo::egreso() => [app(GuardarEgresoProyecto::class), DatosEgresoProyecto::class, app(EgresoProyectoCapturada::class)],
            SeccionesProyectoEducativo::perfilDocente() => [app(GuardarPerfilDocenteProyecto::class), DatosPerfilDocenteProyecto::class, app(PerfilDocenteProyectoCapturada::class)],
            default => abort(404),
        };
    }

    /** "Guardar" de una sección de una sola página: todo o nada (todas las reglas); con errores no escribe y los muestra todos. */
    private function guardarTexto(): void
    {
        [$accion, $dto] = $this->seccionTexto();
        $this->resetErrorBag();

        try {
            $accion->ejecutar($this->escuelaNivel->id, new $dto(...$this->only($dto::campos())));
        } catch (DatosInvalidos $e) {
            foreach ($e->errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }
            if ($this->esEvaluacion() || $this->esCurriculum() || $this->esIngreso()) {
                $this->irAlPrimerBloqueConErrores(array_keys($e->errores));
            }
            $this->toastDatosInvalidos();

            return;
        } catch (PrecondicionIncumplida $e) {
            if ($e->etapaFaltante === TramiteEditable::ENVIADO) {
                $this->toast('No se guardó', $e->getMessage(), 'error');
            } else {
                $this->redirigirSiNoAlcanzable($this->escuelaNivel, SeccionesProyectoEducativo::CLAVE_PASO);
            }

            return;
        }

        $this->cargarTexto();
        $this->toast('Sección completa', 'Puedes seguir con la siguiente sección.');
    }

    private function cargarTexto(): void
    {
        [, , $capturada] = $this->seccionTexto();
        foreach ($capturada->datos($this->escuelaNivel->id) as $campo => $valor) {
            $this->{$campo} = (string) $valor;
        }
    }

    private function esPresentacion(): bool
    {
        return $this->seccion === SeccionesProyectoEducativo::presentacion();
    }

    /** Vuelve a pintar lo guardado; los teléfonos se muestran como (442) 000-0000. */
    private function cargar(): void
    {
        foreach (app(PresentacionProyectoCapturada::class)->datos($this->escuelaNivel->id) as $campo => $valor) {
            $this->{$campo} = (string) $valor;
        }

        foreach (['telefono', 'celular'] as $campo) {
            if (preg_match('/^(\d{3})(\d{3})(\d{4})$/', $this->{$campo}, $m)) {
                $this->{$campo} = "({$m[1]}) {$m[2]}-{$m[3]}";
            }
        }
    }

    /** Lo que la vista de la sección 4 necesita para pintar la pantalla actual. @return array<string, mixed> */
    private function datosPantallaEnfoque(): array
    {
        $total = count($this->pantallas());
        $clave = $this->claveActual();
        [$lista, $indice] = $this->ubicarEjemplo();

        return [
            'clave' => $clave,
            'lista' => $lista,
            'ejemplo' => $indice + 1,
            'ejemplos' => $lista === null ? 0 : count($this->{$lista}),
            'puedeAgregar' => $lista !== null && count($this->{$lista}) < ReglasEnfoqueProyecto::EJEMPLOS[1],
            'puedeQuitar' => $lista !== null && count($this->{$lista}) > ReglasEnfoqueProyecto::EJEMPLOS[0],
            'grupo' => $lista === null ? array_search($clave, ['propuesta_global', 'apropiacion_enfoque'], true) + 1 : 3,
            'numero' => min(max($this->pantalla, 1), $total),
            'total' => $total,
            'ultima' => $this->pantalla >= $total,
        ];
    }

    public function render()
    {
        $slugs = array_keys(SeccionesProyectoEducativo::todas());
        $indice = (int) array_search($this->seccion, $slugs, true);

        return view('livewire.tramite.paso3.proyecto-educativo-seccion', [
            'encabezado' => ResumenTramite::encabezado(SeccionesProyectoEducativo::CLAVE_PASO, $this->escuelaNivel->nivelEducativo),
            'numero' => $indice + 1,
            'nombre' => SeccionesProyectoEducativo::nombre($this->seccion),
            'esPresentacion' => $this->esPresentacion(),
            'esTexto' => $this->esTexto(),
            'enfoque' => $this->esEnfoque() ? $this->datosPantallaEnfoque() : null,
            'esPaec' => $this->esPaec(),
            'evaluacion' => $this->esEvaluacion() ? ['bloque' => $this->bloque, 'total' => count(ReglasEvaluacionProyecto::BLOQUES), 'ultimo' => $this->bloque >= count(ReglasEvaluacionProyecto::BLOQUES), ...ReglasEvaluacionProyecto::BLOQUES[$this->bloque]] : null,
            'curriculum' => $this->esCurriculum() ? ['bloque' => $this->bloque, 'total' => count(ReglasCurriculumProyecto::BLOQUES), 'ultimo' => $this->bloque >= count(ReglasCurriculumProyecto::BLOQUES), ...ReglasCurriculumProyecto::BLOQUES[$this->bloque]] : null,
            'ingreso' => $this->esIngreso() ? ['bloque' => $this->bloque, 'total' => count(ReglasIngresoProyecto::BLOQUES), 'ultimo' => $this->bloque >= count(ReglasIngresoProyecto::BLOQUES), ...ReglasIngresoProyecto::BLOQUES[$this->bloque]] : null,
            'formacion' => $this->esFormacion() ? $this->datosPantallaFormacion() : null,
            'pasos' => ReglasPresentacionProyecto::PASOS,
            'anterior' => isset($slugs[$indice - 1]) ? ['slug' => $slugs[$indice - 1], 'nombre' => SeccionesProyectoEducativo::nombre($slugs[$indice - 1])] : null,
            'siguiente' => isset($slugs[$indice + 1]) ? ['slug' => $slugs[$indice + 1], 'nombre' => SeccionesProyectoEducativo::nombre($slugs[$indice + 1])] : null,
        ])->layoutData(['escuelaId' => $this->escuelaNivel->escuela_id, 'escuelaNivelId' => $this->escuelaNivel->id, 'seccionActual' => SeccionesProyectoEducativo::CLAVE_PASO]);
    }
}
