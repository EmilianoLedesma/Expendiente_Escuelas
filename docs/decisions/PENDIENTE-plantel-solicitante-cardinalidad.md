# PENDIENTE — Cardinalidad entre Plantel y Solicitante

**Estado: abierto.** No adjudicado por el agente. Requiere confirmación de SEDEQ o
una decisión de producto una vez que se observen patrones reales de captura.

## Por qué existe este documento

Surgió durante una revisión de arquitectura: al corregir la suposición de que el
domicilio de un plantel es libremente editable en Paso 3 (ver `docs/progress.md` y
`docs/PRD_Sistema_Incorporacion_MVP.md`, corrección del 2026-09-11), se planteó un
contraejemplo que expone una pregunta de modelo de datos no resuelta y que esa
corrección, por sí sola, no cierra: un mismo inmueble físico operado en turno
matutino por un dueño y en turno vespertino por otro — ¿es un escenario válido que el
sistema debe soportar, o `Plantel` debería estar acotado a un solo `Solicitante`?

## La pregunta exacta

¿Puede un mismo `Plantel` estar asociado a `Escuela`s de distintos `Solicitante`s
(ej. turno matutino operado por un dueño, turno vespertino por otro), o debe ser
**1 Plantel : 1 Solicitante** (permitiendo N `Escuela`s del mismo solicitante en ese
plantel, pero nunca de solicitantes distintos)?

## Evidencia para ambas lecturas (no se adjudica aquí)

- **A favor de permitir solicitantes distintos**: COMPENDIO confirma que un plantel
  puede alojar múltiples escuelas/niveles — el Anexo 2 pregunta explícitamente si ya
  se imparten otros niveles en la misma infraestructura. Nada en el texto revisado
  descarta que esos niveles pertenezcan a dueños distintos; el compendio nunca liga
  la pregunta de "otros niveles en el mismo domicilio" a la identidad del titular.
- **En contra (riesgo práctico)**: `documentos_plantel` (Dictamen de Uso de Suelo,
  Constancia de Seguridad Estructural) son documentos legales típicamente emitidos a
  nombre de un ocupante/titular específico del inmueble. Compartirlos entre
  solicitantes no relacionados es, en el mejor caso, ambiguo (¿a nombre de quién
  queda el documento compartido?) y en el peor, normativamente inválido si SEDEQ
  exige que el documento coincida con el solicitante que lo presenta.

## Estado actual en código: sin restricción

Hoy esto está **sin restringir** — ni a nivel de esquema ni de aplicación:

- `planteles` no tiene columna `solicitante_id` (confirmado, `docs/ddl_sistema_incorporacion_v3.sql:185-206`) — el plantel en sí no pertenece a nadie.
- `escuelas.solicitante_id` (`docs/ddl_sistema_incorporacion_v3.sql:307-314`) permite
  que cualquier solicitante adjunte una `Escuela` a cualquier `Plantel` ya existente,
  sin verificar si ese plantel ya tiene escuelas de otro solicitante.
- Este comportamiento ya está probado y es intencional en su alcance actual —
  `test_dos_solicitantes_distintos_obtienen_escuelas_distintas_en_el_mismo_plantel`
  en `IniciarTramiteNuevoTest` confirma que dos solicitantes distintos SÍ pueden
  obtener escuelas distintas en el mismo plantel hoy.

## Por qué esto no bloquea nada hoy

La corrección del 2026-09-11 (domicilio bloqueado a solo lectura cuando el plantel se
reutiliza) ya cierra el riesgo concreto de integridad de datos que originó esta
pregunta — sin importar cómo se resuelva la cardinalidad, ningún solicitante puede ya
sobrescribir el domicilio de un plantel que no está registrando por primera vez. Esta
pregunta, entonces, **no bloquea el diseño de Paso 3**; solo necesita resolverse
antes de que el producto tenga que presentar o restringir explícitamente en la UI el
comportamiento de compartir un plantel entre solicitantes (por ejemplo, si algún día
se necesita advertir, bloquear, o pedir confirmación adicional en ese escenario).

## Qué falta para resolverla

Una de dos:
- **Confirmación de SEDEQ**: cómo tratan en la práctica los casos de edificio
  compartido con dueños distintos (¿existen hoy? ¿se documentan como plantel único o
  como planteles separados con la misma dirección?).
- **Decisión de producto**, una vez que se observen patrones reales de captura en el
  MVP en uso — si el escenario de dueños distintos resulta ser raro o inexistente en
  la práctica, restringir a 1:1 podría ser la simplificación correcta sin esperar a
  SEDEQ.

No implementado aquí — este documento solo registra la pregunta.
