<?php

namespace App\Application\ResponsableLegal;

use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Models\Gestor;
use App\Models\PersonaFisica;
use App\Models\PersonaMoral;
use App\Models\ResponsableLegal as ResponsableLegalModel;
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
    public function ejecutar(int $escuelaId, DatosResponsableLegal $datos): void
    {
        if (! in_array($datos->tipoPersona, ['fisica', 'fisica_con_gestor', 'moral'], true)) {
            throw new InvalidArgumentException("tipo_persona desconocido: {$datos->tipoPersona}");
        }

        // ponytail: idempotency guard — a repeated submit for an escuela that already
        // has a responsable_legal (escuela_id is NOT NULL UNIQUE) is treated as a no-op
        // rather than a QueryException.
        if (ResponsableLegalModel::where('escuela_id', $escuelaId)->exists()) {
            return;
        }

        DB::transaction(function () use ($escuelaId, $datos) {
            $responsable = ResponsableLegalModel::create([
                'escuela_id' => $escuelaId,
                'tipo_persona' => $datos->tipoPersona,
                'domicilio_notificaciones' => $datos->domicilioNotificaciones,
                'persona_autorizada_recoger' => $datos->personaAutorizadaRecoger,
            ]);

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
}
