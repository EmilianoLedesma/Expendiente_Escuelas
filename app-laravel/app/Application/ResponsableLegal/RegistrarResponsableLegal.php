<?php

namespace App\Application\ResponsableLegal;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\Tramite\TramiteEditable;
use App\Domain\Captura\Formatos;
use App\Infrastructure\Documentos\AlmacenDocumentos;
use App\Models\DocumentoEscuelaNivel;
use App\Models\EscuelaNivel;
use App\Models\Gestor;
use App\Models\PersonaFisica;
use App\Models\PersonaMoral;
use App\Models\ResponsableLegal as ResponsableLegalModel;
use App\Models\TernaNombre;
use App\Models\TipoDocumento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Caso de uso de PRD §5 Paso 2.1: crea o, desde WS-7b-1, actualiza en el lugar
 * responsables_legales, su subtipo y la terna, en una sola transacción. Livewire y la
 * futura API llaman exactamente a este método (ADR-001). La entrada es el estado
 * completo: un campo null queda null. Al cambiar de tipo se borran las filas del subtipo
 * que ya no aplica (no guardan archivos); los documentos subidos nunca se tocan, quedan
 * ocultos (spec WS-7b-1 §1). D1: si cambia algo que imprime el Formato de Solicitud, se
 * descarta el Formato firmado de cada nivel (mismo patrón que RegistrarDatosNivel).
 */
class RegistrarResponsableLegal
{
    public const TERNA_REPETIDA = 'Las tres propuestas de nombre deben ser distintas.';

    public function __construct(
        private readonly TramiteEditable $tramiteEditable = new TramiteEditable,
        private readonly ?AlmacenDocumentos $almacen = null,
    ) {}

    /** @throws DatosInvalidos|PrecondicionIncumplida si el trámite ya se envió (WS-7a). */
    public function ejecutar(int $escuelaId, DatosResponsableLegal $datos): void
    {
        if (! in_array($datos->tipoPersona, ['fisica', 'fisica_con_gestor', 'moral'], true)) {
            throw new InvalidArgumentException("tipo_persona desconocido: {$datos->tipoPersona}");
        }

        $this->validar($datos);

        DB::transaction(function () use ($escuelaId, $datos) {
            // WS-7a, orden escuela_niveles → escuelas. El bloqueo de los niveles también
            // serializa con la subida de un Formato (RegistrarDocumento bloquea el nivel).
            $niveles = EscuelaNivel::where('escuela_id', $escuelaId)->orderBy('id')->lockForUpdate()->pluck('estado_id', 'id');
            $this->tramiteEditable->asegurarEscuela($escuelaId, 'responsable');

            $responsable = ResponsableLegalModel::where('escuela_id', $escuelaId)->lockForUpdate()->first();
            $antes = $responsable === null ? null : $this->huellaFormato($responsable->id);
            $atributos = [
                'tipo_persona' => $datos->tipoPersona,
                'domicilio_notificaciones' => $datos->domicilioNotificaciones,
                'persona_autorizada_recoger' => $datos->personaAutorizadaRecoger,
            ];

            if ($responsable === null) {
                $responsable = ResponsableLegalModel::create(['escuela_id' => $escuelaId, ...$atributos]);
            } else {
                $responsable->update($atributos);
            }

            $this->guardarTerna($escuelaId, $datos);
            $this->guardarSubtipos($responsable->id, $datos);

            if ($antes !== null && $antes !== $this->huellaFormato($responsable->id)) {
                $this->descartarFormatos($this->nivelesConFormatoDescartable($niveles));
            }
        });
    }

