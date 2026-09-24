# WS-3 — Correcciones normativas de catálogos (solo datos semilla)

**Fecha:** 2026-09-24
**Rama:** `fix/catalogos-normativos` (worktree `.claude/worktrees/ws3`), creada desde `master` local en `a1e2df3`.
**Origen:** brief de remediación de la auditoría del 2026-09-23, sección "WS-3", y Apéndice B.
**Clasificación:** acotada. El propietario autorizó iniciar WS-3 el 2026-09-24.

## Resultado

- Sin migraciones, sin cambios de esquema y sin cambios en `docs/ddl_sistema_incorporacion_v3.sql`.
- Pint: limpio. PHPStan: 0 errores.
- Suite completa: 441/441 (1022 aserciones) en la última ejecución (`7314405`), sin interferencia.
  - Durante la rama, otra rama de trabajo (rediseño de UI) corrió pruebas en paralelo contra la misma base de pruebas `sedeq_incorporacion_testing`. Eso produjo fallos aleatorios de tipo `SQLSTATE[42P01] Undefined table` y un deadlock.
  - Todos los archivos afectados pasan al ejecutarse solos. No son regresiones.
  - Para evitarlo en adelante se pidió al propietario crear una base de pruebas separada (`sedeq_incorporacion_testing_ui`) para la rama del rediseño.
- No se escribió nada en la base de desarrollo.

## Método y lo que falló en el proceso

- **3.1–3.3** los implementó Haiku. La revisión (Sonnet) encontró **evidencia fabricada o mal atribuida** en `PENDIENTE-matriz-espacios-por-nivel.md`:
  - Filas de mobiliario inexistentes ("Silla para niño", mobiliario de comedor).
  - La afirmación falsa de que los tres espacios "pasaron de inferidas a explícitas".
  - Una paráfrasis del Apéndice B.3 presentada como cita textual de Requisitos Inicial.
  - Siguiendo la política del propietario (lo que falla con Haiku se rehace con Sonnet), se corrigió en `e4cfbb2`.
- **Mensaje de commit de `b187aa4`:** cita "COMPENDIO evidence" para "Profesor en Educación Preescolar" en Director Técnico de Inicial. Es incorrecto: la fuente real es el Profesiograma Inicial (Apéndice B.4). COMPENDIO línea 461 no lo menciona, y la línea 540 corresponde a Preescolar (Acuerdo 357).
  - El historial no se reescribió.
  - La corrección quedó como comentario en `PerfilesProfesionalesSeeder.php` y se registra aquí.
- **3.4** (Sonnet): la revisión encontró que los tres `director_tecnico` de Básica citaban los Acuerdos 357/254/255 como `fuente`.
  - COMPENDIO documenta su obligatoriedad en §5.1 (Profesiogramas), no en §5.2 (Acuerdos).
  - Corregido por el controlador en `6a59342`, junto con `concepto`/`unidad`, que decían "por plantel" siendo el ámbito `escuela`.
  - La prueba nueva falla con el seeder anterior y pasa con el corregido.
- **3.5–3.6** (Sonnet): documentos de decisión.
- **Revisión final (Opus):** todas las citas se verificaron textualmente; ninguna inventada. Encontró afirmaciones imprecisas en `PENDIENTE-umbral-educacion-fisica.md` y un hueco no documentado (el `cargo_puesto_id` nulo de Secundaria EF). Se corrigieron en una ronda final (ver "Ronda final").

## Cambios

### 3.1 Espacios de Inicial (`f837f86`, corregido en `e4cfbb2`)
- `TiposEspaciosSeeder`: `salon_usos_multiples`, `cocina` y `comedor` ahora aplican también a Inicial, con `obligatorio = false` (la convención de "ninguno obligatorio").
- Evidencia:
  - Apéndice B.3 del brief, que parafrasea Requisitos Inicial §ñ ("Sala de usos múltiples: 1.2 m² per child").
  - La regla existente `inicial.superficie.sala_usos_multiples`.
  - Las tres filas de mobiliario de SUM en `MobiliarioConceptosSeeder`: "Silla infantil con cinturón (lactantes)", "Silla infantil (maternal)", "Mesa infantil".
- Efecto visible: en Paso 3.2 (Infraestructura) de un nivel Inicial aparecen esos tres espacios.
- `PENDIENTE-matriz-espacios-por-nivel.md` actualizado; la afirmación previa de que eran exclusivos de Básica quedó anotada como superada.

### 3.2 Perfiles de Inicial (`b187aa4`)
- "Profesor en Educación Preescolar" agregado a Director Técnico.
- "Enfermera" agregado a Responsable de Filtro y Fomento a la Salud.
- Ambos como `titulo_cedula`. Fuente: Profesiograma Inicial (Apéndice B.4).
- `perfiles_profesionales`: 87 → 89.

### 3.3 Cargos de Secundaria (`272d194`)
- "Trabajador Social" y "Prefecto" (Acuerdo 255 vía COMPENDIO §5.2), con `requiere_asignatura = false` y `requiere_sala = false`.
- `cargos_puestos`: 16 → 18.
- Nuevo `PENDIENTE-perfiles-trabajador-social-prefecto.md`: el Profesiograma Secundaria no define sus perfiles, así que no se sembró ninguno.

