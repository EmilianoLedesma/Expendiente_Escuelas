# Evaluación de la propuesta "Validation Engine" (v0.1) contra el código real

> **Actualización (2026-09-30, misma rama):** las preguntas abiertas de este informe quedaron resueltas en `docs/decisions/ADR-007-motor-validacion-hechos.md` (antes `PENDIENTE-motor-validacion-hechos.md`) y se implementaron según `docs/reports/2026-09-30-motor-validacion-integracion.md`. Este informe se conserva como registro de la evaluación inicial; lo que dice sobre `hechos_documento`, `RegistrarHechoDocumento` y las reglas `NombreTitularCoincide`/`CurpCoincide` quedó reemplazado.

**Fecha:** 2026-09-30
**Rama:** `spike/validation-engine-eval` (desde `master` en `cd91ffe`; no mergeada, no se mergea desde esta sesión)
**Clasificación de la tarea:** spike (evaluación + prototipo mínimo). El prototipo es evidencia para decidir, no una feature lista para `master`.
**Decisiones abiertas:** `docs/decisions/PENDIENTE-motor-validacion-hechos.md`

---

## 0. Resumen ejecutivo

La propuesta acierta en el principio central — **las reglas consumen hechos, nunca PDFs** — y en separar extracción de validación. Eso se adopta.

Pero choca con el código real en cinco puntos que no son cosméticos:

1. **Ya existe un Motor de Validación** (`app/Domain/Validaciones/`, tabla `reglas_validacion`, `CalculadoraRequerimiento`) con otro propósito (capacidad instalada), otro momento de ejecución (una vez, al cierre de Paso 3) y otra fuente de reglas (datos). Crear `app/Domain/Validation/` en paralelo duplicaría el concepto con otro idioma. **Se adapta:** el motor documental vive en `app/Domain/Validaciones/Documental/`, y los tipos de resultado en `app/Domain/Validaciones/Resultado/` para que el motor de capacidad los reutilice después.
2. **La extracción de texto de PDF para INE no es realista.** Una INE subida es casi siempre una foto o un escaneo (imagen dentro de un PDF), sin capa de texto. `spatie/pdf-to-text`/`smalot/pdfparser` devolverían cadena vacía. **Se rechaza como fuente de hechos de Fase 1.** La fuente realista de Fase 1 es la **captura estructurada** que el COMPENDIO ya exige ("datos estructurados + PDF de respaldo", §Paso 2.2), que para la INE hoy no existe.
3. **PASS/FAIL binario castiga al solicitante por fallos del sistema.** El PRD dice explícitamente "mostrar advertencias claras... **sin bloquear el guardado**". Un hecho faltante (extracción fallida) no es incumplimiento del solicitante. **Se adapta:** cuatro estados — `cumple`, `advertencia`, `no_cumple`, `no_evaluable` — y la decisión de *bloquear* no vive en las reglas sino en la capa Application (en el prototipo, nada bloquea).
4. **Las reglas propuestas no tienen de dónde leer.** El esquema real no guarda CURP ni RFC del representante de una persona moral, ni datos de ningún tipo para la INE (se sube como "documento simple", solo PDF). "RFC matches SAT record" requiere una consulta al SAT que no existe. **Se adapta el alcance** a lo que el esquema sí sostiene: nombre del titular (física) y CURP (física).
5. **`Fact.application_id` no tiene referente.** No hay tabla "application"; el expediente es `escuela_niveles`, pero los documentos de Paso 2 cuelgan de `escuelas`/`planteles`. Además, `RegistrarDocumento` **reemplaza el archivo en la misma fila** (`updateOrCreate`), así que un hecho atado solo al `id` del documento sobreviviría al reemplazo del archivo y validaría un archivo que ya no existe. **Se adapta:** el hecho se ata a `escuela_id` + `tipo_documento_id` + `archivo_path` (ruta única por subida, ULID desde WS-2.3), y los hechos de un archivo ya reemplazado se descartan como obsoletos.

**Arquitectura recomendada** (§11) y **prototipo implementado** (§12) siguen estas adaptaciones.

---

## 1. Lo leído antes de escribir esto

- `CLAUDE.md` completo.
- `docs/decisions/` completo, con la línea `Estado:` de cada archivo:
  - `ADR-001` Aceptado (+ nota 2026-09-23), `ADR-002` Aceptado, `ADR-003` resuelto, `ADR-004` resuelto, `ADR-005` resuelto, `ADR-006` Aceptado.
  - `PENDIENTE-origen-de-magnitud` **diseño abierto** (relevante: es el diseño del motor de capacidad), `PENDIENTE-edicion-hasta-envio` abierto, `PENDIENTE-inmueble-relectura-datos` abierto, `PENDIENTE-matriz-espacios-por-nivel` abierto, `PENDIENTE-perfiles-trabajador-social-prefecto` abierto, `PENDIENTE-personal-condicionado-por-grado` abierto, `PENDIENTE-plantel-solicitante-cardinalidad` abierto, `PENDIENTE-umbral-educacion-fisica` abierto. Ninguno tiene prefijo desactualizado.
