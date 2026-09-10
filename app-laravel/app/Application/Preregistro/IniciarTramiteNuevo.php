<?php

namespace App\Application\Preregistro;

use App\Application\Preregistro\DTO\DatosPreregistro;
use App\Application\Preregistro\DTO\ResultadoPreregistro;
use App\Models\Escuela;
use App\Models\Plantel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Caso de uso de PRD §5 Paso 1: crea el `plantel` (si es nuevo) y siempre
 * crea la `escuela`. Livewire y un futuro controlador de API llaman
 * exactamente a este método — ninguno de los dos debe tocar Eloquent
 * directamente (ADR-001).
 *
 * `$solicitanteId` es resuelto por el llamador desde la sesión autenticada
 * (nunca desde input del cliente) — ver
 * docs/superpowers/specs/2026-09-08-modelo-identidad-solicitante-design.md.
 */
class IniciarTramiteNuevo
{
    public function ejecutar(DatosPreregistro $datos, int $solicitanteId): ResultadoPreregistro
    {
        $this->validar($datos);

        return DB::transaction(function () use ($datos, $solicitanteId) {
            $plantelId = $datos->bifurcacion === 'nuevo'
                ? Plantel::create([
                    'calle' => $datos->calle,
                    'numero_ext' => $datos->numeroExt,
                    'numero_int' => $datos->numeroInt,
                    'colonia' => $datos->colonia,
                    'localidad' => $datos->localidad,
                    'municipio' => $datos->municipio,
                    'codigo_postal' => $datos->codigoPostal,
                    'telefono' => $datos->telefono,
                    'correo_electronico' => $datos->correoElectronico,
                ])->id
                : $datos->plantelId;

            // Reutiliza una escuela del mismo (plantel, solicitante) solo mientras
            // siga sin responsable legal — eso es lo que distingue "retomar un
            // Paso 1 abandonado" de "esta escuela ya se completó, es otro trámite
            // distinto". Una escuela con responsable ya avanzó más allá de
            // preregistro y nunca debe reutilizarse para una visita nueva.
            $escuela = Escuela::where('plantel_id', $plantelId)
                ->where('solicitante_id', $solicitanteId)
                ->whereDoesntHave('responsableLegal')
                ->first()
                ?? Escuela::create([
                    'plantel_id' => $plantelId,
                    'solicitante_id' => $solicitanteId,
                ]);

            return new ResultadoPreregistro(
                escuelaId: $escuela->id,
                plantelId: $plantelId,
            );
        });
    }

    private function validar(DatosPreregistro $datos): void
    {
        if ($datos->bifurcacion === 'existente') {
            if ($datos->plantelId === null) {
                throw new InvalidArgumentException('bifurcacion "existente" requiere plantelId.');
            }

            return;
        }

        if ($datos->bifurcacion !== 'nuevo') {
            throw new InvalidArgumentException("bifurcacion desconocida: {$datos->bifurcacion}");
        }

        foreach (['calle' => $datos->calle, 'colonia' => $datos->colonia, 'municipio' => $datos->municipio, 'codigoPostal' => $datos->codigoPostal] as $campo => $valor) {
            if ($valor === null || $valor === '') {
                throw new InvalidArgumentException("bifurcacion \"nuevo\" requiere {$campo}.");
            }
        }
    }
}
