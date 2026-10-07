# WS-5c — Formato de Solicitud con la estructura oficial (2026-10-07)

## Qué se pidió

El propietario entregó el documento oficial de SEDEQ ("FORMATO DE SOLICITUD EDUCACIÓN BÁSICA", .docx) y pidió construirlo con la información que captura el solicitante.

## Qué se hizo

- `resources/views/pdf/formato-solicitud.blade.php` se reescribió siguiendo el documento oficial: logo de la Secretaría de Educación, título, fecha ("Santiago de Querétaro, Qro. a …"), bloque del destinatario, párrafo de apertura con fundamento legal, tabla del propietario (persona física **o** persona moral, solo la que aplica), las tres declaraciones bajo protesta, sanciones, aceptación de notificación electrónica y línea de firma.
- Mapeo de datos capturados:
  - (1) quien suscribe: nombre de la persona física, o `nombre_representante_legal` si es moral;
  - (2) `domicilio_notificaciones`; (3) `persona_autorizada_recoger`;
  - (4) nombre del nivel educativo; (5) horario = `turno`; alumnado = `tipo_alumnado`;
  - persona física: nombre, fecha de nacimiento, RFC, CURP;
  - persona moral: razón social, escritura constitutiva (número y fecha), notario (nombre, número, ciudad), folio y fecha de inscripción en el registro público.
- Todo dato no capturado se imprime como línea en blanco para llenar a mano.
- El logo del .docx se copió a `public/img/formato-logo.png`.
- `FormatoSolicitudPdf` solo cambió su comentario (ya no dice que la estructura oficial "llega con WS-5c").

## Qué se quitó

- La hoja "Instrucciones de llenado" (los datos ya se llenan solos).
- El listado de la terna de nombres propuestos, que el formato oficial no incluye.

## Pruebas y evidencia

- `FormatoSolicitudPdfTest`: una prueba actualizada y dos nuevas (persona física con sus datos; persona moral con escritura, notario y registro). Primero fallaron con la vista anterior y luego pasaron.
- Suite completa: 1041/1041 (3366 aserciones), Pint limpio.
- Se generaron dos PDF de muestra con datos inventados (`formato-solicitud-fisica.pdf`, `formato-solicitud-moral.pdf`) para revisión del propietario. No se pudo revisar el aspecto visual como imagen (sin poppler); la distribución está pendiente de confirmar.

## Lo que queda pendiente

- El número de escritura con la que se acredita al representante legal no tiene campo en la base: se imprime una línea en blanco. Agregarlo sería un cambio de esquema que debe autorizar el propietario.
- "Inscrita en" imprime solo `folio_registro_publico`; la base no guarda la autoridad ante la que se inscribió.
- `fisica_con_gestor` se imprime como persona física; los datos del gestor no aparecen en el formato.
- Confirmar con el propietario el aspecto del PDF.

## Cambios relacionados del mismo día

- Paso 1: código postal, teléfono y número interior solo aceptan dígitos (validación en pantalla y, para número interior, regla en el servidor). Falta que `IniciarTramiteNuevo` valide también `numero_int`.
- Eliminar trámite: la confirmación pasó de `<details>` a un modal `<dialog>` con animación, sin desbordamiento horizontal y con estados hover. Requiere JavaScript para abrirse. El texto no se partía porque el `<dialog>` heredaba `white-space: nowrap` de la celda de la tabla.