- `docs/PRD_Sistema_Incorporacion_MVP.md` (en especial "Motor de Validación de Capacidad Instalada" y §7 No funcionales).
- `docs/COMPENDIO_MAESTRO_Sistema_Incorporacion.md` §Paso 2 (tabla de documentos, regla de validación cruzada, identificaciones aceptadas), §5 y §7.
- `docs/progress.md` (Decisions Log: entrada "Motor de Validación: MVP scope and timing", rediseño de `reglas_validacion`).
- **`docs/ddl_sistema_incorporacion_v3.sql` no está en el repositorio**: se sacó del historial a propósito el 2026-09-04 (ver `progress.md`) y vive solo en disco local del owner. Se usaron como sustituto las migraciones `2026_01_01_*`, que son su port 1:1 verbatim (`DB::unprepared` con el `CREATE TABLE` exacto). Si el DDL local difiere de las migraciones, esta evaluación no lo ve.
- Código: `app/Domain/Validaciones/{Engine/ValidacionCapacidadService (stub vacío), Regla/CalculadoraRequerimiento}`, `app/Domain/Personal/RegistroPersonalCompleto`, `app/Application/Documentos/*` (`DocumentosCompletos`, `DocumentosCapturados`, `RegistrarDocumento`, `ValidarVigenciaDocumentos`, `ObtenerDocumentoCapturado`), `app/Infrastructure/Documentos/*` (`AlmacenDocumentos`, `AlmacenDocumentosLocal`, `ChecklistDocumentalService` stub vacío), `ReglasValidacionSeeder`, `TiposDocumentosSeeder`, migraciones de `tipos_documentos`, `documentos_{plantel,escuela,escuela_nivel}`, `responsables_legales`, `personas_fisicas`, `personas_morales`, `gestores`, `reglas_validacion`, `app/Livewire/Tramite/Paso2Documentos.php`, `tests/Architecture/*`, `phpstan.neon`.
- No se dependió de ninguna rama sin mergear.

---

## 2. Choque `Domain/Validation` vs. `Domain/Validaciones` y `reglas_validacion`

**Veredicto: ADAPTAR.**

Estado real:

- `app/Domain/Validaciones/Regla/CalculadoraRequerimiento.php` — código real, puro, probado; calcula requerimientos numéricos a partir de filas de `reglas_validacion`.
- `app/Domain/Validaciones/Engine/ValidacionCapacidadService.php` — stub vacío, esperando `PENDIENTE-origen-de-magnitud`.
- `reglas_validacion` — 28 filas sembradas, `CHECK (tipo_regla IN ('superficie','personal','mobiliario','infraestructura'))` y `tipo_calculo` de 9 valores numéricos. Es un catálogo de **ratios normativos**, no de reglas de consistencia documental.

Problemas de la propuesta tal cual:

- **Dos raíces para el mismo concepto** ("validación") en dos idiomas. Todo el dominio del proyecto está en español (`Validaciones`, `Personal`, `RegistrarDocumento`, `CalculadoraRequerimiento`); un `Domain/Validation` en inglés sería la primera excepción y obligaría a cada lector a preguntarse si son dos motores o uno a medio renombrar.
- **Meter las reglas documentales en `reglas_validacion`** (que es lo que la sección 12 "Configurable Rules — rules stored in database" sugiere a futuro) requeriría cambiar su `CHECK` y agregar columnas sin sentido para ratios. Las reglas documentales no son parametrizables por número; son lógica. **Se rechaza** guardarlas ahí.
- Son **dos motores distintos** con distinto disparador: el de capacidad corre una vez al cerrar Paso 3 (confirmado por el usuario 2026-09-11, `progress.md`); el documental tiene sentido en Paso 2 (tras 2.2) y al enviar. Fusionarlos en un solo `run()` los acoplaría.

Recomendación:

```
app/Domain/Validaciones/
├── Engine/ValidacionCapacidadService.php      (existente, stub — no se toca)
├── Regla/CalculadoraRequerimiento.php          (existente — no se toca)
├── Resultado/                                  (NUEVO, compartible por ambos motores)
│   ├── EstadoResultado.php   (enum: cumple / advertencia / no_cumple / no_evaluable)
│   ├── ResultadoRegla.php
│   └── ReporteValidacion.php
└── Documental/                                 (NUEVO, motor de consistencia documental)
    ├── Hecho.php, OrigenHecho.php, TipoHecho.php
    ├── ContextoValidacion.php
    ├── ReglaDocumental.php   (contrato)
    ├── MotorValidacionDocumental.php
    ├── NormalizadorNombre.php
    └── Reglas/{NombreTitularCoincide, CurpCoincide, DocumentosRequeridosPresentes}.php
```

Nombres en español por coherencia con el dominio existente. Los comentarios del código nuevo van en inglés, por instrucción explícita de esta tarea ("English for code") — ver pregunta abierta P10 sobre si eso debe aplicar también a identificadores.

