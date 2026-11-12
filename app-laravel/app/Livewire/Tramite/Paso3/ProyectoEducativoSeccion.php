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
use App\Application\ProyectoEducativo\DTO\DatosIngresoProyecto;
use App\Application\ProyectoEducativo\DTO\DatosObjetivosProyecto;
use App\Application\ProyectoEducativo\DTO\DatosPaecProyecto;
use App\Application\ProyectoEducativo\DTO\DatosPresentacionProyecto;
use App\Application\ProyectoEducativo\EgresoProyectoCapturada;
use App\Application\ProyectoEducativo\EnfoqueProyectoCapturada;
use App\Application\ProyectoEducativo\EvaluacionProyectoCapturada;
use App\Application\ProyectoEducativo\FilosofiaProyectoCapturada;
use App\Application\ProyectoEducativo\GuardarCurriculumProyecto;
use App\Application\ProyectoEducativo\GuardarEgresoProyecto;
use App\Application\ProyectoEducativo\GuardarEnfoqueProyecto;
use App\Application\ProyectoEducativo\GuardarEvaluacionProyecto;
use App\Application\ProyectoEducativo\GuardarFilosofiaProyecto;
use App\Application\ProyectoEducativo\GuardarIngresoProyecto;
use App\Application\ProyectoEducativo\GuardarObjetivosProyecto;
use App\Application\ProyectoEducativo\GuardarPaecProyecto;
use App\Application\ProyectoEducativo\GuardarPresentacionProyecto;
use App\Application\ProyectoEducativo\IngresoProyectoCapturada;
use App\Application\ProyectoEducativo\ObjetivosProyectoCapturada;
use App\Application\ProyectoEducativo\PaecProyectoCapturada;
use App\Application\ProyectoEducativo\PresentacionProyectoCapturada;
use App\Application\ProyectoEducativo\ReglasCurriculumProyecto;
use App\Application\ProyectoEducativo\ReglasEnfoqueProyecto;
use App\Application\ProyectoEducativo\ReglasEvaluacionProyecto;
use App\Application\ProyectoEducativo\ReglasIngresoProyecto;
use App\Application\ProyectoEducativo\ReglasPresentacionProyecto;
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
        }
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
        return in_array($this->seccion, [SeccionesProyectoEducativo::filosofia(), SeccionesProyectoEducativo::objetivos(), SeccionesProyectoEducativo::evaluacion(), SeccionesProyectoEducativo::curriculum(), SeccionesProyectoEducativo::ingreso(), SeccionesProyectoEducativo::egreso()], true);
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
            'pasos' => ReglasPresentacionProyecto::PASOS,
            'anterior' => isset($slugs[$indice - 1]) ? ['slug' => $slugs[$indice - 1], 'nombre' => SeccionesProyectoEducativo::nombre($slugs[$indice - 1])] : null,
            'siguiente' => isset($slugs[$indice + 1]) ? ['slug' => $slugs[$indice + 1], 'nombre' => SeccionesProyectoEducativo::nombre($slugs[$indice + 1])] : null,
        ])->layoutData(['escuelaId' => $this->escuelaNivel->escuela_id, 'escuelaNivelId' => $this->escuelaNivel->id, 'seccionActual' => SeccionesProyectoEducativo::CLAVE_PASO]);
    }
}
