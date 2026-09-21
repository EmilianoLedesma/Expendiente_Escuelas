# PENDIENTE — Unión de infraestructura por nivel vs. guardián booleano por plantel

**Estado:** abierto
**Abierto:** 2026-09-21
**Requiere:** decisión del dueño del proyecto (arquitectónica, no normativa)
**Bloquea:** nada hoy en el flujo de un solo nivel. Sí deja datos inalcanzables
en cualquier escuela multi-nivel, y la rama se integra con el guardián
booleano tal como está.

## El conflicto

El spec de diseño de Paso 3 (`docs/superpowers/specs/2026-09-14-paso3-inmueble-infraestructura-mobiliario-design.md`)
afirma dos cosas a la vez:

- §1 "Decisiones ya cerradas", punto 1: la infraestructura a nivel plantel
  (`instalaciones_espacios`, `sanitarios`) es editable solo en la primera
  captura y de solo lectura en cualquier reutilización posterior, justificado
  porque el esquema ancla ambas tablas a `plantel_id` — no a `escuela_nivel_id`.
- §3: el formulario se genera dinámicamente desde `niveles_tipos_espacios`
  **para el nivel en curso**.

Ambas premisas son ciertas por separado, pero son inconsistentes juntas:
*qué* categorías de sanitario y *qué* tipos de espacio son capturables
depende del nivel. El conjunto de filas que un plantel "debe" no es fijo — es
una **unión sobre todos sus niveles**. Un guardián booleano por plantel
("¿ya existe alguna fila de este plantel?") no puede expresar una unión: solo
puede expresar "ya se capturó algo, alguna vez".

## Consecuencia concreta

En una escuela con más de un nivel, el que se captura **segundo** nunca llega
a capturar sus propios campos exclusivos:

- Si Primaria se captura primero, Inicial después nunca puede capturar
  `alumnado_maternal`, `personal` (categorías de sanitario exclusivas de
  Inicial), `cantidad_bacinicas`, ni el espacio `filtro_recepcion` que
  `TiposEspaciosSeeder` mapea solo a Inicial.
- Simétricamente: si Inicial se captura primero, Primaria después nunca
  captura `alumnado_masculino` / `alumnado_femenino`.

El mecanismo exacto: `InfraestructuraYaCapturada::ejecutar()` ya es `true`
para el plantel (el nivel que fue primero ya escribió filas), así que
`InfraestructuraNivel::mount()` pone `soloLectura = true` para el segundo
nivel. La vista en modo solo-lectura no renderiza ninguna rama de captura
(`resources/views/livewire/tramite/paso3/infraestructura-nivel.blade.php`,
bloque `@if ($soloLectura)`), y `guardar()` envía `espacios: []` y
`sanitarios: []` a `RegistrarInfraestructuraNivel` (que además, correctamente,
no escribe nada del plantel cuando `yaCapturada` es `true`). El paso se marca
`completado` de todas formas. No es un dato que falte por descuido del
solicitante: es un dato **inalcanzable por cualquier camino de la UI** para
ese nivel, y el futuro Motor de Validación evaluará ese nivel contra un
plantel que carece de sus propias filas.

## Por qué ningún test lo detectó

`Paso3InfraestructuraNivelTest::test_un_segundo_nivel_del_mismo_plantel_ve_la_infraestructura_en_solo_lectura`
prueba Primaria seguida de Preescolar. Ambos niveles comparten exactamente el
mismo conjunto de categorías de sanitario (`SANITARIOS_BASICA`) y el mismo
conjunto de tipos de espacio aplicables (ninguno de los dos es Inicial). Es
el único par de niveles que **no** rompe con este guardián; el par que sí
rompe (cualquiera con Inicial) nunca se ejercitó.

## Factor que agrava el problema, pero es preexistente

Hoy, Paso 3 multi-nivel es prácticamente inalcanzable desde la UI de todas
formas: `Paso2Responsable` enruta solo a
`escuelaNiveles()->orderBy('id')->first()`, la página de "próximos pasos" no
tiene enlace hacia un segundo nivel, y el widget de progreso no renderiza
anchors para navegar entre niveles. Hoy, el único camino al sub-paso 2 de un
segundo nivel es editar la URL a mano. Esta rama no introduce ese hueco de
navegación — lo hereda —, pero es la razón por la que el problema de la
unión permaneció invisible: nadie llega ahí navegando.

## Posible solución futura (sin decidir aquí)

Reemplazar el guardián único por plantel por un guardián por-categoría /
por-tipo: "¿este plantel ya tiene una fila para *esta* categoría de
sanitario / *este* tipo_espacio?", en vez de "¿tiene alguna fila?". Eso
tiene consecuencias propias sobre qué debe mostrar la vista de solo lectura
(mezclaría filas ya capturadas de un nivel con campos de captura pendientes
del nivel en curso, en la misma pantalla) que tampoco se deciden aquí.

## Qué se integró tal cual

La rama se fusiona con `InfraestructuraYaCapturada` como guardián único por
plantel, sin cambios de comportamiento respecto al diseño original. Este
documento dejó constancia del problema; no lo corrigió.
