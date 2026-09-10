# PENDIENTE — Owner scoping de la ruta de Paso 2 (`/tramite/paso2/{escuela}`)

**Estado: resuelto (2026-09-09).** Ver "## Resolución" al final de este documento.

## Por qué existe este documento

ADR-002 (`docs/decisions/ADR-002-modelo-identidad-solicitante.md`) establece que una
`Escuela` pertenece a un `Solicitante` (`escuelas.solicitante_id`, `NOT NULL`,
`restrictOnDelete()`) — ese es el modelo de propiedad formal del proyecto. La ruta
`/tramite/paso2/{escuela}` (`routes/web.php`), añadida como placeholder mientras Paso 2
seguía sin construirse, está protegida por el middleware `auth` pero por ningún control
adicional: cualquier usuario autenticado puede sustituir el `{escuela}` de la URL por el
id de una escuela que no le pertenece. Esto es un gap concreto en la aplicación de
ADR-002, no una re-declaración de "Paso 2 no está diseñado todavía" — el parámetro ya
existe hoy, acepta cualquier id, y colisionará en cuanto Paso 2 tenga lógica real detrás.

## El riesgo

**IDOR (Insecure Direct Object Reference).** Un solicitante autenticado puede ver o,
una vez Paso 2 tenga lógica de escritura, potencialmente modificar el expediente de otro
solicitante simplemente cambiando el id en la URL — sin que exista ningún control que
compare la propiedad de la `Escuela` contra el usuario autenticado.

## Forma de la corrección (sin decidir la implementación)

Antes de que cualquier lógica real de Paso 2 se ejecute, debe verificarse:

```
escuela.solicitante_id === auth()->user()->solicitante->id
```

Este documento no adjudica **cómo** aplicar ese control — una Policy de Laravel
(`EscuelaPolicy` + `$this->authorize(...)`), un route model binding con scope, o un
chequeo inline al inicio del método del componente Livewire son todas opciones
válidas. Queda a criterio del arquitecto al diseñar Paso 2, no de este documento.

## Consecuencia de no resolverlo

Hoy es inofensivo porque `/tramite/paso2/{escuela}` solo renderiza un placeholder sin
leer ni escribir el registro (`resources/views/tramite/paso2-placeholder.blade.php`).
En el momento en que Paso 2 gane lógica real (lectura del `responsable_legal`,
formularios, escritura), este gap se vuelve explotable de inmediato — el mismo patrón
estructural que el 419 de namespace de Livewire (ver `docs/decisions/ADR-003-namespace-livewire.md`):
un problema que, si no se cierra antes de construir, se repite en cada paso del wizard
que reciba un `{escuela}` en su ruta (Paso 3.1–3.6 también lo harán).

## Estado de este documento

Sin fix aplicado. No se ha tocado código de rutas, middleware ni Livewire como parte de
este documento. Marcado como pendiente de decisión del arquitecto sobre el mecanismo de
enforcement (Policy vs. chequeo inline vs. route-model-binding con scope) — debe
resolverse antes o durante el diseño de Paso 2, no después.

## Resolución (2026-09-09)

Cerrado como parte de construir Paso 2 (`docs/superpowers/specs/2026-09-09-paso2-responsable-niveles-design.md`,
`docs/superpowers/plans/2026-09-09-paso2-responsable-niveles.md`), no por
separado. Mecanismo elegido: `app/Application/Escuelas/VerificarPropietarioEscuela`
(decisión pura) + `app/Policies/EscuelaPolicy` (delega, no reimplementa) +
middleware `can:view,escuela` con route-model binding implícito en
`/tramite/paso2/{escuela}`. Extendido a `/tramite/paso3/{escuelaNivel}`
con la misma forma (`VerificarPropietarioEscuelaNivel` +
`EscuelaNivelPolicy`).

`EscuelaPolicy::view`/`EscuelaNivelPolicy::view` hoy conflan "es dueño"
con "puede ver" — nota de simplificación MVP dejada en el docblock de
ambas clases, no resuelta aquí: en cuanto el panel SEDEQ necesite `view`
sin ser dueño, ambas Policies deben dejar de hacerlo.
