# PENDIENTE — Dictámenes (Protección Civil / Uso de Suelo) por nivel educativo

**Estado:** abierto. Requiere respuesta de SEDEQ (norma), no del dueño del proyecto.
**Origen:** WS-5b (Paso 2.4 "Documentos por nivel"), spec `docs/superpowers/specs/2026-09-29-ws5b-documentos-por-nivel-design.md`.

## Pregunta

Los Requisitos de Educación Básica §14–15 indican que el visto bueno de Protección Civil y el dictamen de Uso de Suelo deben estar "emitidos para el nivel educativo que se ofertará". Hoy ambos documentos se capturan **una sola vez por plantel** en el Paso 2.2, sin importar cuántos niveles pida la escuela.

¿Deben capturarse **por nivel** (un dictamen por cada `escuela_nivel`) o basta uno por plantel que cubra todos los niveles solicitados?

## Decisión provisional

Se mantienen a nivel plantel (Paso 2.2). WS-5b solo movió al nivel los documentos que son inequívocamente por nivel (Formato de Solicitud, recibo de pago de derechos, acervo bibliográfico, inventario).

## Preguntas relacionadas para SEDEQ

- `certificado_numero_oficial` sigue como documento obligatorio del plantel en 2.2 para todos los niveles, pero Requisitos Básica §16 lo hace condicional; la condición depende de niveles elegidos después de 2.2. Se suma a la pregunta existente "condición del número oficial".
- `ratificado_notario` (comodato, `acreditaciones_ocupacion_legal`): `COMPENDIO_MAESTRO` confirma que el comodato debe ratificarse ante Notario Público **solo para Básica** (diferencia real frente a Inicial). Pero `escritura_inmueble` se captura a nivel plantel, igual que `dictamen_uso_suelo` y `visto_bueno_proteccion_civil`: un mismo comodato cubre todos los niveles que solicita la escuela. Si una escuela solicita Preescolar + Primaria bajo un mismo comodato, ¿la ratificación aplica por ser Básica, o depende de cómo se resuelva este mismo PENDIENTE? `ratificado_notario` existe hoy en el Form y en la BD pero sin control en la UI; no habrá regla de obligatoriedad condicional hasta resolverlo.

## Si la respuesta es "por nivel"

- Mover `dictamen_uso_suelo` / `visto_bueno_proteccion_civil` a `ambito = escuela_nivel` en el catálogo (migración nueva, mismo patrón que la de WS-5b).
- Los conteos de 2.2 bajarían (física 8 / moral 10 / gestor 9 hoy) y las escuelas que ya completaron 2.4 tendrían que volver a cargarlos (rollout similar al de WS-5a/5b).
- Al resolverse, renombrar este archivo a `ADR-00N-dictamenes-por-nivel.md` en el mismo commit.

## Actualización (2026-10-19): captura de la acreditación de ocupación legal

Estado de la pregunta sobre `ratificado_notario`: **parcialmente resuelta**. Lo técnico quedó resuelto; lo normativo sigue abierto y no se marca como resuelto. Este archivo **no se renombra** porque quedan preguntas abiertas (dictámenes por nivel, número oficial y ratificación por nivel).

### Qué se implementó (rama `worktree-acreditacion-ocupacion`, informe `docs/reports/2026-10-19-acreditacion-ocupacion.md`)

- La ratificación se captura para **arrendamiento y comodato** (antes solo comodato, sin datos): casilla `ratificado_notario` y, si está marcada, `ratificacion_fecha`, `ratificacion_notario_nombre` y `ratificacion_notaria_numero` (columnas nullable, migración `2026_10_19_000100`).
- **Comportamiento provisional:** se captura y se guarda, **nunca se exige ni bloquea**, en ningún nivel. Si no está marcada, los tres datos se guardan como NULL. La pantalla dice que la exigencia depende del nivel educativo y que aquí solo se registra.
- La acreditación **sigue a nivel plantel** (`documentos_plantel`, una fila por plantel; sin cambios de cardinalidad ni de ámbito). Un mismo contrato cubre todos los niveles que pida la escuela; el sistema no sabe cuál nivel exige qué, así que no impone reglas por nivel durante la captura inicial.
- Decisión relacionada, **vigencia indeterminada**: casilla "El contrato no tiene fecha de terminación"; con ella la fecha de fin no se exige y se guarda NULL. Es completitud de datos, no admisibilidad.

### Fuentes y certeza (detalle en `COMPENDIO_MAESTRO`, "Fundamento normativo de la acreditación de ocupación legal")

| Regla | Fuente | Certeza |
|---|---|---|
| Arrendamiento y comodato con ratificación de contenido y firmas ante notario | Acuerdo 450, art. 23, fr. II–III (DOF 16-dic-2008) | Texto leído; solo media superior; sin reforma ni abrogación hallada (no concluyente) |
| Acuerdo 17/11/17 art. 23 (superior) no menciona ratificación ni plazo mínimo | DOF 13-nov-2017; reformas 08/04/20 y 02/02/24 solo tocan el art. 22 | Texto leído; **la ausencia no equivale a una exención**: remite a "disposiciones jurídicas aplicables" no revisadas |
| Comodato ratificado solo en Básica; vigencia mínima de un ciclo escolar | Requisitos SEDEQ de Básica (vía COMPENDIO) | Documentada; no se pudo contrastar con la página pública de SEDEQ |
| Guía DGB 2025 | URL del encargo | **No verificada** (PDF de imágenes) |

