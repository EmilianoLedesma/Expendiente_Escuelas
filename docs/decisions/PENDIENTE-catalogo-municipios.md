# PENDIENTE: municipio y localidad como catálogo

**Estado:** abierto (decisión del dueño del proyecto; posible consulta a SEDEQ)
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