---

## 3. ADR-001 y la regla PHPat (Domain no depende de `Illuminate\*`)

**Veredicto: ADOPTAR el principio, ADAPTAR la ubicación de la factory y del "repository".**

- La propuesta dice "Rules do not perform database queries" — compatible con ADR-001. Pero coloca `ValidationContextFactory` en Application (correcto) y un "repository" de hechos sin decir dónde. En este proyecto **no hay capa Repository**: Application lee Eloquent directamente (`DocumentosCompletos`, `DocumentosCapturados`), por decisión explícita (CLAUDE.md: "not the Controllers/Services/Repositories/Models split"). **Se rechaza** introducir un repository; la construcción del contexto es una clase de Application que lee Eloquent, como el resto.
- `Hecho`, `ContextoValidacion`, reglas, resultado y reporte **no usan nada de Laravel**: ni `Collection`, ni `Carbon`, ni `Str::ascii`. La normalización de nombres usa `ext-intl` (`Normalizer`) — una extensión de PHP, no `Illuminate` — y así lo acepta la regla PHPat. Verificado con PHPStan (ver §13).
- El contrato de extracción `FactExtractor::extract(Document $document)` de la propuesta recibe un `Document` que en este código sería un modelo Eloquent (`DocumentoEscuela`/`DocumentoPlantel`) — por eso **el contrato de extractor pertenece a `app/Infrastructure/Documentos/`**, no a Domain. No se implementa en el prototipo (ver §5).
- El motor de capacidad (ADR-001 "El Motor de Validación síncrono (no job en cola) es la dirección actual") — el documental también es síncrono; se evalúa en proceso desde un caso de uso.

---

## 4. Modelo de documentos y almacenamiento existente

**Veredicto: ADAPTAR (el modelo `Fact` de la propuesta no encaja con el almacenamiento real).**

Hechos del código:

- Los documentos viven en **tres tablas** según `tipos_documentos.ambito`: `documentos_plantel` (dictamen, constancia, escritura del inmueble…), `documentos_escuela` (INE, acta de nacimiento, formato de solicitud…), `documentos_escuela_nivel` (Paso 2.4, aún sin código). No hay una tabla `documents` única a la que apuntar con un FK `source_document`.
- `RegistrarDocumento` hace `updateOrCreate` sobre `(escuela_id|plantel_id, tipo_documento_id)`: **al reemplazar un archivo, la fila conserva su `id`** y solo cambia `archivo_path`. Cada subida genera una ruta nueva y única (`{ambito}/{owner}/{clave}-{ULID}.pdf`, WS-2.3) y el archivo anterior se borra tras el commit.
- Ya existe el patrón "tabla de extensión" para datos estructurados de un documento: `constancias_seguridad_estructural` y `acreditaciones_ocupacion_legal`, ambas con FK a `documentos_plantel`. Es decir, **el proyecto ya guarda "hechos" de documentos**, solo que como columnas tipadas por documento, no como pares clave/valor.
- `ChecklistDocumentalService` (Infrastructure) es un stub vacío sin uso.

Consecuencias para el diseño:

- `source_document` como texto ("INE") no permite saber **qué archivo** produjo el hecho. Un `documento_id` tampoco basta (sobrevive al reemplazo). **Lo que identifica el archivo es `archivo_path`.** El prototipo guarda `escuela_id`, `tipo_documento_id` y `archivo_path`, y al construir el contexto descarta hechos cuyo `archivo_path` ya no es el vigente del documento (prueba dedicada).
- `application_id` → `escuela_id` (los documentos de Paso 2 son de escuela/plantel, no de `escuela_nivel`). Los documentos de ámbito `plantel` se comparten entre escuelas del mismo plantel (ver `PENDIENTE-plantel-solicitante-cardinalidad`); el prototipo solo produce hechos de documentos de ámbito `escuela` (INE), y deja abierto cómo tratar hechos de documentos de plantel (P7).
- **¿Tabla genérica clave/valor o columnas tipadas como las tablas de extensión?** Para la INE, lo coherente con el patrón existente sería una tabla de extensión `ine_datos` (nombre, CURP, vigencia…). La propuesta pide una tabla genérica de hechos. El prototipo usa la **genérica** (`hechos_documento`) porque es lo que se evalúa, con `tipo_hecho` restringido por `CHECK` a un catálogo cerrado (no texto libre), pero la elección está abierta (P3).

### Justificación de la migración nueva frente al DDL

`hechos_documento` **no existe en el DDL v3** (verificado contra sus migraciones 1:1). Es una tabla nueva, no una modificación de ninguna existente: no altera ninguna de las 44 tablas ni sus `CHECK`. Precedentes de tablas agregadas con autorización fuera del DDL: `pasos_captura`, `escuela_nivel_pasos`, `solicitantes`. La tabla se escribe en el mismo estilo que el resto (`DB::unprepared` con `CREATE TABLE` en SQL crudo, `CHECK` explícitos). **Vive solo en esta rama spike**; si el owner no aprueba P3, se descarta con la rama. Solo se ejecutó contra la BD local de pruebas `sedeq_incorporacion_testing` de este contenedor, nunca contra dev.

