# PENDIENTE — Dictámenes (Protección Civil / Uso de Suelo) por nivel educativo

**Estado:** abierto. Requiere respuesta de SEDEQ (norma), no del dueño del proyecto.
**Origen:** WS-5b (Paso 2.4 "Documentos por nivel"), spec `docs/superpowers/specs/2026-09-29-ws5b-documentos-por-nivel-design.md`.

## Pregunta

Los Requisitos de Educación Básica §14–15 indican que el visto bueno de Protección Civil y el dictamen de Uso de Suelo deben estar "emitidos para el nivel educativo que se ofertará". Hoy ambos documentos se capturan **una sola vez por plantel** en el Paso 2.2, sin importar cuántos niveles pida la escuela.

¿Deben capturarse **por nivel** (un dictamen por cada `escuela_nivel`) o basta uno por plantel que cubra todos los niveles solicitados?

## Decisión provisional

Se mantienen a nivel plantel (Paso 2.2). WS-5b solo movió al nivel los documentos que son inequívocamente por nivel (Formato de Solicitud, recibo de pago de derechos, acervo bibliográfico, inventario).

## Preguntas relacionadas para SEDEQ

- `certificado_numero_oficial` sigue como documento obligatorio del plantel en 2.2 para todos los niveles, pero Requisitos Básica §16 lo hace condicional; la condición depende de niveles elegidos después de 2.2. Se suma a la pregunta existente "condición del número oficial".
- `ratificado_notario` (comodato, `acreditaciones_ocupacion_legal`): `COMPENDIO_MAESTRO` confirma que el comodato debe ratificarse ante Notario Público **solo para Básica** (diferencia real frente a Inicial). Pero `escritura_inmueble` se captura a nivel plantel, igual que `dictamen_uso_suelo` y `visto_bueno_proteccion_civil`: un mismo comodato cubre todos los niveles que solicita la escuela. Si una escuela solicita Preescolar + Primaria bajo un mismo comodato, ¿la ratificación aplica por ser Básica, o depende de cómo se resuelva este mismo PENDIENTE? `ratificado_notario` existe hoy en el Form y en la BD pero sin control en la UI; no habrá regla de obligatoriedad condicional hasta resolverlo.

## Si la respuesta es "por nivel"

- Mover `dictamen_uso_suelo` / `visto_bueno_proteccion_civil` a `ambito = escuela_nivel` en el catálogo (migración nueva, mismo patrón que la de WS-5b).
- Los conteos de 2.2 bajarían (física 8 / moral 10 / gestor 9 hoy) y las escuelas que ya completaron 2.4 tendrían que volver a cargarlos (rollout similar al de WS-5a/5b).
- Al resolverse, renombrar este archivo a `ADR-00N-dictamenes-por-nivel.md` en el mismo commit.
