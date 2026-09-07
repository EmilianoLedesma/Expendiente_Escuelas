# PENDIENTE — Origen de la magnitud y composición de reglas

**Estado: diseño abierto.** Documento para el arquitecto — no implementado aquí, a
propósito. `CalculadoraRequerimiento` (ver
`docs/reports/2026-09-07-reglas-validacion-schema.md`, §10) recibe `magnitud` como
parámetro escalar; este documento cubre de dónde debe salir ese escalar para cada una
de las 28 filas de `reglas_validacion`, y qué filas no producen un requerimiento
completo por sí solas.

## 1. Magnitud requerida por cada una de las 28 filas

| clave | tipo_calculo | ambito | magnitud que necesita el calculador |
|---|---|---|---|
| inicial.superficie.aula_lactantes | minimo_fijo | sala | no usa magnitud para el cálculo; sí necesita saber la capacidad registrada de esa sala específica para decidir si aplica (`condicion_max = 10`) — un dato de la sala, no del calculador |
| inicial.superficie.aula_maternales | minimo_fijo | sala | igual, `condicion_max = 15` |
| inicial.superficie.area_recreativa | ratio_por_alumno | plantel | matrícula total de Inicial en el plantel |
| inicial.superficie.sala_usos_multiples | ratio_por_alumno | plantel | matrícula total de Inicial en el plantel |
| inicial.superficie.sanitarios | ratio_por_alumno | plantel | matrícula total de Inicial en el plantel |
| inicial.personal.responsable_sala | personal_por_espacio | sala | número de salas existentes (Lactantes A/B/C + Maternal A/B) — un conteo, no una matrícula |
| inicial.personal.asistente_lactantes | personal_proporcional | sala | matrícula de la(s) sala(s) tipo Lactantes específicamente (por sala, no total del plantel) |
| inicial.personal.asistente_maternales | personal_proporcional | sala | matrícula de la(s) sala(s) tipo Maternal específicamente |
| inicial.personal.director_tecnico | personal_obligatorio | plantel | ninguna (constante) |
| preescolar.superficie.construida_total | ratio_por_alumno | plantel | matrícula total de Preescolar en el plantel |
| preescolar.superficie.aula | ratio_por_alumno | aula | matrícula del aula/grupo específico — **compone con la fila siguiente, ver §2** |
| preescolar.superficie.espacio_maestro | adicional_fijo | aula | ninguna (constante) — **se suma al resultado de la fila anterior, no es un requerimiento independiente, ver §2** |
| preescolar.superficie.area_recreacion | ratio_por_alumno | plantel | matrícula total de Preescolar en el plantel |
| preescolar.superficie.aula_usos_multiples | factor | aula | **no es una matrícula** — es el área ya registrada del aula más grande del plantel (un dato agregado sobre otros espacios, no un conteo de alumnos) — ver §2 |
| preescolar.personal.educacion_fisica | personal_umbral | escuela | matrícula total de la escuela (nivel Preescolar) |
| primaria.superficie.predio_total | ratio_por_alumno | predio | matrícula total — **ambiguo si es solo Primaria o todo el predio si comparte niveles**, ver §3 |
| primaria.superficie.aulas | ratio_por_alumno | aula | matrícula por aula/grupo |
| primaria.infraestructura.altura_aulas | minimo_fijo | aula | ninguna (constante) |
| primaria.infraestructura.acervo_bibliografico | ratio_por_grado | escuela | número de grados ofertados por la escuela (típicamente 6 en Primaria completa) |
| primaria.personal.educacion_fisica | personal_proporcional | escuela | matrícula total de la escuela (nivel Primaria) |
| secundaria.superficie.predio_total | ratio_por_alumno | predio | matrícula total — mismo punto de ambigüedad que Primaria, ver §3 |
| secundaria.superficie.aulas | ratio_por_alumno | aula | matrícula por aula/grupo |
| secundaria.superficie.area_recreacion | ratio_por_alumno | plantel | matrícula total de la escuela Secundaria |
| secundaria.superficie.areas_recreativas_minimas | minimo_fijo | plantel | ninguna (constante) |
| secundaria.infraestructura.acervo_bibliografico | minimo_fijo | escuela | ninguna (constante — 300 total, no por grado) |
| secundaria.personal.educacion_fisica | personal_umbral | escuela | matrícula total de la escuela Secundaria |
| secundaria.personal.trabajador_social | personal_umbral | escuela | matrícula total de la escuela Secundaria |
| secundaria.personal.prefecto | personal_umbral | escuela | matrícula total de la escuela Secundaria |

