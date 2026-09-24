# PENDIENTE: Perfiles profesionales para Trabajador Social y Prefecto en Secundaria

**Estado:** abierto  
**Fecha de apertura:** 2026-09-24  
**Ámbito:** catálogos normativos

## Contexto

El Acuerdo 255 (vía COMPENDIO §5.2) y las reglas de validación seeded (`reglas_validacion`) requieren los siguientes cargos en Secundaria:
- **Trabajador Social**
- **Prefecto**

Ambos cargos han sido agregados a `cargos_puestos` en WS-3.3, y se encuentran presentes en `reglas_validacion` que rigen la cantidad de personal obligatorio por nivel (ambas reglas son `personal_umbral`, condicionadas a >60 alumnos — ninguna regla seeded para estos cargos es "recomendado").

## El Problema

El Profesiograma de Secundaria (SEDEQ, ciclo 2023-2024) no incluye definiciones de perfiles profesionales aceptados para estos dos cargos. Sin una definición de perfiles:

- No pueden seeded filas en `perfiles_profesionales` que asocien estos cargos con carreras aceptadas.
- **Impacto futuro** (aún no aplica hoy): un solicitante no podrá declarar Trabajador Social o Prefecto sin especificar una carrera de acreditación, y la validación de capacidad instalada no podrá verificar que el personal cumple con requisitos mínimos. Estas dos consecuencias dependen de la captura del Anexo 1 (`app/Livewire/Tramite/Paso3/PlantillaDocente.php`), que hoy sigue siendo un stub sin formulario — no bloquean nada operativo todavía, solo el trabajo futuro de ese paso.

## Decisión Requerida

**Para SEDEQ:** 
¿Cuáles son los perfiles profesionales (carreras y documentos de acreditación) aceptados para:
1. **Trabajador Social** en Secundaria (¿Lic. en Trabajo Social? ¿Psicología?, ¿otros?)
2. **Prefecto** en Secundaria (¿qué requisitos de carrera/especialización?)

**Documento de referencia:**  
- Profesiograma oficial de Secundaria (SEDEQ)
- Acuerdo 255 o norma que defina estos cargos

## Impacto

- **Bloqueado:** No se pueden seeder `perfiles_profesionales` para estos cargos.
- **Riesgo:** Escuelas de Secundaria que necesitan declarar estos cargos según `reglas_validacion` quedarán sin perfiles válidos hasta que se resuelva.

---

## Hueco relacionado: `secundaria.personal.educacion_fisica` sin `cargo_puesto_id`

Distinto del problema de perfiles de arriba, pero en el mismo vecindario: la
regla `secundaria.personal.educacion_fisica` (`ReglasValidacionSeeder.php`,
tabla `secundaria`) se siembra sin `cargo` (por lo tanto `cargo_puesto_id`
queda `null`), a diferencia de `preescolar.personal.educacion_fisica` y
`primaria.personal.educacion_fisica`, que sí se enlazan a un cargo real
("Docente de Educación Física").

La razón no es que falte un cargo por sembrar — es estructural. En
Secundaria, Educación Física no es un cargo propio: es una **asignatura**
que imparte el cargo "Docente Titular", el único cargo de Secundaria con
`requiere_asignatura = true` (`CargosPuestosSeeder.php`). COMPENDIO §5.1 lo
confirma: la lista de "Asignaturas con perfil definido" para Secundaria
incluye "Educación Física" junto con Biología, Español, Física, etc., todas
impartidas por "un Docente Titular por cada asignatura" (líneas 487–489),
no por un cargo dedicado por asignatura.

`reglas_validacion` no tiene columna `asignatura_id` (ver
`docs/ddl_sistema_incorporacion_v3.sql`, `CREATE TABLE reglas_validacion`) —
solo `cargo_puesto_id`. Enlazar
`secundaria.personal.educacion_fisica` correctamente requeriría distinguir
"Docente Titular de la asignatura Educación Física" de "Docente Titular" en
general, lo cual el esquema actual no permite sin una de dos cosas: (a) una
columna `asignatura_id` nueva en `reglas_validacion` (cambio de esquema), o
(b) resolver esa relación en el Motor de Validación en tiempo de ejecución
en vez de en el seed (decisión de diseño del Motor, no solo de datos). Esto
no se resuelve en este documento — es una decisión de esquema o de Motor
pendiente, no una pregunta para SEDEQ.

---

**Nota:** Este pendiente permanece abierto hasta que SEDEQ proporcione la información requerida.
