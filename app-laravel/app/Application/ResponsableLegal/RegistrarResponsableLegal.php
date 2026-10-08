<?php

namespace App\Application\ResponsableLegal;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\Tramite\TramiteEditable;
use App\Domain\Captura\Formatos;
use App\Models\Gestor;
use App\Models\PersonaFisica;
use App\Models\PersonaMoral;
use App\Models\ResponsableLegal as ResponsableLegalModel;
use App\Models\TernaNombre;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Caso de uso de PRD §5 Paso 2.1: crea responsables_legales + el subtipo
 * correspondiente en una sola transacción. Livewire y la futura API
 * llaman exactamente a este método (ADR-001) — ninguno de los dos debe
 * tocar Eloquent directamente para estas tablas.
 */
class RegistrarResponsableLegal
{
    public function __construct(private readonly TramiteEditable $tramiteEditable = new TramiteEditable) {}

    public function ejecutar(int $escuelaId, DatosResponsableLegal $datos): void
    {
        if (! in_array($datos->tipoPersona, ['fisica', 'fisica_con_gestor', 'moral'], true)) {
            throw new InvalidArgumentException("tipo_persona desconocido: {$datos->tipoPersona}");
        }

        $this->validar($datos);

        DB::transaction(function () use ($escuelaId, $datos) {
            // WS-7a: before the idempotency check, so a sent trámite says so instead of a silent no-op.
            $this->tramiteEditable->asegurarEscuela($escuelaId);

            // ponytail: idempotency guard — a repeated submit for an escuela that already
            // has a responsable_legal (escuela_id is NOT NULL UNIQUE) is treated as a no-op
            // rather than a QueryException.
            if (ResponsableLegalModel::where('escuela_id', $escuelaId)->exists()) {
                return;
            }

            $responsable = ResponsableLegalModel::create([
                'escuela_id' => $escuelaId,
                'tipo_persona' => $datos->tipoPersona,
                'domicilio_notificaciones' => $datos->domicilioNotificaciones,
                'persona_autorizada_recoger' => $datos->personaAutorizadaRecoger,
            ]);

            foreach ([1 => $datos->nombrePropuesto1, 2 => $datos->nombrePropuesto2, 3 => $datos->nombrePropuesto3] as $numeroPropuesta => $nombrePropuesto) {
                if ($nombrePropuesto === null) {
                    continue;
                }

                TernaNombre::create([
                    'escuela_id' => $escuelaId,
                    'numero_propuesta' => $numeroPropuesta,
                    'nombre_propuesto' => $nombrePropuesto,
                ]);
            }

            if (in_array($datos->tipoPersona, ['fisica', 'fisica_con_gestor'], true)) {
                PersonaFisica::create([
                    'responsable_legal_id' => $responsable->id,
                    'nombre' => $datos->nombre,
                    'fecha_nacimiento' => $datos->fechaNacimiento,
                    'rfc' => $datos->rfc,
                    'curp' => $datos->curp,
                ]);
            }

            if ($datos->tipoPersona === 'fisica_con_gestor') {
                Gestor::create([
                    'responsable_legal_id' => $responsable->id,
                    'nombre' => $datos->gestorNombre,
                    'numero_poder' => $datos->gestorNumeroPoder,
                    'notario_nombre' => $datos->gestorNotarioNombre,
                    'notario_numero' => $datos->gestorNotarioNumero,
                    'fecha_poder' => $datos->gestorFechaPoder,
                    'curp' => $datos->gestorCurp,
                ]);
            }

            if ($datos->tipoPersona === 'moral') {
                PersonaMoral::create([
                    'responsable_legal_id' => $responsable->id,
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
            }
        });
    }

    /**
     * Invariantes de entrada: los nombres que el DDL declara NOT NULL por
     * subtipo (personas_fisicas.nombre, gestores.nombre, personas_morales.
     * razon_social/nombre_representante_legal) y el formato de CURP/RFC. Las
     * claves usan la ruta del Form object de Paso2Responsable para mapearse
     * 1:1 con addError().
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

        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }
    }
}