**Tablas candidatas para resolver cada magnitud** (no confirmado, para orientar al
arquitecto): matrícula por aula/grupo → `matricula_grados`; matrícula por sala →
`matricula_salas`; conteo de grados ofertados → `grados` + `escuela_niveles`; conteo
de aulas/salas existentes → `aulas_nivel` / `instalaciones_espacios`; área de un aula
existente → `aulas_nivel` o `instalaciones_espacios` (no queda claro cuál de las dos
es la fuente correcta — ninguna de las dos fue diseñada pensando en este uso).

## 2. Filas que componen en un solo requerimiento normativo

Releída COMPENDIO §5.2 completa (Preescolar/Primaria/Secundaria) buscando
específicamente patrones de "+" o "más" que sumen dos magnitudes en un solo
requerimiento, además del caso ya señalado por el usuario:

1. **`preescolar.superficie.aula` + `preescolar.superficie.espacio_maestro`** (el caso
   ya identificado). COMPENDIO línea 520: *"Aulas: 1 m² por educando + 2 m²
   adicionales para el espacio del maestro."* La tabla comparativa (línea 637)
   confirma que esta composición es **exclusiva de Preescolar** — Primaria y
   Secundaria tienen una sola cifra de aula (0.90 m²/alumno) sin término adicional.
   Confirmado: ningún otro nivel necesita esta composición para `superficie.aulas`.
2. **`preescolar.superficie.aula_usos_multiples`** no es una composición de dos filas
   de `reglas_validacion` entre sí, pero sí depende del *resultado (o del dato
   registrado)* de otro espacio — "1.5 veces el aula mayor del plantel." Esto es
   distinto de la composición aritmética simple del caso 1: aquí la "magnitud" de
   entrada de esta fila **es** la salida de otra consulta (el área máxima entre las
   aulas del plantel), no una matrícula. Vale la pena que el arquitecto lo trate como
   su propia categoría de "origen de magnitud" (una consulta agregada sobre otros
   registros), no como una composición aditiva.
3. **No se encontraron más casos** de este tipo en §5.2 para Primaria o Secundaria —
   sus reglas de superficie (predio, aulas, área de recreación, bibliográfico) son
   todas cifras independientes, sin términos "+X" adicionales en el texto revisado.
   Tampoco se encontró ninguno en §5 (Inicial) — sus reglas de personal (responsable,
   asistentes, director) son conceptos claramente separados en el propio texto, no
   sumandos de un mismo requerimiento.

## 3. Punto abierto adicional (no pedido explícitamente, mencionado porque bloquea la resolución de magnitud)

`primaria.superficie.predio_total` y `secundaria.superficie.predio_total` usan
`ambito = predio`. Si un mismo predio físico aloja más de un nivel educativo (p.ej.
Primaria y Secundaria en el mismo plantel, escenario que el DDL permite vía
`escuela_niveles`), no está claro si "2.50 m² por alumno" se calcula con la matrícula
de solo ese nivel o con la suma de todos los niveles que comparten el predio. COMPENDIO
no lo aclara explícitamente en el texto revisado. No es parte de la pregunta que
originó este documento, pero cualquier diseño de "origen de magnitud" para `ambito =
predio` tendrá que resolver esto — se deja anotado para no perderlo.

## 4. Diseños candidatos (no implementados)

