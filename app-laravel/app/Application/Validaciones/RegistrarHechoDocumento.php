<?php

namespace App\Application\Validaciones;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\EstadoPaso2;
use App\Domain\Validaciones\Documental\TipoHecho;
use App\Models\DocumentoEscuela;
use App\Models\HechoDocumento;
use App\Models\TipoDocumento;
use InvalidArgumentException;

/**
 * Records a fact read off an uploaded document (today: typed by the
 * applicant from their INE, metodo = captura_manual). Takes plain strings so
 * a Livewire caller never imports app/Domain (ADR-001). The fact is pinned
 * to the document's current archivo_path; re-capturing the same fact for
 * the same file corrects it, a new file starts a new set.
 */
class RegistrarHechoDocumento
{
    public function ejecutar(int $escuelaId, string $tipoDocumentoClave, string $tipoHecho, string $valor): void
    {
        $campo = "hechos.{$tipoDocumentoClave}.{$tipoHecho}";

        if (TipoHecho::tryFrom($tipoHecho) === null) {
            throw new DatosInvalidos([$campo => "Tipo de dato desconocido: {$tipoHecho}."]);
        }

        if (trim($valor) === '') {
            throw new DatosInvalidos([$campo => 'El dato no puede estar vacío.']);
        }

        $tipo = TipoDocumento::where('clave', $tipoDocumentoClave)->first();

        if ($tipo === null) {
            throw new InvalidArgumentException("tipo_documento desconocido: {$tipoDocumentoClave}");
        }

        // Plantel-scoped documents are shared across escuelas; who owns
        // their facts is open (PENDIENTE-motor-validacion-hechos P7).
        if ($tipo->ambito !== 'escuela') {
            throw new DatosInvalidos([$campo => 'Solo se registran datos de documentos de la escuela por ahora.']);
        }

        $archivoPath = DocumentoEscuela::where('escuela_id', $escuelaId)
            ->where('tipo_documento_id', $tipo->id)
            ->value('archivo_path');

        if ($archivoPath === null) {
            throw new PrecondicionIncumplida(EstadoPaso2::DOCUMENTOS, 'Sube el documento antes de capturar sus datos.');
        }

        HechoDocumento::updateOrCreate(
            [
                'escuela_id' => $escuelaId,
                'tipo_documento_id' => $tipo->id,
                'archivo_path' => $archivoPath,
                'tipo_hecho' => $tipoHecho,
            ],
            ['valor' => $valor, 'metodo' => 'captura_manual'],
        );
    }
}