    /** Una terna con dos nombres iguales (sin distinguir mayúsculas ni espacios) no es una terna. */
    public static function propuestaRepetida(?string $propuesta, ?string $anterior): bool
    {
        $normalizar = fn (?string $valor): string => (string) preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $valor)));

        return $normalizar($propuesta) !== '' && $normalizar($propuesta) === $normalizar($anterior);
    }

    /** En el lugar por número de propuesta: conserva valido_marca_comercial / valido_registro_sedeq. */
    private function guardarTerna(int $escuelaId, DatosResponsableLegal $datos): void
    {
        foreach ([1 => $datos->nombrePropuesto1, 2 => $datos->nombrePropuesto2, 3 => $datos->nombrePropuesto3] as $numero => $nombre) {
            $llave = ['escuela_id' => $escuelaId, 'numero_propuesta' => $numero];

            if ($nombre === null) {
                TernaNombre::where($llave)->delete();
            } else {
                TernaNombre::updateOrCreate($llave, ['nombre_propuesto' => $nombre]);
            }
        }
    }

    /** física ↔ física con gestor conserva personas_fisicas y solo agrega/quita gestores; moral entra y sale entera. */
    private function guardarSubtipos(int $responsableId, DatosResponsableLegal $datos): void
    {
        $llave = ['responsable_legal_id' => $responsableId];

        if (in_array($datos->tipoPersona, ['fisica', 'fisica_con_gestor'], true)) {
            PersonaFisica::updateOrCreate($llave, [
                'nombre' => $datos->nombre,
                'fecha_nacimiento' => $datos->fechaNacimiento,
                'rfc' => $datos->rfc,
                'curp' => $datos->curp,
            ]);
        } else {
            PersonaFisica::where($llave)->delete();
        }

        if ($datos->tipoPersona === 'fisica_con_gestor') {
            Gestor::updateOrCreate($llave, [
                'nombre' => $datos->gestorNombre,
                'numero_poder' => $datos->gestorNumeroPoder,
                'notario_nombre' => $datos->gestorNotarioNombre,
                'notario_numero' => $datos->gestorNotarioNumero,
                'fecha_poder' => $datos->gestorFechaPoder,
                'curp' => $datos->gestorCurp,
            ]);
        } else {
            Gestor::where($llave)->delete();
        }

        if ($datos->tipoPersona === 'moral') {
            PersonaMoral::updateOrCreate($llave, [
                'razon_social' => $datos->razonSocial,
                'numero_escritura_constitutiva' => $datos->numeroEscrituraConstitutiva,
                'fecha_escritura_constitutiva' => $datos->fechaEscrituraConstitutiva,
                'notario_nombre' => $datos->notarioNombre,
                'notario_numero' => $datos->notarioNumero,
                'notario_ciudad' => $datos->notarioCiudad,
                'folio_registro_publico' => $datos->folioRegistroPublico,
                'fecha_inscripcion_rpp' => $datos->fechaInscripcionRpp,
                'nombre_representante_legal' => $datos->nombreRepresentanteLegal,
            ]);
        } else {
            PersonaMoral::where($llave)->delete();
        }
    }

    /**
     * Lo que pdf/formato-solicitud.blade.php imprime del responsable (D1): bloque moral o
     * físico, domicilio para notificaciones y persona autorizada. La terna y el gestor no se
     * imprimen hoy; si el formato empieza a imprimirlos, agrégalos aquí.
     *
     * @return list<array<string, mixed>>
     */
    private function huellaFormato(int $responsableId): array
    {
        $fila = fn (string $tabla, string $llave, array $columnas): array => (array) (DB::table($tabla)->where($llave, $responsableId)->first($columnas) ?? []);

        $responsable = $fila('responsables_legales', 'id', ['tipo_persona', 'domicilio_notificaciones', 'persona_autorizada_recoger']);
        $responsable['tipo_persona'] = ($responsable['tipo_persona'] ?? null) === 'moral';

        return [
            $responsable,
            $fila('personas_fisicas', 'responsable_legal_id', ['nombre', 'fecha_nacimiento', 'rfc', 'curp']),
            $fila('personas_morales', 'responsable_legal_id', [
                'razon_social', 'numero_escritura_constitutiva', 'fecha_escritura_constitutiva', 'notario_nombre',
                'notario_numero', 'notario_ciudad', 'folio_registro_publico', 'fecha_inscripcion_rpp', 'nombre_representante_legal',
            ]),
        ];
    }

    /**
     * Devolución: solo los niveles que el solicitante puede volver a cargar (en captura, o con_observaciones, donde
     * PasosReabiertos reabre `documentos_nivel` junto con `responsable`). Un nivel aprobado o en revisión conserva su Formato.
     *
     * @param  Collection<int, mixed>  $niveles  estado_id por id de escuela_nivel
     * @return list<int>
     */
    private function nivelesConFormatoDescartable(Collection $niveles): array
    {
        $descartables = DB::table('estados_expediente')->whereIn('clave', [TramiteEditable::EN_CAPTURA, 'con_observaciones'])->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $niveles->filter(fn ($estadoId) => in_array((int) $estadoId, $descartables, true))->keys()->map(fn ($id) => (int) $id)->values()->all();
    }

    /** @param list<int> $nivelIds */
    private function descartarFormatos(array $nivelIds): void
    {
        if ($nivelIds === []) {
            return;
        }

        $formatos = DocumentoEscuelaNivel::whereIn('escuela_nivel_id', $nivelIds)
            ->where('tipo_documento_id', TipoDocumento::where('clave', 'formato_solicitud')->firstOrFail()->id)
            ->lockForUpdate()
            ->get(['id', 'archivo_path']);

        if ($formatos->isEmpty()) {
            return;
        }

        DocumentoEscuelaNivel::whereIn('id', $formatos->pluck('id'))->delete();
        $rutas = $formatos->pluck('archivo_path')->filter()->values()->all();
        $almacen = $this->almacen ?? app(AlmacenDocumentos::class);

        // Mismo patrón que RegistrarDatosNivel: el archivo se borra solo si la transacción
        // real confirma, y un fallo al borrar se reporta sin revertir lo confirmado.
        DB::afterCommit(function () use ($rutas, $almacen) {
            foreach ($rutas as $ruta) {
                rescue(fn () => $almacen->eliminar($ruta), report: true);
            }
        });
    }

    /**
     * Invariantes de entrada: los nombres que el DDL declara NOT NULL por
     * subtipo (personas_fisicas.nombre, gestores.nombre, personas_morales.
     * razon_social/nombre_representante_legal), el formato de CURP/RFC y que
     * la terna no repita nombres. Las claves usan la ruta del Form object de
     * Paso2Responsable para mapearse 1:1 con addError().
     */
    private function validar(DatosResponsableLegal $datos): void
    {
        $errores = [];
        $vacio = fn (?string $valor): bool => $valor === null || trim($valor) === '';

        if ($datos->tipoPersona === 'moral') {
            if ($vacio($datos->razonSocial)) {
                $errores['personaMoralForm.razonSocial'] = 'El campo razón social es obligatorio.';
            }
            if ($vacio($datos->nombreRepresentanteLegal)) {
                $errores['personaMoralForm.nombreRepresentanteLegal'] = 'El campo representante legal es obligatorio.';
            }
        } else {
            if ($vacio($datos->nombre)) {
                $errores['personaFisicaForm.nombre'] = 'El campo nombre completo es obligatorio.';
            }
            if ($datos->rfc !== null && ! Formatos::esRfcPersonaFisica($datos->rfc)) {
                $errores['personaFisicaForm.rfc'] = 'El RFC no tiene un formato válido: son 13 caracteres para persona física, por ejemplo GOMA800101AB1.';
            }
            if ($datos->curp !== null && ! Formatos::esCurp($datos->curp)) {
                $errores['personaFisicaForm.curp'] = 'La CURP no tiene un formato válido: son 18 caracteres, por ejemplo GOMA800101HQTRRL09.';
            }
            if ($datos->tipoPersona === 'fisica_con_gestor' && $vacio($datos->gestorNombre)) {
                $errores['gestorForm.nombre'] = 'El campo nombre del gestor es obligatorio.';
            }
            if ($datos->tipoPersona === 'fisica_con_gestor' && $datos->gestorCurp !== null && ! Formatos::esCurp($datos->gestorCurp)) {
                $errores['gestorForm.curp'] = 'La CURP del gestor no tiene un formato válido: son 18 caracteres, por ejemplo GOMA800101HQTRRL09.';
            }
        }

        $propuestas = [1 => $datos->nombrePropuesto1, 2 => $datos->nombrePropuesto2, 3 => $datos->nombrePropuesto3];
        foreach ([2 => [1], 3 => [1, 2]] as $numero => $anteriores) {
            foreach ($anteriores as $anterior) {
                if (self::propuestaRepetida($propuestas[$numero], $propuestas[$anterior])) {
                    $errores["nombrePropuesto{$numero}"] = self::TERNA_REPETIDA;
                    break;
                }
            }
        }

        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }
    }
}
