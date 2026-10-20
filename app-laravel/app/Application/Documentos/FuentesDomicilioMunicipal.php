<?php

namespace App\Application\Documentos;

use App\Application\Documentos\DTO\FuenteDomicilio;
use App\Domain\Validaciones\Documental\CatalogoReglasDocumentales;

/**
 * Recopila los domicilios que declaran los documentos municipales ya capturados, como evidencia para la
 * aplicabilidad del Certificado de Número Oficial (spec docs/specs/2026-10-20-paso2-integracion-end-to-end.md §6.1).
 *
 * Solo lee datos estructurados capturados: no lee los PDF, no usa OCR ni modelos de lenguaje. La matriz de
 * disponibilidad (verificada contra las migraciones el 2026-10-20) dice qué documento guarda un domicilio:
 * hoy **ninguno** aparte del propio certificado, que se excluye porque no puede probar que él mismo no se
 * necesita. Por eso `para()` devuelve una lista vacía y la evaluación queda «Pendiente de determinar» cuando
 * SEDEQ configure un alcance. Cuando un documento municipal empiece a capturar su domicilio, se marca aquí con
 * su lector; cada documento cuenta una sola vez.
 */
class FuentesDomicilioMunicipal
{
    /**
     * @var array<string, array{tabla: string, domicilio_estructurado: bool, autoridad: string|null, jurisdiccion: string|null, excluido: bool}>
     */
    private const DISPONIBILIDAD = [
        'dictamen_uso_suelo' => ['tabla' => 'dictamenes_uso_suelo', 'domicilio_estructurado' => false, 'autoridad' => 'autoridad_expide', 'jurisdiccion' => null, 'excluido' => false],
        'escritura_inmueble' => ['tabla' => 'acreditaciones_ocupacion_legal', 'domicilio_estructurado' => false, 'autoridad' => null, 'jurisdiccion' => null, 'excluido' => false],
        'constancia_seguridad_estructural' => ['tabla' => 'constancias_seguridad_estructural', 'domicilio_estructurado' => false, 'autoridad' => 'perito_registro_autoridad', 'jurisdiccion' => null, 'excluido' => false],
        'visto_bueno_proteccion_civil' => ['tabla' => 'vistos_buenos_proteccion_civil', 'domicilio_estructurado' => false, 'autoridad' => 'autoridad_emisora', 'jurisdiccion' => 'jurisdiccion_emisora', 'excluido' => false],
        CatalogoReglasDocumentales::FUENTE_DOMICILIO => ['tabla' => 'certificados_numero_oficial', 'domicilio_estructurado' => true, 'autoridad' => 'autoridad_emisora', 'jurisdiccion' => null, 'excluido' => true],
    ];

    /** @return array<string, array{tabla: string, domicilio_estructurado: bool, autoridad: string|null, jurisdiccion: string|null, excluido: bool}> */
    public function disponibilidad(): array
    {
        return self::DISPONIBILIDAD;
    }

    /**
     * Fuentes con domicilio estructurado del plantel, sin el certificado y sin repetir documentos. Hoy siempre vacía:
     * ningún documento municipal disponible guarda un domicilio (ver la matriz).
     *
     * @return list<FuenteDomicilio>
     */
    public function para(int $plantelId): array
    {
        // Ningún tipo de la matriz con `domicilio_estructurado` es independiente del certificado: no hay lector que
        // invocar. Al agregar uno, se lee aquí su domicilio del plantel $plantelId y se marca en la matriz.
        return [];
    }
}
