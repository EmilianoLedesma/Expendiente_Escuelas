# PENDIENTE: Perfiles profesionales para Trabajador Social y Prefecto en Secundaria

**Estado:** abierto  
**Fecha de apertura:** 2026-09-24  
**Ámbito:** catálogos normativos

## Contexto

El Acuerdo 255 (vía COMPENDIO §5.2) y las reglas de validación seeded (`reglas_validacion`) requieren los siguientes cargos en Secundaria:
- **Trabajador Social**
- **Prefecto**

Ambos cargos han sido agregados a `cargos_puestos` en WS-3.3, y se encuentran presentes en `reglas_validacion` que rigen la cantidad de personal obligatorio/recomendado por nivel.

## El Problema

El Profesiograma de Secundaria (SEDEQ, ciclo 2023-2024) no incluye definiciones de perfiles profesionales aceptados para estos dos cargos. Sin una definición de perfiles:

- No pueden seeded filas en `perfiles_profesionales` que asocien estos cargos con carreras aceptadas.
- Un solicitante no puede declarar Trabajador Social o Prefecto sin especificar una carrera de acreditación.
- La validación de capacidad instalada no puede verificar que el personal cumple con requisitos mínimos.

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

**Nota:** Este pendiente permanece abierto hasta que SEDEQ proporcione la información requerida.
