# ADR-007 — Motor de validación documental basado en hechos

**Estado: resuelto (2026-09-30).** Antes `PENDIENTE-motor-validacion-hechos.md`; renombrado en el mismo commit que implementa las respuestas.
**Origen:** `docs/reports/2026-09-30-evaluacion-motor-validacion.md` (evaluación) y `docs/reports/2026-09-30-motor-validacion-integracion.md` (implementación).
**Rama:** `spike/validation-engine-eval` (no mergeada).

Relación con otros documentos: independiente de `PENDIENTE-origen-de-magnitud.md` (motor de **capacidad instalada**, Paso 3). Comparte solo los tipos de resultado (`app/Domain/Validaciones/Resultado/`). El **envío** del trámite sigue en `PENDIENTE-edicion-hasta-envio.md` (WS-7).

---

## Decisiones

Las respuestas del owner (2026-09-30) van literales. Donde el owner delegó ("toma las decisiones tú mismo") o la respuesta dejaba un hueco, se marca **Decisión del agente**, para que se pueda revertir sin buscar en el código.

### P1. ¿Qué bloquea y cuándo corre?

**Owner:** "Sí, pero la validación debe hacerse en el último paso del flujo. El usuario completa todo el wizard y al final el motor evalúa lo que subió y señala lo incorrecto."

- Nuevo último paso **"Validación final"** (`/tramite/{escuela}/validacion`), accesible desde el resumen solo cuando todas las secciones están completas (`ResumenTramite::completo`).
- Cualquier `no_cumple` impide enviar. `advertencia` y `no_evaluable` no bloquean.
- **Decisión del agente:** el envío en sí (cambio de estado a `en_revision` y candado de edición) **no se implementa aquí**: es WS-7. La página dice si el trámite *puede* enviarse. WS-7 debe volver a ejecutar `EjecutarValidacionFinal` al enviar, no confiar en una evaluación guardada (los datos pueden cambiar después).

### P2. Nombres que no coinciden

**Owner:** "Una discrepancia siempre dispara una alerta."

- Cualquier diferencia de nombre (tokens faltantes, extra o distintos) → `advertencia`. El orden de los tokens no cuenta como diferencia (la INE imprime los apellidos primero).
- **Decisión del agente:** una diferencia en el domicilio (calle, número, colonia, municipio) también es `advertencia`. Un **código postal**, una **CURP** o un **RFC** distintos son `no_cumple`: son identificadores exactos, sin variantes legítimas de escritura.

### P3. Forma de persistir los hechos

**Owner:** "Usa una tabla tipada si es mejor en general."

- Tablas de extensión tipadas por documento, mismo patrón que `constancias_seguridad_estructural`: `credenciales_ine`, `constancias_curp`, `constancias_situacion_fiscal` (FK a `documentos_escuela`) y `certificados_numero_oficial` (FK a `documentos_plantel`).
- La tabla genérica `hechos_documento` del prototipo se eliminó. Nunca llegó a `master`, así que se borró su migración en lugar de agregar una que la tire.

### P4. Retención y cifrado

**Owner:** "Borrar los hechos, sin cifrar por ahora."

- Al resubir un documento, sus datos tipados se sobrescriben. Si la resubida no trae datos, se borran. Los datos de un archivo reemplazado nunca sobreviven. Sin cifrado en reposo.
- Las **evaluaciones** guardadas (P6) sí conservan lo que se mostró, incluidos nombres y CURP. Son la evidencia pedida. Su retención no se decidió (ver "Pendiente").

### P5. Documentos fuente de CURP y RFC

**Owner:** "Sí."

- Se agregan al catálogo `constancia_curp` y `constancia_situacion_fiscal`, ambas con `aplica_persona = 'ambas'` y `ambito = 'escuela'`.
- **Decisión del agente sobre de quién es cada documento:**
  - La **Constancia de CURP** es de la misma persona que la INE: la persona que actúa (ver P8).
  - La **Constancia de Situación Fiscal** es del contribuyente: el titular, o la persona moral.

### P6. ¿Se persiste el reporte?

**Owner:** "Sí, en PDF con formato."

- Cada ejecución de la validación final guarda un PDF (dompdf, disco privado `documentos`, `validaciones/{escuela}/reporte-{ULID}.pdf`) y una fila en `evaluaciones_validacion`. La fila guarda las filas mostradas en JSONB, para releerlas sin volver a ejecutar el motor.
- El PDF se descarga desde la página. Solo lo sirve la escuela a la que pertenece, y la Policy `view` decide el acceso.

### P7. Hechos de documentos de ámbito `plantel`