---

## 5. ¿Es realista la extracción solo-texto de PDF? (INE es foto o escaneo)

**Veredicto: RECHAZAR como fuente de hechos de Fase 1 para identificaciones; ADAPTAR la Fase 1 a captura estructurada.**

- La INE es una tarjeta física. Lo que el solicitante sube es una **foto del celular convertida a PDF o un escaneo**: el PDF contiene una imagen, sin capa de texto. `pdftotext` (lo que envuelve `spatie/pdf-to-text`) y `smalot/pdfparser` extraen **texto**, no leen imágenes: devolverían vacío. La sección 7 de la propuesta ("Initial scope: INE, CURP, RFC") es, para la INE, la única de las tres que casi nunca tendrá texto.
- La **Constancia de CURP** descargada de gob.mx y la **Constancia de Situación Fiscal** (RFC) del SAT sí suelen ser PDFs nativos con texto. Pero **ninguna de las dos está en el catálogo `tipos_documentos`** (13 claves: `ine`, `acta_nacimiento`, `escritura_poder_facultades`, …). Extraer CURP/RFC de documentos que el sistema no pide no tiene sentido; agregarlos al catálogo es una decisión normativa (P5).
- El acta de nacimiento puede venir del portal gob.mx (texto) o ser un escaneo de papel (imagen). Mismo problema, a medias.
- Además, `spatie/pdf-to-text` depende del binario `pdftotext` (poppler-utils) en el servidor — una dependencia de sistema que hoy no existe en el despliegue ni en CI.
- "Text-search based extraction is sufficient": el texto de un PDF nativo no tiene orden de lectura garantizado; etiquetas como "NOMBRE" pueden quedar separadas de su valor. Es frágil incluso donde hay texto.

**Qué sí es realista en Fase 1:** el patrón de captura que el COMPENDIO ya fijó para Paso 2.2 — *"captura de campos de datos estructurados + carga del archivo PDF de respaldo (no es solo 'subir archivo')"* — aplicado a la INE, que hoy se captura como documento simple sin campos. El solicitante transcribe nombre y CURP que aparecen en su INE; el sistema los guarda como hechos del documento (`metodo = 'captura_manual'`) y el motor compara contra lo declarado en 2.1. El cotejo real contra el original ya ocurre en la visita de verificación (COMPENDIO: "Original se coteja en la visita de verificación"). Esto no prueba que el PDF diga lo mismo — solo detecta inconsistencias internas de lo capturado, y deja el hecho con trazabilidad al archivo exacto para que un revisor humano lo contraste. Es más débil que una extracción, pero es honesto y funciona con escaneos.

La columna `metodo` deja el camino abierto: cuando exista un extractor (texto, OCR, servicio externo), sus hechos entran con otro `metodo` y **las reglas no cambian** — que es el criterio de éxito 6 de la propuesta, que sí se conserva.

---

## 6. Normalización de nombres (acentos, orden de apellidos)

**Veredicto: ADAPTAR (la propuesta compara `JUAN PEREZ GOMEZ` = `JUAN PEREZ GOMEZ` literal; eso produce falsos negativos seguros).**

Hechos del dominio:

- `personas_fisicas.nombre` es **un solo campo `VARCHAR(200)`** — no hay nombre/apellido paterno/apellido materno separados. No se puede reordenar con certeza.
- La INE imprime **apellidos primero** ("PEREZ / GOMEZ / JUAN") en renglones separados, en mayúsculas y sin acentos.
- Un solicitante escribe "Juan Pérez Gómez" o "Pérez Gómez Juan", con acentos, mayúsculas/minúsculas, dobles espacios.
- La Ñ: la INE la imprime; la CURP la sustituye por X. En nombres no hay que llegar a eso, pero sí tratarla igual en ambos lados.
- Partículas ("DE LA", "DEL", "Y"), nombres compuestos ("MARÍA DE LOS ÁNGELES"), apellido único, y personas que omiten su segundo nombre en un formulario.

Normalización implementada (`NormalizadorNombre`, determinista, sin Laravel):

1. Descomposición Unicode NFD y eliminación de marcas diacríticas (`á→a`, `ñ→n`, `ü→u`) vía `ext-intl`.
2. Mayúsculas.
3. Todo lo que no sea letra o espacio → espacio (puntos, guiones, comas).
4. Colapsar espacios y recortar.

Comparación implementada (`NombreTitularCoincide`):

- Igual tras normalizar → `cumple`.
- Mismos tokens en distinto orden (multiconjunto igual) → `cumple`, con detalle `orden_distinto: true`. Justificación: el orden de la INE (apellidos primero) es por diseño; exigir orden causaría un `no_cumple` para prácticamente todo solicitante que escriba su nombre de forma natural.
- Cualquier otra diferencia (token faltante, extra o distinto) → **`advertencia`, no `no_cumple`**, con los tokens de cada lado en `detalles`. Justificación en §7.