### 3.4 Reglas (`d3c1d44`, `6a59342`)
- Nuevas reglas (28 → 32):
  - `preescolar|primaria|secundaria.personal.director_tecnico`: `personal_obligatorio`, ámbito `escuela`, fuente "Profesiograma {Nivel} (SEDEQ, ciclo 2023-2024)".
  - `inicial.personal.responsable_filtro`: `personal_obligatorio`, ámbito `plantel`, fuente "Profesiograma Inicial (SEDEQ, ciclo 2023-2024)".
- `cargo_puesto_id` llenado en 12 de 13 reglas de personal, buscando por (nivel, nombre del cargo), nunca por id fijo.
  - La excepción es `secundaria.personal.educacion_fisica`. En Secundaria, Educación Física es una asignatura bajo "Docente Titular", y `reglas_validacion` no tiene columna de asignatura. Documentado en `PENDIENTE-perfiles-trabajador-social-prefecto.md`.
- `ReglasValidacionSeeder`:
  - Pasó de `insertOrIgnore` a `upsert` por `clave`: al volver a correrlo actualiza las filas existentes en vez de ignorarlas.
  - Falla con error explícito si `cargos_puestos` está vacío o falta un cargo nombrado, en lugar de dejar `NULL` en silencio.
  - `DatabaseSeeder` ya lo ejecutaba después de `CargosPuestosSeeder`; se agregó un comentario que hace explícita la dependencia.
- `CalculadoraRequerimiento` ya maneja `personal_obligatorio`; no hubo cambios en el dominio.

### 3.5 Personal condicionado por grado (`c56f149`)
- Nuevo `PENDIENTE-personal-condicionado-por-grado.md`: Inglés desde 3º en Preescolar; Inglés siempre y Computación desde 3º en Primaria (COMPENDIO L455–458, L474, L481–482).
- Las columnas actuales de `reglas_validacion` no pueden expresarlo. Se plantean tres opciones (columna nueva, tabla aparte, manejo en el Motor) para decisión del propietario/arquitecto.
- **No se sembró ninguna regla.**

### 3.6 Umbral de Educación Física (`c56f149`)
- Se agregó al `PENDIENTE-umbral-educacion-fisica.md` la redacción del Profesiograma (Apéndice B.4, notas de Preescolar y Primaria): "capacidad de sesenta alumnos **o más**". Dos implicaciones para SEDEQ:
  - "o más" significa ≥60, y la regla de Preescolar sembrada usa >60 (61);
  - dice *capacidad* instalada, no matrícula.
- Las reglas de Secundaria vienen del Acuerdo 255 y no están cubiertas por esa redacción.
- **No se cambió ningún valor sembrado.**

## Ronda final (correcciones de la revisión Opus)
Solo documentación, más una aserción de prueba (`fuente` de `inicial.personal.responsable_filtro`):
- `PENDIENTE-umbral-educacion-fisica.md`: atribución corregida (COMPENDIO L447, no el brief); la contradicción limitada a `preescolar.personal.educacion_fisica` (la de Primaria, `floor(alumnos/60)`, ya incluye a 60 y no se contradice; las de Secundaria vienen del Acuerdo 255 y B.4 no las cubre); reconocido que la distinción capacidad/matrícula ya aparecía en filas previas de su propia tabla; la nueva redacción agregada como fila 10.
- `PENDIENTE-perfiles-trabajador-social-prefecto.md`: sección nueva sobre el `cargo_puesto_id` nulo de Secundaria EF; sin "recomendado"; el impacto en el asistente marcado como futuro (la captura del Anexo 1 aún es un esqueleto).
- `PENDIENTE-personal-condicionado-por-grado.md`: rango de líneas corregido (455–458).

## Deliberadamente fuera de alcance
- Reglas condicionadas por grado (3.5): pendientes de decisión.
- Valores del umbral de EF (3.6): pendientes de SEDEQ.
- Perfiles de Trabajador Social y Prefecto: pendientes de SEDEQ.
- Vinculación de Secundaria EF con un cargo o asignatura: requiere decisión de esquema o del Motor.

## Acciones del propietario (base de desarrollo)
Desde `app-laravel/`, **en este orden**:
```
php artisan db:seed --class=TiposEspaciosSeeder
php artisan db:seed --class=CargosPuestosSeeder
php artisan db:seed --class=PerfilesProfesionalesSeeder
php artisan db:seed --class=ReglasValidacionSeeder
```
- `CargosPuestosSeeder` debe ir antes de `PerfilesProfesionalesSeeder` y de `ReglasValidacionSeeder`; si faltan Trabajador Social o Prefecto, este último lanza `RuntimeException`.
- `TiposEspaciosSeeder` es independiente.
- **No ejecutar `php artisan db:seed` sin `--class`:** `DatabaseSeeder` crea el usuario `test@example.com` y un solicitante, lo que falla por correo duplicado o agrega una fila sobrante en desarrollo.
- Todos los seeders son idempotentes. `ReglasValidacionSeeder` actualiza en su lugar las 28 reglas existentes (les llena `cargo_puesto_id`) e inserta las 4 nuevas, sin duplicar.
