<?php

namespace App\Livewire\Tramite\Paso3;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\DTO\DatosPresentacionProyecto;
use App\Application\ProyectoEducativo\GuardarPresentacionProyecto;
use App\Application\ProyectoEducativo\PresentacionProyectoCapturada;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\ResumenTramite;
use App\Application\Tramite\TramiteEditable;
use App\Livewire\Concerns\NotificaToast;
use App\Livewire\Tramite\Paso3\Concerns\CompuertaPaso3;
use App\Models\EscuelaNivel;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

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

    #[Locked]
    public EscuelaNivel $escuelaNivel;

    #[Locked]
    public string $seccion = '';

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
        }
    }

    public function guardar(GuardarPresentacionProyecto $guardar): void
    {
        abort_unless($this->esPresentacion(), 404);
        $this->resetErrorBag();

        try {
            $guardar->ejecutar($this->escuelaNivel->id, new DatosPresentacionProyecto(...$this->only(DatosPresentacionProyecto::campos())));
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

        $this->cargar();
        $this->toast('Sección guardada', 'Puedes seguir con la siguiente sección.');
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

    public function render()
    {
        $slugs = array_keys(SeccionesProyectoEducativo::todas());
        $indice = (int) array_search($this->seccion, $slugs, true);

        return view('livewire.tramite.paso3.proyecto-educativo-seccion', [
            'encabezado' => ResumenTramite::encabezado(SeccionesProyectoEducativo::CLAVE_PASO, $this->escuelaNivel->nivelEducativo),
            'numero' => $indice + 1,
            'nombre' => SeccionesProyectoEducativo::nombre($this->seccion),
            'esPresentacion' => $this->esPresentacion(),
            'anterior' => isset($slugs[$indice - 1]) ? ['slug' => $slugs[$indice - 1], 'nombre' => SeccionesProyectoEducativo::nombre($slugs[$indice - 1])] : null,
            'siguiente' => isset($slugs[$indice + 1]) ? ['slug' => $slugs[$indice + 1], 'nombre' => SeccionesProyectoEducativo::nombre($slugs[$indice + 1])] : null,
        ])->layoutData(['escuelaId' => $this->escuelaNivel->escuela_id, 'escuelaNivelId' => $this->escuelaNivel->id, 'seccionActual' => SeccionesProyectoEducativo::CLAVE_PASO]);
    }
}