No se implementa: similitud difusa (Levenshtein, fonética). Es determinista, pero introduce un umbral arbitrario que nadie ha validado, y un "casi igual" en identidad es precisamente lo que un revisor humano debe ver. Se deja como pregunta (P2).

---

## 7. FAIL falso vs. WARNING — impacto en el solicitante

**Veredicto: RECHAZAR el binario PASS/FAIL como único eje; ADAPTAR a cuatro estados y separar "estado" de "bloqueo".**

- El PRD es explícito: *"Mostrar advertencias claras al solicitante cuando una validación no se cumpla, **sin bloquear el guardado** (el expediente puede quedar 'con observaciones')."* La propuesta no menciona esto.
- La prueba de la propuesta *"fails when required fact is missing → FAIL"* es el caso más dañino: el hecho falta porque **el sistema** no pudo extraerlo (escaneo sin texto) o porque todavía no se capturó. Reportarlo como FAIL es culpar al solicitante de un límite de la herramienta. En el prototipo eso es **`no_evaluable`**, con un mensaje que dice qué hecho falta.
- Un nombre que no coincide tras normalizar puede ser un segundo nombre omitido, un apellido de casada, un error de captura — no necesariamente una suplantación. Un `no_cumple` que bloquee el trámite por esto genera soporte, abandono o que el solicitante "ajuste" el dato para pasar la validación (degradando el valor de la validación misma). Se reporta como `advertencia` y la decisión es de un humano.
- **CURP distinta** sí es `no_cumple`: es un identificador exacto de 18 caracteres, sin variaciones legítimas de escritura. Aun así, `no_cumple` **no bloquea** en el prototipo.
- **Documento requerido faltante** es `no_cumple`: es un hecho determinista (fila en BD o no). Ya lo bloquea `DocumentosCompletos` en la compuerta de Paso 2; la regla solo lo refleja en el reporte.

Separación de responsabilidades: **la regla dice qué encontró; la capa Application decide qué hacer** (mostrar, bloquear, mandar a revisión). En el prototipo, `EjecutarValidacionDocumental` solo devuelve el reporte; nada bloquea. Si algún `no_cumple` debe bloquear el envío es decisión del owner (P1).

---

## 8. PII en los hechos guardados y auditabilidad

**Veredicto: ADAPTAR (la propuesta no trata PII; su `confidence` no aplica a Fase 1).**

- Los hechos contienen **CURP y nombre completo** — datos personales bajo la LFPDPPP / Ley General de Protección de Datos Personales en Posesión de Sujetos Obligados (SEDEQ es sujeto obligado). La misma clase de dato ya vive en claro en `personas_fisicas.curp`, así que la tabla nueva **no aumenta la categoría de riesgo**, pero sí la superficie: una segunda copia.
- El prototipo guarda el valor **tal como se capturó** (no normalizado), para que la auditoría muestre exactamente qué dijo la fuente; la normalización ocurre al comparar, nunca al guardar.
- **Un hecho por (escuela, documento, archivo, tipo de hecho)** (`UNIQUE` en la tabla). Recapturar el mismo dato **para el mismo archivo** lo corrige (`updateOrCreate`, queda `updated_at`) — pensado para que el solicitante arregle un error de dedo antes de enviar; el valor anterior de esa corrección **no** se conserva. Un archivo nuevo produce hechos nuevos (otra `archivo_path`); los del archivo anterior quedan como historial y se ignoran al validar. Esto da auditoría por archivo ("qué dato, de qué archivo, cuándo, por qué método") a costa de retener PII de archivos ya reemplazados. Si se requiere historial de cada corrección, habría que volver la fila inmutable (insertar siempre). Política de retención/borrado abierta (P4).
- **Cifrado en reposo** (p. ej. cast `encrypted` de Eloquent) no se aplica en el prototipo: impediría consultar por valor e introduce rotación de llaves; tampoco `personas_fisicas` lo aplica hoy. Queda como decisión de conjunto, no de esta tabla (P4).
- `confidence` de la propuesta: sin extractor probabilístico no significa nada — una captura manual no tiene "confianza 0.87". **No se agrega** la columna; si llega OCR, se agrega con su migración y su semántica definida entonces.
- **El reporte de validación no se persiste** en el prototipo (se calcula al vuelo, determinista a partir de hechos inmutables + datos declarados). Si se requiere evidencia histórica ("qué vio el sistema cuando el solicitante envió"), hay que persistir el reporte o su huella; los datos declarados en 2.1 *sí* son editables, así que recalcular después no reproduce lo de entonces (P6). Las columnas `documentos_*.estado_validacion`/`observaciones` son para el revisor humano de SEDEQ (fuera de MVP) — **no** se escriben desde el motor, para no mezclar resultado automático con dictamen humano.

---

## 9. Los hechos normativos: CURP, RFC, INE, identidad del representante

