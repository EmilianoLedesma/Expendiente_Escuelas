# Motor de Validación de Capacidad Instalada: diseño e implementación inicial

**Fecha:** 2026-09-30
**Rama:** `feat/motor-capacidad-instalada`, apilada sobre `spike/validation-engine-eval` (PR #1, sin mergear). Reutiliza los tipos de resultado de ese PR (`app/Domain/Validaciones/Resultado/`).
**Decisiones abiertas que toca:** `PENDIENTE-origen-de-magnitud.md` (implementada la opción C, provisional), `PENDIENTE-umbral-educacion-fisica.md`, `PENDIENTE-perfiles-trabajador-social-prefecto.md` (hueco de Educación Física en Secundaria), `PENDIENTE-personal-condicionado-por-grado.md`. Las elecciones nuevas de esta sesión están en `PENDIENTE-motor-capacidad-provisionales.md`.

## 1. Qué pide la norma

- **PRD, "Motor de Validación de Capacidad Instalada":**
  - Se ejecuta **una sola vez por nivel**, después de capturar todo el Paso 3 (no por sub-paso).
  - Compara superficie, personal y mobiliario contra `reglas_validacion` / `mobiliario_conceptos`, con un mensaje por regla incumplida.
  - Muestra advertencias **sin bloquear el guardado**: el expediente puede quedar "con observaciones".
- **COMPENDIO §5, §5.1 y §5.2:** ratios por nivel, ya sembrados en `reglas_validacion` (28 filas) y `mobiliario_conceptos` (Inicial).

## 2. Qué datos existen hoy para alimentarlo

| Magnitud | Tabla | ¿Se captura en el wizard? |
|---|---|---|
| Matrícula por grado/grupo (Preescolar–Secundaria) | `matricula_grados` | **No** — sub-paso "Matrícula" es un stub |
| Matrícula por sala (Inicial) | `matricula_salas` | **No** — mismo stub |
| Personal por cargo (y sala / asignatura) | `personal`, `personal_salas`, `personal_asignaturas` | **No** — sub-paso "Plantilla docente" es un stub |
| Aulas del nivel (número y superficie **total**) | `aulas_nivel` | Sí (Infraestructura) |
| Superficie del predio y construida | `planteles.metros_totales`, `metros_construidos` | Sí (Datos del inmueble) |
| Espacios recreativos, sala de usos múltiples, biblioteca | `instalaciones_espacios` (+ `biblioteca_materiales`) | Sí (Infraestructura) |
| Sanitarios (superficie por categoría) | `sanitarios` | Sí (Infraestructura) |
| Mobiliario declarado (Inicial) | `mobiliario_nivel` | Sí (Mobiliario) |

Consecuencia: el motor se construye completo contra las tablas del DDL, pero **en el flujo real todas las reglas que dependen de matrícula o personal saldrán "no verificado" hasta que existan los sub-pasos de Matrícula y Plantilla docente**. No se inventan datos ni se piden por otro lado.

## 3. Diseño

Sigue la **opción C** de `PENDIENTE-origen-de-magnitud.md`: la magnitud de cada regla se resuelve en código, por `clave`, sin cambios de esquema.

- **Domain** (`app/Domain/Validaciones/`, sin Laravel):
  - `Regla/ReglaCapacidad`: objeto de valor con una fila de `reglas_validacion`.
  - `Engine/DatosCapacidadNivel`: objeto de valor con todas las magnitudes de un nivel. `null` significa "no capturado".
  - `Engine/ValidacionCapacidadService` (hoy un stub vacío, se llena):
    - Por cada regla resuelve la magnitud y el valor declarado, calcula el requerimiento con `CalculadoraRequerimiento` (sin cambios) y compara.
    - Resultado: `cumple`, `no_cumple` o `no_evaluable`, con requerido/declarado en `detalles`.
    - Compone `preescolar.superficie.aula` + `espacio_maestro` en un solo requerimiento (§2 de ese PENDIENTE).
  - `Engine/EvaluadorMobiliario`: `mobiliario_nivel` contra `mobiliario_conceptos` por sala (Inicial).
- **Application** (`app/Application/Validaciones/`):
  - `ConstruirDatosCapacidad` es la única lectura a la base.
  - `EjecutarValidacionCapacidad` corre el motor para un `escuela_nivel`.
- **Integración:** la página "Validación final" y su PDF (PR #1) agregan una sección **"Capacidad instalada"** por nivel. Esos resultados **no cambian** si el trámite puede enviarse: el PRD dice "sin bloquear, con observaciones". Ver provisional P3.

### Reglas y su evaluación

| Clave | Declarado | Magnitud |
|---|---|---|
| `*.superficie.predio_total` | `planteles.metros_totales` | matrícula de **todos los niveles del plantel** (P2) |
| `preescolar.superficie.construida_total` | `planteles.metros_construidos` | matrícula del nivel |
| `*.superficie.aula(s)` (+ `espacio_maestro` en Preescolar) | `aulas_nivel.superficie_m2` (total) | matrícula del nivel (+2 m² × número de aulas) |
| `*.superficie.area_recreacion` / `inicial.superficie.area_recreativa` | suma de espacios `recreativo_deportivo` | matrícula del nivel |
| `secundaria.superficie.areas_recreativas_minimas` | suma de espacios `recreativo_deportivo` | fijo 200 m² |
| `inicial.superficie.sala_usos_multiples` | espacio `salon_usos_multiples` | matrícula del nivel |
| `inicial.superficie.sanitarios` | suma de superficie de sanitarios `alumnado_*` | matrícula del nivel |
| `primaria.infraestructura.acervo_bibliografico` | títulos de tipo `libros` en bibliotecas del plantel | grados ofertados × 50 |
| `secundaria.infraestructura.acervo_bibliografico` | ídem | fijo 300 |
| `*.personal.director_tecnico`, `inicial.personal.responsable_filtro` | personal completo con ese cargo | fijo 1 |
| `inicial.personal.responsable_sala` | responsables de sala | número de salas con alumnos |
| `inicial.personal.asistente_lactantes` / `_maternales` | asistentes asignados a salas de ese tipo | Σ por sala de ⌈alumnos/5⌉ o ⌈alumnos/10⌉ |
| `preescolar.personal.educacion_fisica`, `secundaria.personal.trabajador_social`, `secundaria.personal.prefecto` | personal con ese cargo | umbral sobre matrícula (P1) |
| `secundaria.personal.educacion_fisica` | Docentes Titulares con asignatura "Educación Física" | umbral sobre matrícula; resuelve en el motor el hueco de `cargo_puesto_id` nulo |
| `primaria.personal.educacion_fisica` | personal con ese cargo | ⌊matrícula/60⌋ |
| `primaria.infraestructura.altura_aulas`, `preescolar.superficie.aula_usos_multiples`, `inicial.superficie.aula_lactantes`, `inicial.superficie.aula_maternales` | — | **no evaluable**: la altura, el aula mayor y la superficie por sala no se capturan |

Solo cuenta el personal **completo** según `RegistroPersonalCompleto` (Domain existente).

## 4. Fuera de alcance de esta sesión

- **Captura de Matrícula y Plantilla docente:** sub-pasos propios del wizard, con su propio diseño.
- **Reglas por grado ofertado** (Inglés, Computación): `PENDIENTE-personal-condicionado-por-grado.md` sigue abierto; no hay filas sembradas.
- **Detalle granular** (puertas, escaleras, pasillos, sanitarios por rango): no está sembrado; diferido desde el plan original.
- **Validación de perfil profesional** del personal (carrera contra cargo).

(Las secciones de evidencia y resultados se agregan al terminar.)
