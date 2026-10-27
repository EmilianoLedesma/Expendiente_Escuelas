# PENDIENTE: aulas de Inicial por aula (tabla repetible)

**Estado:** abierto
**Abierto:** 2026-10-27 (decision D7 del plan Anexo 2 Instalaciones, tomada por el dueno el 2026-10-22: no cambiar la captura ahora)
**Requiere:** decision del dueno (alcance WS-6)
**Bloquea:** nada hoy. Afecta la exactitud de tres reglas de Inicial y el llenado del Anexo.

## Contexto

El Compendio (`docs/COMPENDIO_MAESTRO_Sistema_Incorporacion.md`, lineas 198-206, "Formato 3", seccion 5) pide para Inicial una tabla repetible por aula o sala con: identificador, capacidad promedio, superficie (m2), altura, ventilacion natural e iluminacion natural.

Hoy solo existe el agregado `aulas_nivel` (`numero_aulas`, `superficie_m2` por `escuela_nivel`); no hay filas por aula ni altura.

## Consecuencia actual

Las reglas `inicial.superficie.aula_lactantes`, `inicial.superficie.aula_maternales` e `infraestructura.altura_aulas` no se pueden evaluar: quedan `no_evaluable` / `sin_dato` por el mapa `SIN_DATO` de `app/Domain/Validaciones/Engine/ValidacionCapacidadService.php` ("la superficie por sala no se captura (solo el total de aulas)", "la altura de las aulas no se captura").

El Anexo 2 Instalaciones impreso muestra solo el agregado de aulas; la tabla por aula no existe en el documento (no hay formulario de Inicial, D6).

## Opciones

- (a) Tabla nueva `aulas_nivel_detalle` (una fila por aula: identificador, capacidad, superficie, altura, ventilacion, iluminacion) con migracion y su DDL, filas en el Formato 3, y agregado derivado (`numero_aulas` y `superficie_m2` calculados de las filas). Habilitaria evaluar las tres reglas. Es un cambio de esquema: requiere plan propio y el visto bueno del dueno.
- (b) Mantener el agregado. Las tres reglas siguen `no_evaluable`; costo cero.

## Recomendacion

Resolver dentro del alcance de WS-6 (Aulas por sala en Inicial), que ya contempla una tabla nueva. Hasta entonces, opcion (b).

Al resolverse: renombrar este archivo a `ADR-NNN-aulas-inicial-por-aula.md` en el mismo commit que lo resuelve.