**Veredicto: ADAPTAR el alcance de reglas a lo que el esquema sostiene; RECHAZAR `RFCMatchesSATRecord` y `DocumentNotExpired`-para-INE en esta fase.**

Base normativa real (COMPENDIO §Paso 2): *"Todos los documentos deberán coincidir en los datos de identificación de la persona física o moral solicitante, así como el domicilio oficial (tal cual el certificado de número oficial)."* y *"El sistema debería validar automáticamente consistencia de nombre/domicilio entre documentos cargados."* — la norma habla de **nombre y domicilio**, no de CURP/RFC.

| Hecho | ¿Qué declara hoy el solicitante? | ¿Qué documento lo respalda? | Estado |
|---|---|---|---|
| Nombre (física) | `personas_fisicas.nombre` | INE, acta de nacimiento | **Regla implementada** (INE). Acta: mismo patrón, no implementada. |
| CURP (física) | `personas_fisicas.curp` (opcional, `VARCHAR(18)`, sin validación de formato en captura) | INE (la trae impresa desde 2014); acta de nacimiento reciente | **Regla implementada** (INE). |
| RFC (física) | `personas_fisicas.rfc` | **Ninguno del catálogo.** La INE no trae RFC; la Constancia de Situación Fiscal no se pide. | **Rechazada.** No hay contra qué comparar. "SAT record" implica integración externa, fuera de alcance y del principio determinista-local. |
| Nombre del representante (moral) | `personas_morales.nombre_representante_legal` | INE del representante; escritura/poder de facultades | **No implementada** — ver P8: ¿la INE subida en una moral es la del representante? El catálogo tiene un solo slot `ine` `ambas`. |
| CURP del representante (moral) | **No se captura** (no hay columna) | INE | **No evaluable** — la regla lo reporta así. |
| Nombre del gestor (`fisica_con_gestor`) | `gestores.nombre` | ¿INE de quién? | **No evaluable** — la regla lo reporta explícitamente como pendiente de decisión (P8). |
| Razón social (moral) | `personas_morales.razon_social` | acta constitutiva | No implementada. |
| Domicilio | `responsables_legales.domicilio_notificaciones`, `planteles.*` | certificado de número oficial | No implementada — es la otra mitad de la norma y probablemente la de mayor valor; necesita normalización de domicilios (más difícil que nombres). P9. |
| Vigencia de INE | — | INE (año de vigencia impreso) | No implementada. Es determinista y útil, pero requiere capturar el año de vigencia (hecho nuevo). La vigencia de Dictamen/Constancia **ya** la cubre `ValidarVigenciaDocumentos`; un `DocumentNotExpired` genérico la duplicaría. |

`DocumentosRequeridosPresentes` se implementa **reutilizando** `DocumentosCompletos::clavesAplicables()`/`clavesPendientes()` — no se crea una segunda definición de "qué documentos aplican" (el defecto de duplicación ya ocurrió antes, ver `DocumentosCapturados`).

---

## 10. Veredicto por sección de la propuesta

| § | Tema | Veredicto | Motivo corto |
|---|---|---|---|
| 1 | Resumen / independencia de Filament, Livewire, OCR, PDF | **Adoptar** | Coincide con ADR-001 y PHPat. |
| 2.1 | Facts over documents | **Adoptar** | Correcto y central. |
| 2.2 | Determinismo PASS/FAIL | **Adaptar** | Determinismo sí; binario no (§7). |
| 2.3 | Extensibilidad sin tocar reglas | **Adoptar** | `metodo` en el hecho + contrato de regla. |
| 3.1 | Flujo alto nivel | **Adaptar** | Primer eslabón es captura estructurada, no "Document Processing" (§5). |
| 3.2 | `Fact` (id, application_id, type, value, source_document, confidence) | **Adaptar** | `escuela_id` + `tipo_documento_id` + `archivo_path` + `metodo`; sin `confidence` (§4, §8). |
| 3.2 | `Rule`, sin BD ni archivos | **Adoptar** | |
| 3.2 | `ValidationResult` PASS/FAIL/WARNING | **Adaptar** | + `no_evaluable` (§7). |
| 3.2 | `ValidationReport` | **Adoptar** | |
| 4 | Estructura `Domain/Validation`, `Application/Validation`, `Infrastructure/Documents` | **Adaptar** | `Domain/Validaciones/{Documental,Resultado}`, `Application/Validaciones`, `Infrastructure/Documentos` (existente) (§2). |
| 5 | `ValidationContext` | **Adoptar** | Construido en Application. |
| 6 | `FactExtractor::extract(Document)` | **Adaptar** | Vive en Infrastructure; no se implementa hasta que exista una fuente real (§5). |
| 7 | Extracción solo-texto (INE, CURP, RFC) | **Rechazar** (para INE) | Imágenes sin capa de texto; CURP/RFC no están en el catálogo (§5). |
| 8 | Ejecución | **Adoptar** | Síncrona, desde un caso de uso. |
| 9 | TDD | **Adoptar** | Ya es ley del proyecto; + prueba de no-vacuidad por regla. |
| 9 | "missing fact → FAIL" | **Rechazar** | → `no_evaluable` (§7). |
| 10 | Pirámide, 90%+ | **Adaptar** | Unit para Domain; Feature (Postgres) para Application. Sin meta numérica de cobertura: no hay driver de cobertura en CI. |
| 11 | MVP: ExtractedFact model+migration+**repository** | **Adaptar** | Sin repository (no es el estilo del proyecto) (§3). |
| 11 | MVP: PDF extraction | **Rechazar** en esta fase | §5. |
| 11 | Reglas Representative/CURP/RequiredDocuments | **Adaptar** | Solo física; moral/gestor → `no_evaluable` explícito (§9). |
| 11 | Filament demo page | **Diferir** | Opcional; no se hizo — el panel no tiene Resources todavía y no aporta a la decisión. |
| 12 | Reglas configurables en BD | **Rechazar** en `reglas_validacion` | Ese catálogo es numérico (§2). |
| 12 | Aprobación/rechazo automático | **Rechazar** | Contradice el PRD (revisión humana; sin bloqueo). |
| 13 | Criterios de éxito | **Adaptar** | El 1 ("documents can generate structured facts") solo vía captura en Fase 1. |

