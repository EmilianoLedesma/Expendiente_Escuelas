<?php

namespace App\Application\ResponsableLegal;

use App\Models\ResponsableLegal;

/**
 * Única lectura de "¿qué tipo_persona tiene el responsable legal de esta
 * escuela?" — antes vivía duplicada en Paso2Documentos::tipoPersona() y en
 * ObtenerDocumentoCapturado::ejecutar(). Devuelve null en vez de lanzar
 * cuando no hay responsable; cada caller decide si eso es un error (Paso2
 * Documentos, que solo se ejecuta después de que EstadoPaso2 ya confirmó
 * que hay responsable) o un resultado válido (ObtenerDocumentoCapturado,
 * que responde null → 404 en vez de una excepción).
 */
class TipoPersonaDeEscuela
{
    public function ejecutar(int $escuelaId): ?string
    {
        return ResponsableLegal::where('escuela_id', $escuelaId)->value('tipo_persona');
    }
}