**Owner:** "No estoy seguro de entender la pregunta."

- **Resuelto por diseño, sin decisión pendiente.** La pregunta era a quién pertenecen los datos de un documento que se comparte entre varias escuelas del mismo plantel (por ejemplo, el certificado de número oficial). Con tablas tipadas (P3), los datos cuelgan de la **fila del documento**: el certificado pertenece al plantel y aplica igual a cada escuela del plantel. Hay una prueba dedicada.

### P8. Identidad en persona moral y física con gestor

**Owner:** "INE y CURP del gestor."

- `fisica_con_gestor`: la INE y la Constancia de CURP son **del gestor**. Se agregó `gestores.curp` (única modificación a una tabla del DDL v3), obligatoria en Paso 2.1 cuando hay gestor, con formato validado.
- **Decisión del agente para `moral`:** la INE y la Constancia de CURP son del **representante legal**. Se comparan contra `personas_morales.nombre_representante_legal`. Como no hay CURP declarada del representante, la CURP de la INE se compara contra la de la Constancia de CURP.

### P9. Domicilio vs. certificado de número oficial

**Owner:** "Sí."

- El certificado se captura con calle, número exterior, colonia, municipio y código postal. Se compara, parte por parte, con el domicilio del plantel de Paso 1. La normalización expande abreviaturas comunes (Av., Blvd., Prol., Cda.) e ignora palabras de relleno (Calle, Col., No.).

### P10. Idioma de identificadores

**Owner:** "Está bien." Identificadores en español, comentarios y commits en inglés.

---

## Quién es "lo declarado" para cada tipo de hecho

| Hecho | `fisica` | `fisica_con_gestor` | `moral` | Documentos que lo aportan |
|---|---|---|---|---|
| Nombre de identidad | titular | gestor | representante legal | INE, Constancia de CURP |
| CURP | titular | gestor | — (se comparan los documentos entre sí) | INE, Constancia de CURP |
| Nombre fiscal | titular | titular | razón social | Constancia de Situación Fiscal |
| RFC | titular | titular | — (no hay columna; `no_evaluable`) | Constancia de Situación Fiscal |
| Domicilio | plantel | plantel | plantel | Certificado de número oficial |

La tabla vive en código en `App\Application\Validaciones\ConstruirContextoValidacion`. Las reglas de Domain no conocen `tipo_persona`.

## Adenda 2026-09-30 — revisiones por nivel (Paso 2.4, WS-5b)

- **El motor corre también por `escuela_nivel`.** `ContextoValidacion` gana `foliosAjenos`. `TipoHecho` gana `FolioRecibo`, `TitulosAcervo` y `LaboratoriosDeclarados`. El conjunto de reglas del nivel lo elige `CatalogoReglasDocumentales::reglasDeNivel(clavesRequeridas)` según los documentos que aplican.
- **Qué bloquea:** P1 no cambia. Cualquier `no_cumple`, en la sección de la escuela o en la de cualquier nivel, impide el envío. El folio de recibo repetido en otro nivel o trámite es `no_cumple`. Las diferencias de cantidad (acervo, laboratorio) son `advertencia`, como los nombres en P2.
- **Hechos nuevos persistidos:**
  - número de títulos de la relación del acervo en `relaciones_acervo_bibliografico`, con la regla de P3 y P4;
  - el folio ya vivía en `recibos_pago_derechos` (WS-5b).
- **Lo declarado por nivel:**
  - títulos de "libros" de la biblioteca del plantel;
  - suma de laboratorios polifuncionales del plantel;
  - ambos capturados en Paso 3 y compartidos entre niveles (ADR-005).
- **Resultado guardado:** `{documental, niveles}`.

Detalle, supuestos y evidencia: `docs/reports/2026-09-30-motor-validacion-integracion.md` §7.

## Pendiente, fuera de este ADR

- **Envío y candado de edición:** WS-7 (`PENDIENTE-edicion-hasta-envio.md`).
- **RFC de persona moral:** `personas_morales` no tiene columna `rfc`. Agregarla permitiría validar el RFC de las morales.
- **Retención de evaluaciones/PDF:** cada visita a la página final crea una evaluación nueva. No hay política de limpieza.
  - Resuelto en parte (2026-10-06, `EliminarTramite`): eliminar el trámite borra sus evaluaciones y sus PDF. La política general de limpieza sigue abierta.
- **Datos declarados erróneos:** Paso 2.1 es de solo lectura una vez capturado (WS-7). Si el error está en lo declarado y no en el documento, hoy el solicitante no puede corregirlo desde el flujo.