### Opción A — columna `origen_magnitud` (enum)

Añadir `origen_magnitud VARCHAR(30) NOT NULL CHECK (...)` a `reglas_validacion` con
valores como `matricula_escuela`, `matricula_aula`, `matricula_sala`, `num_grados`,
`num_salas`, `area_aula_mayor`, `fijo`. El calculador (o una clase resolutora
separada) traduce cada valor a una consulta concreta.

- **Ventaja:** declarativo — el origen de cada magnitud queda visible en la fila
  misma, auditable sin leer código.
- **Desventaja:** varios orígenes (`area_aula_mayor`, el caso del predio compartido en
  §3) no son un simple lookup por FK — siguen necesitando lógica de resolución en
  código de todos modos, así que la columna solo documenta la intención, no elimina
  el branching. Y no resuelve composición (§2) en absoluto — necesitaría combinarse
  con otro mecanismo para eso.

### Opción B — `regla_padre_id` (autorreferencia para composición)

Añadir `regla_padre_id INTEGER NULL REFERENCES reglas_validacion(id)` para que
`preescolar.superficie.espacio_maestro` apunte a `preescolar.superficie.aula` como su
padre; el motor sumaría el resultado del padre con el de todos sus hijos al evaluar.

- **Ventaja:** modela la composición directamente en los datos, extensible a N hijos
  por regla si aparecen más casos.
- **Desventaja:** para un solo par confirmado en 28 filas (§2), es una estructura de
  árbol autorreferenciada que resuelve un problema que hoy tiene exactamente un caso.
  Tampoco dice nada sobre el origen de la magnitud (Opción A o C igual haría falta).

### Opción C — resolver ambos (origen y composición) en la capa de Aplicación/Dominio

No tocar el esquema más allá de lo ya hecho. Escribir una clase pequeña en
`app/Domain/Validaciones/` (p.ej. un `ResolverMagnitud` o extender el futuro
`ValidacionCapacidadService`) que, dado el `clave` de una fila (un catálogo cerrado y
conocido de 28 valores), sepa qué consulta ejecutar y qué filas sumar. Codificado por
`clave`, no por una convención genérica.

- **Ventaja:** el catálogo de 28 reglas es pequeño y cerrado por ahora (el detalle
  granular — puertas, escaleras, sanitarios por rango — sigue deliberadamente
  diferido, per el plan original); resolver esto en código evita añadir dos columnas
  de esquema para 1-de-28 (`area_aula_mayor`) y 1-de-28 (la composición) casos
  especiales. Cuando de todos modos hace falta código para consultar
  `matricula_grados`/`aulas_nivel`/etc., una columna de enum no ahorra ese código —
  solo lo describe.
- **Desventaja:** menos inspeccionable desde la base de datos sola; un cambio de
  mapeo requiere una migración de código, no un cambio de datos; si el catálogo de
  reglas crece sustancialmente (cuando se aborde el detalle granular diferido), el
  mapeo por `clave` puede volverse difícil de mantener a mano.

## 5. Recomendación (no vinculante — decide el arquitecto)

**Opción C.** El `ambito` ya seeded (aula/sala/plantel/escuela/predio) reduce la
mayoría de los casos a una categoría conocida; solo una fila (`aula_usos_multiples`)
necesita un origen de magnitud verdaderamente distinto, y solo un par de filas compone
hoy. Añadir dos columnas de esquema (Opciones A y B) para resolver un problema de
escala 1-2 es la abstracción prematura que este proyecto ya evitó una vez (ver
`docs/progress.md`, Decisions Log, sobre no pre-escalar carpetas de dominio vacías).
Revisar esta recomendación cuando el detalle granular diferido (puertas, escaleras,
sanitarios por rango) se aborde — ahí es probable que el volumen de reglas y de
patrones de composición justifique moverse a la Opción A y/o B.

No implementado aquí, por instrucción explícita — este documento es solo para guiar
la decisión del arquitecto antes de escribir el Motor de Validación.
