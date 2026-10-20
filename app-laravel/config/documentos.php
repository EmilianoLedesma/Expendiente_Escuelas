<?php

/*
 * Políticas de documentos de Paso 2.2 que dependen de criterios de SEDEQ todavía no confirmados.
 *
 * numero_oficial.alcance_condicional: claves de nivel educativo (niveles_educativos.clave) en las que SEDEQ
 * confirmó que el Certificado de Número Oficial se exige solo cuando los documentos municipales traen
 * domicilios diferentes (requisitos de Educación Básica §16, hoy «Documentado, no contrastado»: ver
 * COMPENDIO_MAESTRO §5 y §9). Mientras la lista esté vacía —el valor por defecto— el certificado es
 * obligatorio para todos los planteles y niveles. Cambiarla es una decisión del dueño del proyecto con
 * respaldo de SEDEQ, nunca del solicitante.
 */
return [
    'numero_oficial' => [
        'alcance_condicional' => [],
    ],
];
