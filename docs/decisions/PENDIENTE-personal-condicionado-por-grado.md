# PENDIENTE — Personal condicionado por grado ofertado (Inglés/Computación)

**Estado: abierto.** Requiere decisión del owner/arquitecto (y, para el detalle
normativo exacto de "a partir de qué grado", confirmación de SEDEQ si hiciera
falta precisión adicional a la ya documentada en COMPENDIO).

## Contexto

WS-3.4 (`ReglasValidacionSeeder`) sembró las reglas de personal obligatorio,
umbral y proporcional que hoy caben en el esquema de `reglas_validacion`
(`personal_obligatorio`, `personal_umbral`, `personal_proporcional`,
`personal_por_espacio`, ver columnas `tipo_calculo`/`ambito`/`condicion_min`/
`condicion_max` en `docs/ddl_sistema_incorporacion_v3.sql`, tabla
`reglas_validacion`, líneas 146–170). Ese seeder **no** sembró ninguna regla
para los cargos condicionados por grado (Inglés en Preescolar, Inglés y
Computación en Primaria) porque el esquema actual de `reglas_validacion` no
tiene forma de expresar "grado ofertado" como condición.

## Evidencia (verbatim, con ubicación)

`docs/COMPENDIO_MAESTRO_Sistema_Incorporacion.md`:

- Línea 447 (encabezado de §5.1, declara la fuente de toda la sección):
  > "Fuente: `PROFESIOGRAMA_INICIAL.pdf`, `PROFESIOGRAMA_PREESCOLAR.pdf`,
  > `PROFESIOGRAMA_PRIMARIA.pdf`, `PROFESIOGRAMA_SECUNDARIA.pdf` (ciclo
  > escolar 2023-2024)."

