<?php

namespace App\Livewire\Tramite;

use App\Application\Documentos\DocumentosCapturados;
use App\Application\Documentos\DocumentosNivelCompletos;
use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\Documentos\ValidarVigenciaDocumentos;
use App\Application\EscuelaNiveles\RegistrarDatosNivel;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\EstadoPaso2;
use App\Application\Tramite\EstadoPaso24;
use App\Application\Tramite\ResumenTramite;
use App\Livewire\Forms\ReciboPagoForm;
use App\Models\EscuelaNivel;
use App\Models\TipoDocumento;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Presentación pura de Paso 2.4 (WS-5b): una página por escuela_nivel con
 * turno y tipo de alumnado, Formato de Solicitud, recibo de pago y los
 * documentos del nivel. Decide EstadoPaso24/DocumentosNivelCompletos; escriben
 * RegistrarDatosNivel y RegistrarDocumento (ADR-001/006). No redirige al
 * estar completa: el Formato firmado se puede reemplazar.
 */
#[Layout('layouts.tramite')]
class Paso24DocumentosNivel extends Component
{
    use WithFileUploads;

    /** Claves con campos propios y método propio; toda otra clave aplicable va por guardarDocumento() (WS-5a M1). Pública: la lee la prueba guarda del catálogo. */
    public const CON_DATOS_ESTRUCTURADOS = ['recibo_pago_derechos'];

    /** Bloqueada: el cliente no puede retargetar el nivel (id/escuela_id) que leen todas las acciones. */
    #[Locked]
    public EscuelaNivel $escuelaNivel;

    public string $turno = '';

    public string $tipoAlumnado = '';

    public array $archivos = [];

    /** @var array<string, bool> clave => true mientras se muestra el form de reemplazo. */
    public array $reemplazando = [];

    public ReciboPagoForm $recibo;

    public function mount(EscuelaNivel $escuelaNivel, EstadoPaso2 $estadoPaso2): void
    {
        $this->escuelaNivel = $escuelaNivel;

        if (! $estadoPaso2->puedeSeleccionarNiveles($escuelaNivel->escuela_id)) {
            $this->redirectRoute('tramite.paso2', ['escuela' => $escuelaNivel->escuela_id]);

            return;
        }

        $this->turno = (string) $escuelaNivel->turno;
        $this->tipoAlumnado = (string) $escuelaNivel->tipo_alumnado;
    }

    public function guardarDatos(RegistrarDatosNivel $registrarDatosNivel): void
    {
        $this->validate([
            'turno' => ['required', 'in:'.implode(',', RegistrarDatosNivel::TURNOS)],
            'tipoAlumnado' => ['required', 'in:'.implode(',', RegistrarDatosNivel::TIPOS_ALUMNADO)],
        ]);

        try {
            $registrarDatosNivel->ejecutar($this->escuelaNivel->id, $this->turno, $this->tipoAlumnado);
        } catch (PrecondicionIncumplida) {
            // DatosInvalidos no puede ocurrir aquí: validate() ya aplica las mismas listas que el caso de uso.
            $this->redirectRoute('tramite.paso2', ['escuela' => $this->escuelaNivel->escuela_id]);
        }
    }

    public function guardarDocumento(string $clave, RegistrarDocumento $registrarDocumento, DocumentosNivelCompletos $documentosNivelCompletos): void
    {
        $simples = array_diff($documentosNivelCompletos->clavesAplicables($this->escuelaNivel->id), self::CON_DATOS_ESTRUCTURADOS);
        abort_unless(in_array($clave, $simples, true), 403);

        $this->validate(["archivos.{$clave}" => ['required', 'file', 'mimes:pdf', 'max:10240']]);

        if ($this->intentarRegistrar($registrarDocumento, $clave, new DatosDocumento)) {
            $this->archivos[$clave] = null;
            $this->reemplazando[$clave] = false;
        }
    }

    public function guardarRecibo(RegistrarDocumento $registrarDocumento): void
    {
        $this->validate(['archivos.recibo_pago_derechos' => ['required', 'file', 'mimes:pdf', 'max:10240']]);
        $this->recibo->validate();

        if ($this->intentarRegistrar($registrarDocumento, 'recibo_pago_derechos', new DatosDocumento(
            folio: $this->recibo->folio,
            monto: $this->recibo->monto,
            fechaPago: $this->recibo->fechaPago,
            portalReferencia: $this->recibo->portalReferencia !== '' ? $this->recibo->portalReferencia : null,
        ))) {
            $this->archivos['recibo_pago_derechos'] = null;
            $this->reemplazando['recibo_pago_derechos'] = false;
            $this->recibo->reset();
        }
    }

    public function toggleReemplazar(string $clave, DocumentosNivelCompletos $documentosNivelCompletos): void
    {
        if (! in_array($clave, $documentosNivelCompletos->clavesAplicables($this->escuelaNivel->id), true)) {
            return;
        }

        $this->reemplazando[$clave] = ! ($this->reemplazando[$clave] ?? false);
    }

    /** DatosInvalidos → errores de campo; el Formato antes de los datos → error en turno; otra precondición → Paso 2. */
    private function intentarRegistrar(RegistrarDocumento $registrarDocumento, string $clave, DatosDocumento $datos): bool
    {
        try {
            $registrarDocumento->ejecutar($this->escuelaNivel->escuela_id, $clave, $this->archivos[$clave], $datos, $this->escuelaNivel->id);
        } catch (DatosInvalidos $e) {
            foreach ($e->errores as $campo => $mensaje) {
                $this->addError($campo, $mensaje);
            }

            return false;
        } catch (PrecondicionIncumplida $e) {
            if ($e->etapaFaltante === EstadoPaso24::DATOS) {
                $this->addError('turno', $e->getMessage());
            } else {
                $this->redirectRoute('tramite.paso2', ['escuela' => $this->escuelaNivel->escuela_id]);
            }

            return false;
        }

        return true;
    }

    public function render(DocumentosNivelCompletos $documentosNivelCompletos, DocumentosCapturados $documentosCapturados, EstadoPaso24 $estadoPaso24, ValidarVigenciaDocumentos $validarVigencia)
    {
        $claves = $documentosNivelCompletos->clavesAplicables($this->escuelaNivel->id);
        $capturados = $documentosCapturados->paraEscuelaNivel($this->escuelaNivel->id);

        return view('livewire.tramite.paso24-documentos-nivel', [
            'clavesAplicables' => $claves,
            'titulos' => TipoDocumento::whereIn('clave', $claves)->pluck('nombre', 'clave')->all(),
            'capturados' => $capturados,
            // Solo para marcar la fila vencida y avisar antes de descartar el Formato; las reglas siguen en Application.
            'vencidos' => $validarVigencia->paraEscuelaNivel($this->escuelaNivel->id),
            'formatoSubido' => $capturados->has('formato_solicitud'),
            'datosCapturados' => $estadoPaso24->datosNivelCapturados($this->escuelaNivel->id),
            'completo' => $estadoPaso24->etapaFaltante($this->escuelaNivel->id) === null,
            'encabezado' => ResumenTramite::encabezado('documentos_nivel', $this->escuelaNivel->nivelEducativo),
        ])->layoutData(['escuelaId' => $this->escuelaNivel->escuela_id, 'escuelaNivelId' => $this->escuelaNivel->id, 'seccionActual' => 'documentos_nivel']);
    }
}
