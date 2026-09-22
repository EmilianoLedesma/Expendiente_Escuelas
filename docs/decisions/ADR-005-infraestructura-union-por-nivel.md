# ADR-005 — Unión de infraestructura por nivel vs. guardián booleano por plantel

**Estado:** resuelto
**Abierto:** 2026-09-21
**Resuelto:** 2026-09-22
**Decidido por:** dueño del proyecto (arquitectónica, no normativa)

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

## Consecuencia concreta (antes de esta decisión)

En una escuela con más de un nivel, el que se capturaba **segundo** nunca
llegaba a capturar sus propios campos exclusivos: si Primaria se capturaba
primero, Inicial después nunca podía capturar `alumnado_maternal`,
`personal`, `cantidad_bacinicas`, ni el espacio `filtro_recepcion`. El
mecanismo: `InfraestructuraYaCapturada::ejecutar()` era `true` para el
plantel en cuanto el primer nivel escribía cualquier fila, así que
`InfraestructuraNivel::mount()` ponía `soloLectura = true` para el segundo
nivel, y su formulario no renderizaba ninguna rama de captura.

`Paso3InfraestructuraNivelTest::test_un_segundo_nivel_del_mismo_plantel_ve_la_infraestructura_en_solo_lectura`
no lo detectó porque probaba Primaria + Preescolar, el único par que
comparte exactamente el mismo conjunto de categorías/tipos aplicables; el
par que rompe (cualquiera con Inicial) nunca se ejercitó.

## Factor que agrava el problema, pero es preexistente

Paso 3 multi-nivel sigue siendo prácticamente inalcanzable desde la UI: la
página de "próximos pasos" y el widget de progreso no tienen enlaces hacia
un segundo nivel; el único camino es editar la URL a mano. Esta decisión no
resuelve esa navegación — solo corrige que, cuando sí se llega ahí (a mano o
cuando se conecte la navegación), el segundo nivel ya no pierde sus datos.

## Decisión

Reemplazar el guardián booleano por plantel por un guardián por-fila:
`InfraestructuraYaCapturada` gana `tiposCapturados(int $plantelId): array`
(ids de `tipos_espacios` ya escritos) y `categoriasCapturadas(int $plantelId): array`
(categorías de `sanitarios` ya escritas), en vez de responder solo
verdadero/falso. `RegistrarInfraestructuraNivel` escribe fila por fila,
saltando solo las que ya existen para ese tipo/categoría — no todo el bloque.

Para la vista de solo lectura mixta (rows ya capturadas de otro nivel +
campos aún pendientes de este nivel, en la misma pantalla): **secciones
separadas** — "Ya capturado" (solo lectura, todo lo que el plantel ya tiene)
seguido de "Por capturar" (editable, solo lo aplicable a este nivel que aún
no existe). `tiposAplicables()` y `categoriasSanitarios()` ya excluyen lo
capturado, así que la sección editable es exactamente lo que se envía en
`guardar()`.

## Qué se integró

- `InfraestructuraYaCapturada::ejecutar()` se conserva (señal general "algo
  ya se capturó"), sin nuevos consumidores en producción.
- `RegistrarInfraestructuraNivel::escribirEspacios()`/`escribirSanitarios()`
  saltan fila por fila, no el bloque completo.
- `InfraestructuraNivel::soloLectura` se elimina; `mount()` calcula
  `tiposCapturados`/`categoriasCapturadas`, y `tiposAplicables()`/
  `categoriasSanitarios()` ya devuelven solo lo pendiente.
- La vista se parte en dos bloques (`@if` independientes), en vez de un
  `@if ($soloLectura) … @else … @endif`.
- Cobertura nueva: `test_un_segundo_nivel_captura_sus_propios_campos_exclusivos_de_inicial`
  (el par Primaria→Inicial que antes nunca se ejercitó) y
  `test_un_segundo_nivel_no_vuelve_a_ofrecer_lo_que_el_plantel_ya_capturo`
  (reemplaza el test de "solo lectura" original).

Detalle completo y evidencia de verificación: `docs/reports/2026-09-22-infraestructura-union-por-nivel.md`.