- Líneas 455–457 (§5.1, "Reglas de personal condicionadas por grado
  ofertado"):
  > "### Reglas de personal condicionadas por grado ofertado
  > - **Preescolar**: docente de Inglés obligatorio **a partir de 3º grado**.
  > - **Primaria**: docente de Inglés **obligatorio siempre** (todos los
  > grados); docente de Computación obligatorio **a partir de 3º grado**.
  > - **Preescolar**: el "asistente de grupo/auxiliar" **no es obligatorio**,
  > pero si la escuela decide tenerlo debe cumplir el perfil profesional
  > exigido (certificado)."

- Línea 474 (detalle de perfil, Preescolar):
  > "**Docente de Inglés** (obligatorio a partir de 3º): certificado —
  > Educación/Idiomas/Lenguas Modernas/Letras con especialidad en Inglés, o
  > certificaciones reconocidas (TKT, CENNI, KET, PET, FCE, CAE, CPE, MCER)."

- Línea 481 (detalle de perfil, Primaria):
  > "**Docente de Inglés** (obligatorio, todos los grados): título y cédula o
  > certificado — mismos perfiles/certificaciones que en Preescolar."

- Línea 482 (detalle de perfil, Primaria):
  > "**Docente de Computación** (obligatorio a partir de 3º grado): título y
  > cédula — Informática, Sistemas Computacionales Administrativos,
  > Tecnologías de la Información, Computación, o afín."

- Línea 142 (§5 introductorio, confirma que esto es una regla "condicionada",
  distinta de las reglas por grupo/grado ya sembradas):
  > "**Por grupo/grado (Preescolar, Primaria)**: Director, Docente titular por
  > grupo, docentes condicionados (Educación Física, Inglés, Computación según
  > capacidad/grado — ver Profesiogramas), docentes extracurriculares si
  > aplica."

Los cargos ya existen en el catálogo — `app-laravel/database/seeders/
CargosPuestosSeeder.php` siembra "Docente de Inglés" para Preescolar (línea
29) y Primaria (línea 34), y "Docente de Computación" para Primaria (línea
35). Solo falta la regla de `reglas_validacion` que los active
condicionalmente por grado.

## Por qué las columnas actuales no lo pueden expresar

`reglas_validacion.tipo_calculo` (CHECK, DDL líneas 152–157) admite:
`ratio_por_alumno`, `ratio_por_grado`, `minimo_fijo`, `adicional_fijo`,
`factor`, `personal_obligatorio`, `personal_umbral`, `personal_proporcional`,
`personal_por_espacio`. Ninguno de estos codifica "requerido si la escuela
oferta el grado N" — el más cercano es `personal_umbral`
(`App\Domain\Validaciones\Regla\CalculadoraRequerimiento::umbral()`,
`app-laravel/app/Domain/Validaciones/Regla/CalculadoraRequerimiento.php`
líneas 43–50), pero su única entrada de comparación es una `$magnitud`
numérica contra `condicion_min` (p. ej. alumnos), no un grado ofertado ni un
conjunto de grados.

`reglas_validacion.ambito` (CHECK, DDL líneas 158–159) admite: `aula`, `sala`,
`plantel`, `escuela`, `predio` — tampoco hay un ámbito `grado` o
`escuela_nivel_grado`. No existe ninguna columna que referencie
"grado" (mínimo o exacto) en la tabla `reglas_validacion`; el motor
(`CalculadoraRequerimiento::calcular()`) tampoco recibe un parámetro de grado,
solo `magnitud` (float).

En consecuencia, hoy no hay forma de sembrar "Docente de Inglés obligatorio a
partir de 3º grado" sin inventar semántica no soportada por el schema o el
motor — sembrarlo como `personal_obligatorio` (siempre 1, sin condición)
sería normativamente falso para Preescolar/Computación-Primaria (obligaría el
cargo aunque la escuela no ofrezca 3º grado o niveles superiores); sembrarlo
como `personal_umbral` con `condicion_min` sobre alumnos sería una condición
equivocada (alumnos, no grado).

## Opciones (con trade-offs)

1. **Nueva columna `condicion_grado` en `reglas_validacion`** (p. ej.
   `SMALLINT NULL`, "grado mínimo ofertado para que la regla aplique";
   `NULL` = sin condición de grado). Requiere migración nueva + append al DDL
   (`docs/ddl_sistema_incorporacion_v3.sql`), por la regla de CLAUDE.md de
   que todo cambio de schema es una migración nueva, nunca una edición de las
   existentes. Ventaja: cabe en el mismo motor genérico (`tipo_calculo` +
   condiciones), mínimo cambio estructural. Desventaja: mezcla dos tipos de
   condición distintos (capacidad numérica vs. grado ofertado) en la misma
   fila/tabla; el motor necesita saber de dónde viene "grado ofertado" por
   escuela (¿de qué tabla? `escuela_niveles` no captura grados individuales
   hoy).

2. **Tabla de reglas separada** (p. ej. `reglas_validacion_grado` o una
   extensión 1:1 de `reglas_validacion` solo para las filas que lo necesitan)
   con `nivel_educativo_id`, `cargo_puesto_id`, `grado_minimo`. Ventaja: no
   contamina el schema genérico de `reglas_validacion` con una columna que
   solo 2-3 filas usarían; separación de concerns más limpia. Desventaja: el
   motor de validación necesitaría dos rutas de lectura (reglas genéricas +
   reglas por grado) en vez de una — más superficie de código, y
   `ValidacionCapacidadService` es hoy un stub vacío, así que este costo cae
   sobre trabajo aún no escrito.

3. **Manejarlo en el Motor con una magnitud por-grado**, sin cambio de
   schema: sembrar la regla como `personal_obligatorio` (siempre exigible en
   el catálogo), pero el Motor —al evaluar una escuela concreta— decide si la
   regla aplica según los grados que esa escuela realmente oferte, usando una
   lista de excepciones hardcodeada en `app/Domain/Validaciones/Engine/`
   (ej. `if ($cargo === 'Docente de Inglés' && $nivel === 'preescolar') { …
   grado >= 3 … }`). Ventaja: cero cambio de schema, encaja con la nota de
   CLAUDE.md "(Validaciones-specific rules plugged here, not scattered)"
   para `Domain/Validaciones/Engine/`. Desventaja: la condición deja de vivir
   en datos (seed/tabla) y pasa a vivir en código — cualquier corrección
   futura (SEDEQ cambia el grado mínimo) requiere un deploy, no un `UPDATE`
   sembrado por seeder; rompe el patrón "todo lo normativo vive en
   `reglas_validacion`" que el resto del seeder sigue.

Ninguna opción se implementa en este workstream (WS-3.5 es solo la
documentación de la pregunta abierta; "NO schema change" por alcance de
tarea).

## Quién decide

- **Owner/arquitecto**: cuál de las tres opciones (o una variante) encaja
  mejor con la dirección de `app/Domain/Validaciones/Engine/` — es una
  decisión de diseño interno, no depende de SEDEQ.
- **SEDEQ**: la redacción de COMPENDIO §5.1 (líneas 455–457) ya es específica
  ("a partir de 3º grado", "obligatorio siempre") y trazable a los
  Profesiogramas citados en la línea 447 — no hay ambigüedad normativa
  detectada que requiera confirmación adicional de SEDEQ para *esta* regla en
  particular, a diferencia del umbral de Educación Física
  (`PENDIENTE-umbral-educacion-fisica.md`, que sí tiene redacciones
  contradictorias dentro del propio COMPENDIO). Si la opción elegida necesita
  capturar el grado ofertado por escuela y esa captura no existe todavía en
  el wizard, eso es una pregunta de producto para el owner, no para SEDEQ.

## Impacto mientras esté abierto

- Hoy no hay ninguna regla sembrada en `reglas_validacion` para "Docente de
  Inglés" (Preescolar/Primaria) ni "Docente de Computación" (Primaria)
  condicionada por grado. Los cargos existen en `cargos_puestos` (ver
  `CargosPuestosSeeder.php` líneas 29, 34, 35) pero el Motor de Validación no
  tiene ninguna fila de `reglas_validacion` que los exija — cuando
  `ValidacionCapacidadService` deje de ser un stub, no validará estos dos
  requisitos hasta que esta pregunta se resuelva.
- Esto no bloquea la captura del Anexo 1 (el catálogo de cargos ya acepta
  "Docente de Inglés"/"Docente de Computación" como cargo válido), solo la
  validación automática de que son obligatorios según el grado ofertado.
