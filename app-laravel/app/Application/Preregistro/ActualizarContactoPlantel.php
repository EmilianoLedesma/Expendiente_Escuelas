<?php

namespace App\Application\Preregistro;

use App\Application\Escuelas\VerificarPropietarioEscuela;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\TramiteEditable;
use App\Domain\Captura\Formatos;
use App\Models\Escuela;
use App\Models\Plantel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * WS-7b-1 U1: el teléfono y el correo del plantel se corrigen hasta el envío. El
 * domicilio no se acepta aquí: cambiarlo requiere autorización previa de la Dirección de
 * Educación (decisión del dueño). El plantel se comparte entre las escuelas del mismo
 * solicitante (ADR-009), así que la guarda es la del plantel: se rechaza si cualquier
 * trámite del plantel ya se envió. Sin bloqueo de niveles (spec U1): no toca filas de nivel.
 */
class ActualizarContactoPlantel
{
    public function __construct(
        private readonly VerificarPropietarioEscuela $propietario,
        private readonly TramiteEditable $tramiteEditable = new TramiteEditable,
    ) {}

    /** @throws AuthorizationException|DatosInvalidos|PrecondicionIncumplida */
    public function ejecutar(int $escuelaId, int $solicitanteId, ?string $telefono, ?string $correoElectronico): void
    {
        if (! $this->propietario->ejecutar($escuelaId, $solicitanteId)) {
            throw new AuthorizationException;
        }

        // Mismos criterios y mensajes que Paso 1 (IniciarTramiteNuevo).
        $errores = [];
        if ($telefono !== null && ! Formatos::esTelefono($telefono)) {
            $errores['telefono'] = 'El teléfono debe tener 10 dígitos, por ejemplo 4421234567.';
        }
        if ($correoElectronico !== null && (mb_strlen($correoElectronico) > 150 || ! Formatos::esCorreo($correoElectronico))) {
            $errores['correoElectronico'] = 'Ingresa un correo electrónico válido, por ejemplo: nombre@dominio.com.';
        }
        if ($errores !== []) {
            throw new DatosInvalidos($errores);
        }

        DB::transaction(function () use ($escuelaId, $telefono, $correoElectronico) {
            $plantelId = (int) Escuela::whereKey($escuelaId)->value('plantel_id');
            $this->tramiteEditable->asegurarPlantel($plantelId, 'plantel', $escuelaId);

            Plantel::whereKey($plantelId)->update(['telefono' => $telefono, 'correo_electronico' => $correoElectronico]);
        });
    }
}