---

## 11. Arquitectura recomendada

```
Paso 2.2 (Livewire, delgado)
   │  captura campos de la INE junto con el PDF (patrón COMPENDIO)   ← NO implementado en UI
   ▼
Application\Validaciones\RegistrarHechoDocumento        (escribe hechos_documento, metodo=captura_manual,
   │                                                     atado al archivo_path vigente)
   ▼
hechos_documento  (inmutable, PII, trazable al archivo)
   │
   ▼
Application\Validaciones\ConstruirContextoValidacion    (lee Eloquent: responsable, persona física,
   │                                                     hechos vigentes, DocumentosCompletos)
   ▼
Domain\Validaciones\Documental\ContextoValidacion       (puro)
   ▼
Domain\Validaciones\Documental\MotorValidacionDocumental → ReglaDocumental[]
   ▼
Domain\Validaciones\Resultado\ReporteValidacion         (cumple/advertencia/no_cumple/no_evaluable)
   ▼
Application\Validaciones\EjecutarValidacionDocumental   (devuelve el reporte; hoy no bloquea nada)

Futuro (no implementado): Infrastructure\Documentos\Extractores\*  → mismo RegistrarHechoDocumento
                           con otro `metodo`; las reglas no cambian.
```

Momento de ejecución sugerido (no decidido, P1): al avanzar de 2.2 a 2.3, junto a `ValidarVigenciaDocumentos`, como advertencias no bloqueantes; y otra vez al envío final.

---

## 12. Prototipo implementado en la rama

Estricto TDD: cada clase nació de una prueba que se vio fallar por la razón correcta (clase inexistente o aserción incorrecta, nunca error de sintaxis o de entorno). Detalle por commit en `git log master..spike/validation-engine-eval`.

**Domain (puro, sin Illuminate):**

- `Resultado/EstadoResultado` (enum), `Resultado/ResultadoRegla`, `Resultado/ReporteValidacion` (conteos por estado, `tieneNoCumplimientos()`).
- `Documental/TipoHecho` (enum: `nombre_titular`, `curp`), `Documental/OrigenHecho` (enum: `declarado`, `documento`), `Documental/Hecho` (VO; rechaza valor vacío; exige clave de documento cuando el origen es `documento`).
- `Documental/ContextoValidacion`: tipo de persona, hechos, claves requeridas y presentes; `declarado(TipoHecho)` y `deDocumento(TipoHecho, clave)`.
- `Documental/NormalizadorNombre`.
- `Documental/ReglaDocumental` (contrato), `Documental/MotorValidacionDocumental`.
- `Documental/Reglas/NombreTitularCoincide`, `CurpCoincide`, `DocumentosRequeridosPresentes`.

**Application:**

- `Validaciones/RegistrarHechoDocumento` — exige que el documento exista y ata el hecho a su `archivo_path` vigente.
- `Validaciones/ConstruirContextoValidacion` — reutiliza `TipoPersonaDeEscuela` y `DocumentosCompletos`; descarta hechos obsoletos.
- `Validaciones/EjecutarValidacionDocumental` — caso de uso delgado.

**Persistencia:** migración `2026_09_30_000000_create_hechos_documento_table.php` + modelo `HechoDocumento` (justificación en §4).

**No hecho, a propósito:** extractor de PDF/OCR, cambios en la UI de Paso 2.2 (captura de campos de la INE), página Filament, persistencia del reporte, reglas para moral/gestor/domicilio/RFC. Todo depende de decisiones abiertas.

