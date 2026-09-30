# Motor de Validación de Capacidad Instalada: diseño e implementación inicial

**Fecha:** 2026-09-30
**Rama:** `feat/motor-capacidad-instalada`, apilada sobre `spike/validation-engine-eval` (PR #1, sin mergear). Reutiliza los tipos de resultado de ese PR (`app/Domain/Validaciones/Resultado/`).
**Pedido:** continuar con el motor de capacidad y, a mitad de la sesión, "agregar los pasos del wizard del Paso 3, construyendo el backend si hace falta". Las dos cosas se hicieron en esta rama.
**Decisiones abiertas que toca:** `PENDIENTE-origen-de-magnitud.md` (implementada la opción C, provisional), `PENDIENTE-umbral-educacion-fisica.md`, `PENDIENTE-perfiles-trabajador-social-prefecto.md` (hueco de Educación Física en Secundaria), `PENDIENTE-personal-condicionado-por-grado.md`. Las elecciones nuevas de esta sesión están en `PENDIENTE-motor-capacidad-provisionales.md`.

## 1. Qué pide la norma

- **PRD, "Motor de Validación de Capacidad Instalada":**
  - Se ejecuta **una sola vez por nivel**, después de capturar todo el Paso 3 (no por sub-paso).
  - Compara superficie, personal y mobiliario contra `reglas_validacion` / `mobiliario_conceptos`, con un mensaje por regla incumplida.
  - Muestra advertencias **sin bloquear el guardado**: el expediente puede quedar "con observaciones".
- **COMPENDIO §5, §5.1 y §5.2:** ratios por nivel, ya sembrados en `reglas_validacion` (28 filas) y `mobiliario_conceptos` (Inicial).

## 2. Qué datos existían al empezar (antes de construir los sub-pasos)

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

Consecuencia al empezar: sin Matrícula ni Plantilla, todas las reglas que dependen de ellas habrían salido "no verificado". A petición del owner se construyeron esos sub-pasos en la misma rama (§5), así que el motor ya se alimenta del flujo real.

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

- **Reglas por grado ofertado** (Inglés, Computación): `PENDIENTE-personal-condicionado-por-grado.md` sigue abierto; no hay filas sembradas.
- **Detalle granular** (puertas, escaleras, pasillos, sanitarios por rango): no está sembrado; diferido desde el plan original.
- **Validación de perfil profesional** del personal (carrera contra cargo).

## 5. Sub-pasos 4–6 del Paso 3 (pedido a mitad de la sesión)

Los tres eran stubs sin ruta; el hub los mostraba como "No disponible aún".

| Sub-paso | Página | Caso de uso | Escribe en |
|---|---|---|---|
| 4. Plan de estudios y modalidad | `/tramite/paso3/{nivel}/plan-estudios` | `RegistrarPlanEstudios` | columnas ya existentes de `escuela_niveles` (modalidad, turno, tipo de alumnado, referencia del plan, plataforma) |
| 5. Plantilla docente (Anexo 1) | `/tramite/paso3/{nivel}/plantilla` | `RegistrarPlantillaDocente` | `personal`, `personal_salas` (Inicial), `personal_asignaturas` (Secundaria) |
| 6. Matrícula | `/tramite/paso3/{nivel}/matricula` | `RegistrarMatricula` | `matricula_salas` (Inicial) o `matricula_grados` (resto) |

- **Compuerta:** los tres casos de uso ejecutan la compuerta Paso 2 / orden de Paso 3 (`CompuertaSubPaso3`, extraída para el código nuevo) y marcan su sub-paso con `MarcarPasoCompletado`.
- **Lecturas de la vista:** van por Application (`PlantillaCapturada`, `MatriculaCapturada`, ADR-006).
- **Flujo:** Mobiliario ahora continúa a Plan de estudios (antes regresaba al resumen). Matrícula cierra el nivel y regresa al resumen. Los tres sub-pasos se pueden reabrir desde el resumen con sus datos precargados.
- **Esquema:** **ningún cambio.** Todas las tablas y columnas ya estaban en el DDL v3. Lo único nuevo en base es un seeder, `GradosSeeder`: la tabla `grados` existía pero nunca se había sembrado, y la matrícula por grado la necesita. Está incluido en `DatabaseSeeder`.
- **Defecto encontrado y corregido:** `ResumenTramite::posicion()` numeraba mal los sub-pasos posteriores a Mobiliario en niveles distintos de Inicial ("Paso 8 de 9" donde tocaba "Paso 7 de 9"). `array_filter` conserva las llaves del arreglo. El error existía desde antes, pero no se veía porque Mobiliario era el último sub-paso con página.

## 6. Integración en "Validación final"

- **Dónde:** `EjecutarValidacionFinal` ejecuta el motor de capacidad para cada nivel de la escuela. La página y el PDF muestran una sección **"Capacidad instalada · {nivel}"** con lo requerido contra lo declarado y un enlace "Revisar la captura" al sub-paso correspondiente.
- **Efecto en el envío:** estas observaciones **no** cambian si el trámite puede enviarse (provisional P3).
- **Evaluación guardada:** `evaluaciones_validacion.resultados` pasa a `{documental, capacidad}`. Las filas anteriores, con la lista plana, se siguen leyendo.

## 7. Evidencia

### TDD

Cada paso empezó con pruebas que fallaron por la razón correcta: clase, ruta o propiedad inexistente, o el comportamiento del stub. Durante la sesión aparecieron dos fallas que eran defectos reales:

- **En el código:** una modalidad inválida disparaba también el error de plataforma. Se corrigió el código, no la prueba.
- **En una prueba nueva:** una prueba comparaba un arreglo asociativo cuyo orden dependía de la consulta. Se corrigió la prueba antes de escribir el código que valida.

### Mutaciones de no-vacuidad

Cada mutación fue temporal; el archivo se restauró de un respaldo después de cada una. En las últimas se verificó además con `php -l` que el archivo mutado compilara, para no confundir un error de sintaxis con una detección real.

| Mutación | Resultado |
|---|---|
| Comparación siempre `cumple` (motor) | 12 de 20 fallan |
| Sin sumar el espacio del maestro (Preescolar) | 1 de 20 falla |
| Predio con la matrícula del nivel en lugar de la del plantel (Domain) | 1 de 20 falla |
| Sin conteo de Educación Física por asignatura (Secundaria) | 1 de 20 falla |
| Mobiliario redondeando hacia abajo | 1 de 20 falla |
| Compuerta de orden de Paso 3 desactivada | 4 fallas + 15 errores de 19: `MarcarPasoCompletado` repite la misma compuerta |
| Sin exigir sala en cargos de Inicial | 1 error de 19: la restricción `NOT NULL` de `personal_salas` lo detecta una capa abajo |
| Plantilla sin reemplazo | 1 de 19 falla |
| Grupo repetido permitido (Matrícula) | 1 de 19 falla |
| Matrícula con cero alumnos permitida | 1 de 19 falla |
| Contar personal incompleto (lectura) | 1 de 7 falla |
| Predio solo con la matrícula del nivel (lectura) | 1 de 7 falla |
| Acervo contando todo tipo de material | 1 de 7 falla |
| Sanitarios contando los del personal | 1 de 7 falla |
| La capacidad bloquea el envío | 1 de 10 falla |
| Sin regreso a la validación final tras corregir (sesión anterior) | 1 de 8 falla |

### Pruebas existentes que cambiaron, y por qué

Todas son consecuencia directa de tener tres sub-pasos más. Ninguna se debilitó:

- **`ResumenTramiteTest`:** los totales de "Paso X de N" pasan de 6 a 9 (y de 7 a 10 en Inicial); los sub-pasos 4–6 ya no son "no disponible"; `completo` exige los seis sub-pasos. Se agregaron casos de posición para Matrícula.
- **`Paso3VistaTest`:** mismos totales en los encabezados.
- **`Paso3RequierePaso2CompletoTest`:** las tres rutas nuevas entran a su lista. Esa prueba existe para forzar que cada ruta de Paso 3 pase por la compuerta, y las nuevas pasan.
- **`AutorizacionEscrituraTest`:** las rutas nuevas y las de la validación final se agregaron a la verificación de Policy.
- **`Paso3MobiliarioNivelTest` y `Paso3OrdenSubPasosHttpRoundTripTest`:** Mobiliario ahora continúa a Plan de estudios.
- **`ResumenTramitePaginaTest`:** en lugar de "No disponible aún" verifica que Plantilla y Matrícula aparecen.
- **Fixture `CapturaExpedienteConsistente::completarTramite`:** ahora completa el Paso 3 con los casos de uso reales.

### PDF

Se generó un reporte de muestra: Primaria con 70 alumnos, 55 m² de aulas, sin Director Técnico y con 120 títulos. Se revisó visualmente. La sección de capacidad muestra "Observación" en ámbar y una línea de requerido contra declarado. Aparecieron y se corrigieron tres defectos de presentación: la etiqueta "Debe corregirse" en observaciones que no bloquean, el mensaje duplicado y "1 personas".

## 8. Resultados de verificación

Entorno: el mismo contenedor efímero (PostgreSQL 16 local, `sedeq_incorporacion_testing`). No se tocó ninguna base dev.

| Verificación | Resultado |
|---|---|
| Suite completa | **728/728** (PR #1: 653) |
| Pint `--test` | Pasa |
| PHPStan nivel 5 + PHPat | 0 errores. Se quitaron 4 `array_values` redundantes y se corrigió el tipo de `EvaluacionValidacion::$resultados` |

**No verificado:** las páginas nuevas (Plan de estudios, Plantilla, Matrícula y la sección de capacidad) **no se vieron en un navegador**. Las pruebas de Livewire y HTTP cubren contenido, enlaces y comportamiento, no la apariencia.

## 9. Pendiente

- Las elecciones provisionales están en `PENDIENTE-motor-capacidad-provisionales.md` (P1–P10).
- Notas agregadas a `PENDIENTE-origen-de-magnitud.md`, `PENDIENTE-umbral-educacion-fisica.md` y `PENDIENTE-perfiles-trabajador-social-prefecto.md`. Los tres siguen abiertos.
- Al mergear: `php artisan db:seed --class=GradosSeeder` en dev (idempotente), con confirmación del owner según CLAUDE.md.
