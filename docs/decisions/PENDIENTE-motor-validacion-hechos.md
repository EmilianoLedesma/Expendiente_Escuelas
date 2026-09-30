# PENDIENTE — Motor de validación documental basado en hechos

**Estado:** abierto. No adjudicado por el agente. Requiere decisión del owner (P1–P4, P6, P7, P10) y de SEDEQ (P5, P8, P9).
**Fecha:** 2026-09-30
**Origen:** evaluación de la propuesta "Validation Engine v0.1" — `docs/reports/2026-09-30-evaluacion-motor-validacion.md`.
**Prototipo:** rama `spike/validation-engine-eval` (no mergeada). Donde el prototipo tuvo que elegir algo para funcionar, se indica abajo como "Elección provisional del prototipo" — no es una decisión, se puede revertir.

Relación con otros documentos: independiente de `PENDIENTE-origen-de-magnitud.md` (motor de **capacidad instalada**, Paso 3). Este documento cubre el motor de **consistencia documental** (Paso 2). Comparten solo los tipos de resultado (`app/Domain/Validaciones/Resultado/`).

---

## P1. ¿Qué bloquea, y cuándo corre el motor documental?

El PRD dice "advertencias sin bloquear el guardado" para el motor de capacidad. No dice nada del documental.

- **A.** Todo es advertencia; nada bloquea. El reporte se muestra al solicitante y al revisor.
- **B.** `no_cumple` bloquea el **envío final**, no el avance entre pasos.
- **C.** `no_cumple` bloquea avanzar de 2.2 a 2.3 (como `ValidarVigenciaDocumentos`).

Momento: al avanzar 2.2→2.3, al envío final, o ambos.

**Elección provisional del prototipo:** A — `EjecutarValidacionDocumental` solo devuelve el reporte; no hay gancho en el wizard.
**Recomendación:** A en Paso 2 + B al envío, solo para `no_cumple` deterministas (documento faltante, CURP distinta). Nunca bloquear por `advertencia` ni por `no_evaluable`.

## P2. Nombres que no coinciden tras normalizar

- **A.** `advertencia` (revisión humana).
- **B.** `no_cumple`.
- **C.** Similitud difusa (Levenshtein/fonética) con umbral → `cumple` si supera el umbral.

**Elección provisional del prototipo:** A. Orden de tokens distinto (apellidos primero, como imprime la INE) cuenta como `cumple`.
**Recomendación:** A. C introduce un umbral que nadie ha validado, en un dato de identidad.

## P3. Forma de persistir los hechos

- **A.** Tabla genérica `hechos_documento` (`tipo_hecho` con `CHECK` de catálogo cerrado, `valor TEXT`).
- **B.** Tabla de extensión tipada por documento (p. ej. `ine_datos(documento_escuela_id, nombre, curp, anio_vigencia)`), igual que `constancias_seguridad_estructural` y `acreditaciones_ocupacion_legal`.

Ninguna de las dos está en el DDL v3; ambas son tablas nuevas.

**Elección provisional del prototipo:** A (es lo que pedía la propuesta evaluada).
**Consideración:** B es más coherente con el esquema existente y da tipos/`CHECK` por campo; A es más simple de extender a extractores futuros. El reemplazo de archivo en la misma fila (`updateOrCreate`) afecta a ambas: B tendría que resetear sus columnas al reemplazar; A lo resuelve atando el hecho a `archivo_path`.

## P4. PII: retención y cifrado

Los hechos contienen nombre y CURP (misma clase de dato que `personas_fisicas`, que hoy está en claro).

- Retención: ¿se conservan los hechos de archivos reemplazados (historial de auditoría) o se borran al reemplazar?
- Cifrado en reposo: ¿se cifra (cast `encrypted`) esta tabla, `personas_fisicas`, ambas, ninguna?

**Elección provisional del prototipo:** los hechos de archivos reemplazados se conservan; recapturar un dato del mismo archivo sobrescribe el valor (sin historial de correcciones); todo en claro.

## P5. Documentos fuente de CURP y RFC (SEDEQ)

El catálogo `tipos_documentos` no incluye Constancia de CURP ni Constancia de Situación Fiscal. Sin ellos no hay fuente documental para el RFC, y la CURP solo sale de la INE.

¿Se agregan al catálogo? Es decisión normativa, no técnica.

**Elección provisional del prototipo:** no se agregan; no hay regla de RFC.

## P6. ¿Se persiste el reporte?

El reporte se recalcula al vuelo. Los datos declarados de Paso 2.1 son editables, así que recalcular después no reproduce lo que el sistema vio en el momento del envío.

- **A.** No persistir (prototipo).
- **B.** Persistir el reporte completo (JSON) al envío final.
- **C.** Persistir solo huella (hash) + versión de reglas.

## P7. Hechos de documentos de ámbito `plantel`

Los documentos de plantel (escritura del inmueble, dictamen…) se comparten entre escuelas del mismo plantel (ver `PENDIENTE-plantel-solicitante-cardinalidad.md`). `hechos_documento.escuela_id` los ataría a una escuela.

¿Los hechos de un documento de plantel pertenecen al plantel (con `plantel_id`) o a cada escuela?

**Elección provisional del prototipo:** solo se registran hechos de documentos de ámbito `escuela`; `RegistrarHechoDocumento` rechaza los de plantel.

## P8. Identidad en persona moral y física con gestor (SEDEQ + owner)

El catálogo tiene un solo documento `ine` con `aplica_persona = 'ambas'`.

- Persona moral: ¿la INE subida es la del representante legal? `personas_morales` no guarda su CURP.
- Física con gestor: ¿se sube la INE del titular, la del gestor, o ambas? Hoy hay un solo slot.

**Elección provisional del prototipo:** para `moral` y `fisica_con_gestor` las reglas de nombre y CURP devuelven `no_evaluable` con un mensaje que cita esta pregunta. No se eligió a quién comparar.

## P9. Domicilio contra el certificado de número oficial

La norma (COMPENDIO §Paso 2) pide coincidencia de **nombre y domicilio** en todos los documentos. El domicilio no está en el prototipo y requiere su propia normalización (abreviaturas "C.", "Av.", "No.", "S/N", colonias).

¿Entra al alcance del MVP?

## P10. Idioma de los identificadores en el código nuevo

La tarea pidió "English for code". El dominio existente usa identificadores en español (`Validaciones`, `CalculadoraRequerimiento`, `RegistrarDocumento`).

**Elección provisional del prototipo:** identificadores en español (coherencia con `app/Domain/Validaciones/`, que era el punto de la evaluación §2); comentarios y commits en inglés. Si el owner prefiere identificadores en inglés, es un renombrado mecánico mientras la rama no se mergee.