### Preguntas que siguen abiertas para SEDEQ (no se asumen)

1. Con la acreditación **compartida por plantel**: si una escuela pide Preescolar + Primaria bajo un mismo comodato, ¿la ratificación aplica por ser Básica? ¿Y para arrendamiento?
2. ¿Se acepta un contrato **sin fecha de terminación**? ¿Con qué condiciones?
3. **Vigencia mínima** del contrato por nivel (hoy solo "un ciclo escolar" en Básica; media superior y superior no la fijan en el artículo leído).
4. ¿Los datos del **Registro Público de la Propiedad** son obligatorios en la escritura para Básica e Inicial? (hoy opcionales).
5. ¿Qué **otros instrumentos** se aceptan y con qué suficiencia? (hoy se capturan metadatos; la revisión es humana).
6. ¿SEDEQ aplica los Acuerdos 450 y 17/11/17 por delegación o tiene normativa estatal propia? ¿Existe versión posterior vigente del Acuerdo 450?
7. ¿Una escritura con propietario distinto del solicitante, o un arrendador que no es propietario, qué documento de facultades acepta SEDEQ? (hoy solo se captura la referencia).

### Si la respuesta a (1) es "por nivel"

La obligatoriedad de la ratificación se resolvería en la **revisión** (o en la validación final) con una regla por nivel, sin cambiar el esquema. Mover la acreditación a `escuela_nivel` requiere una decisión arquitectónica y normativa explícita (misma consecuencia que "dictámenes por nivel": recarga de documentos de las escuelas que ya terminaron).

## Actualización (2026-10-19): vigencia del Dictamen de Uso de Suelo

Estado: **parcialmente resuelto**. Lo técnico quedó resuelto; la antigüedad de presentación y los requisitos por nivel siguen **normativamente sin resolver**. Este archivo no se renombra (siguen abiertas las preguntas de arriba). Detalle y evidencia en `docs/reports/2026-10-19-dictamen-uso-suelo.md`.

### Qué cambió

- El sistema dejó de rechazar un dictamen por tener más de 30 días desde su emisión. Ese límite (`tipos_documentos.vigencia_max_dias = 30`) nació como regla operativa confirmada por el dueño del proyecto, sin cita normativa (COMPENDIO_MAESTRO, fila 4), y se aplicaba como si fuera la **vigencia jurídica** del documento.
- Ahora se captura lo que declara el documento: "¿El documento indica una fecha de vencimiento?" → si **sí**, la fecha (obligatoria en ese caso); si **no**, se registra `sin_fecha_expresa` y **no se inventa ninguna fecha**. Solo una fecha de vencimiento declarada y ya pasada bloquea el avance.
- Los dictámenes ya capturados quedan en el estado explícito `no_capturada` (ni vigentes ni vencidos, no bloquean) y su fecha calculada se anuló por una migración reversible.
- Se agrega `condicionantes` (opcional) y la etiqueta "Uso o giro autorizado según el dictamen".

### Fuentes y certeza

| Dato | Fuente | Certeza |
|---|---|---|
| Vigencia = "la que indique el documento"; autoridad: Dirección de Desarrollo Urbano; el dictamen contiene giros y condicionantes | Municipio de Querétaro, TR-170100-017 (rev. 33, 06/nov/2024) | Texto leído; solo Municipio de Querétaro |
| Solo la factibilidad de giro tiene vigencia de 1 año calendario; el dictamen de uso de suelo no indica plazo | Municipio de Querétaro, TR-170100-044 (rev. 11) | Texto leído |
| Antigüedad máxima de 1 mes | COMPENDIO_MAESTRO (regla operativa del dueño) | **Sin fundamento verificado**; ya no bloquea |
| Requisitos por nivel de SEDEQ | Página de trámites de SEDEQ (carpeta "Servicios": PDF de requisitos de Básica, Media Superior y Superior 2024) | **No verificada**: no se pudo leer el contenido de los PDF |

### Preguntas abiertas para SEDEQ (no se asumen)

1. ¿SEDEQ exige una **antigüedad máxima** de presentación del dictamen? ¿De cuántos meses? ¿Es la misma en todos los niveles?
2. ¿Se acepta un dictamen **sin fecha de vencimiento expresa**? ¿Con qué condiciones?
3. ¿SEDEQ exige además la **factibilidad de giro** (cédula TR-170100-044) y con qué vigencia?
4. Dictámenes de **otros municipios** de Querétaro: ¿autoridad y formato aceptados?
5. ¿El uso o giro debe indicar expresamente el servicio educativo y por nivel (relación con la pregunta de "dictámenes por nivel" de arriba)?

Si SEDEQ confirma una antigüedad máxima, se implementaría como una regla **separada** (aviso o bloqueo según lo que se decida), nunca reutilizando la vigencia declarada por el documento.