**Nota de frontera para cuando se conecte al wizard:** `EjecutarValidacionDocumental` devuelve `ReporteValidacion`, un tipo de `app/Domain`. CLAUDE.md dice que Livewire "never import Domain directly"; si un componente necesita tipar el reporte, hará falta un DTO de Application (como `ResumenTramiteDTO`) o que el caso de uso devuelva un arreglo. No se resolvió aquí porque nada lo consume todavía. `RegistrarHechoDocumento` ya recibe strings por esta misma razón.

### Prueba de no-vacuidad por regla

Cada mutación fue temporal (no commiteada) y se revirtió después de correr las pruebas. Una regla o filtro cuyas pruebas siguen verdes con la mutación puesta tendría pruebas vacías.

| Mutación | Pruebas en rojo |
|---|---|
| `DocumentosRequeridosPresentes::evaluar()` devuelve siempre `cumple` | 3 de 4 (queda verde solo el caso "todos presentes") |
| `NombreTitularCoincide::evaluar()` devuelve siempre `cumple` | 8 de 9 (queda verde solo "nombres idénticos") |
| `CurpCoincide::evaluar()` devuelve siempre `cumple` | 5 de 7 (quedan verdes los dos casos de coincidencia) |
| `ConstruirContextoValidacion`: quitar la condición de join por `archivo_path` | 1 de 16: `test_los_hechos_de_un_archivo_reemplazado_se_descartan` |
| `ConstruirContextoValidacion`: quitar el filtro por `escuela_id` | 1 de 16: `test_los_hechos_de_otra_escuela_no_se_mezclan` |
| Regla PHPat: usar `Illuminate\Support\Str` dentro de `NormalizadorNombre` | PHPStan: 1 error "should not depend on Illuminate\Support\Str" |

Además, cada prueba nueva se vio fallar antes de existir su código de producción, siempre por clase inexistente (`Class ... not found` / `Target class ... does not exist`), nunca por sintaxis o entorno.

---

## 13. Resultados de verificación

Entorno: contenedor efímero de esta sesión. PHP 8.4.19. **PostgreSQL 16.13 local** (el proyecto usa 18; es el que trae el contenedor), con la base `sedeq_incorporacion_testing` y el rol `sedeq_app` de `phpunit.xml`, creados en este contenedor. No se tocó ninguna base dev ni de producción. No se corrió ningún `artisan migrate` contra `.env`: las migraciones solo corrieron vía `RefreshDatabase` dentro de las pruebas.

Instalación: `composer install` no pudo descargar la dist de `phpstan/phpstan` (403 de `api.github.com` sin token). Se clonó el repo de phpstan en el commit exacto del lock (`9ba9ac7`) y se instaló desde una copia temporal del lock que apuntaba a ese zip local; la copia se borró y `composer.json`/`composer.lock` no cambiaron. `npm ci && npm run build` hizo falta para el manifiesto de Vite.

| Verificación | Resultado |
|---|---|
| Suite completa en `master` antes de cambios | 546/546 (tras `npm run build`; la primera corrida sin assets dio 59 fallas: 58 por manifiesto de Vite ausente y 1, `MisTramitesTest::test_ordena_del_mas_reciente_al_mas_antiguo`, que pasó en la siguiente corrida — posible dependencia de orden temporal; no investigado, no tocado) |
| Suite completa en la rama | **604/604** (546 + 58 nuevas: 42 unitarias de Domain, 16 de Feature contra Postgres) |
| Pint `--test` | Pasa. La primera corrida marcó orden de imports en `RegistrarHechoDocumentoTest.php`; corregido en el commit `style:` |
| PHPStan nivel 5 + PHPat | 0 errores. La regla PHPat sí cubre el código nuevo (ver mutación arriba) |
| `down()` de la migración nueva | **No verificado** por separado (correrlo requeriría `artisan migrate:rollback` contra la conexión de `.env`) |

---

## 14. Preguntas abiertas para el owner

Resumen; detalle y opciones en `docs/decisions/PENDIENTE-motor-validacion-hechos.md`.

- **P1** ¿Algún `no_cumple` documental bloquea avanzar/enviar, o todo es advertencia? ¿En qué punto del flujo corre el motor documental?
- **P2** Nombre con tokens distintos: ¿`advertencia` (prototipo) o `no_cumple`? ¿Se admite similitud difusa?
- **P3** Hechos como tabla genérica (`hechos_documento`, prototipo) o tabla de extensión tipada por documento (`ine_datos`, patrón de `constancias_seguridad_estructural`)?
- **P4** PII: retención de hechos de archivos reemplazados, cifrado en reposo.
- **P5** ¿Se agregan Constancia de CURP / Constancia de Situación Fiscal al catálogo? (decisión normativa, SEDEQ)
- **P6** ¿Se persiste el reporte de validación como evidencia?
- **P7** Hechos de documentos de ámbito `plantel` compartidos entre escuelas.
- **P8** Persona moral y física-con-gestor: ¿la INE subida es la del representante/gestor? ¿Se captura su CURP?
- **P9** Domicilio vs. certificado de número oficial: ¿entra al alcance?
- **P10** Idioma de identificadores en código nuevo (español como el dominio vs. inglés por instrucción).
