# PENDIENTE: municipio y localidad como catálogo

**Estado:** resuelto el 2026-10-07 (decisión del dueño del proyecto). Antes `PENDIENTE-catalogo-municipios.md`.

## Resolución (2026-10-07)

`planteles.municipio` deja de ser texto libre: se captura con un **selector de los 18 municipios de Querétaro**. La localidad sigue siendo texto libre.

- Cambio de esquema autorizado por el dueño: tabla `municipios` (catálogo sembrado, 18 filas) y FK en `planteles` (y en `certificados_numero_oficial`, que también guarda municipio). Migración nueva; los datos de desarrollo se limpian, no se migran. El DDL se actualiza con la misma migración.
- Paso 1 y el formulario del certificado de número oficial pasan a un `<select>`. El motor documental deja de normalizar texto de municipio.
- Fuera de alcance: catálogo nacional (INEGI) y catálogo de localidades.
- Trabajo derivado: ver `ADR-014-decisiones-de-producto-2026-10-07.md`.

---

El resto del documento conserva la pregunta original.
**Fecha:** 2026-09-30
**Origen:** `docs/reports/2026-09-30-validacion-entradas.md`

## Pregunta

¿`planteles.municipio` (y `localidad`) deben dejar de ser texto libre y pasar a referenciar un catálogo (FK a una tabla de municipios de Querétaro)?

## Por qué importa

Es la mayor fuente de datos sucios que queda tras la validación de entradas: el mismo municipio puede capturarse como "Qro", "Querétaro" o "QUERETARO", y ninguna regla de formato lo distingue. En 3FN el nombre del municipio es un hecho del municipio, no del plantel. El motor documental (ADR-007, PR #1) compara domicilios y hoy tiene que normalizar texto para compensar.

## Qué implica

- Cambio al DDL v3 (tabla nueva + FK en `planteles`, y en `certificados_numero_oficial` si PR #1 se fusiona) y migración de datos existentes.
- Decidir el alcance: solo Querétaro (18 municipios) o catálogo nacional (INEGI).
- Localidad: catálogo INEGI (miles de filas) o sigue libre.

No se implementó: CLAUDE.md pide que todo cambio de esquema se derive del DDL/PRD.
