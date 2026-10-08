# PENDIENTE — Relectura de datos de inmueble ya capturados

**Estado:** resuelto el 2026-10-07: absorbido por WS-7. Antes `PENDIENTE-inmueble-relectura-datos.md`.

## Resolución (2026-10-07)

Se resuelve dentro de WS-7 ("edición hasta el envío", `PENDIENTE-edicion-hasta-envio.md`): Datos del inmueble debe poder leerse y corregirse hasta el envío, con la misma rama de solo lectura/edición que ya tiene Infraestructura del nivel. No se hace nada por separado.

---
**Abierto:** 2026-09-21
**Requiere:** decisión del dueño del proyecto (arquitectónica)
**Bloquea:** nada hoy en el flujo feliz. Sí impide corregir un dato mal
capturado en el sub-paso 1 desde cualquier punto del asistente.

## El conflicto

El spec de diseño de Paso 3 pide dos cosas para el sub-paso 1 (Datos del
inmueble) que resultan incompatibles tal como está implementado:

- §2: los datos de plantel ya capturados deben mostrarse en solo lectura
  cuando el plantel se reutiliza.
- §6: si el plantel ya tiene `metros_totales`, el sub-paso debe
  autocompletarse de inmediato, sin mostrar formulario.

`DatosInmueble::mount()` (ver `app/Livewire/Tramite/Paso3/DatosInmueble.php`)
implementa §6 y omite §2 por completo: cuando `metros_totales` ya está
definido, `mount()` redirige de inmediato al siguiente sub-paso —
incluyendo para el propio nivel que acaba de guardar esos datos—, y el
componente no tiene ninguna rama de solo lectura. Contrástese con
`InfraestructuraNivel`, que sí tiene una rama `soloLectura` que renderiza lo
ya capturado antes de redirigir.

## Consecuencia concreta

Si el solicitante teclea mal `metros_totales` o una colindancia en el
sub-paso 1, no hay ningún punto del asistente donde pueda revisar o corregir
ese dato: la primera vez que se guarda, cualquier visita posterior al
sub-paso (mismo nivel u otro nivel del mismo plantel) rebota de inmediato
al siguiente paso sin mostrar nada.

## Contraste explícito entre los dos sub-pasos

La mitad "autocompletar" de la asimetría entre Datos del Inmueble e
Infraestructura del Nivel es coherente y deliberada: son dos sub-pasos con
reglas de reutilización de plantel distintas, documentadas como tales. La
mitad "mostrar en solo lectura" no lo es — Infraestructura la implementa,
Datos del Inmueble no, y nada en el spec justifica omitirla ahí. Es una
omisión, no una decisión de diseño distinta.

## Qué se integró tal cual

La rama se fusiona con `DatosInmueble::mount()` redirigiendo sin rama de
solo lectura, sin cambios de comportamiento. Este documento dejó constancia
del problema; no lo corrigió.

## Actualización (2026-10-01)
La decisión D8 del brief de remediación ya fue respondida por el dueño y quedó absorbida en WS-7 ("edición hasta el envío"): ver `PENDIENTE-edicion-hasta-envio.md`. Este archivo sigue abierto porque la relectura y corrección de los datos del inmueble en el asistente aún no está implementada.
