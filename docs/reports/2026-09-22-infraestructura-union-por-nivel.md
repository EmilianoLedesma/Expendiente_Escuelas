# 2026-09-22 — Resolución de ADR-005 (unión de infraestructura por nivel)

**Rama:** `fix/infraestructura-union-por-nivel`
**Alcance:** corrección puntual, sin subagentes (bounded, un solo hilo de trabajo).

## Qué se hizo

Resuelto `docs/decisions/PENDIENTE-infraestructura-union-por-nivel.md` (ahora
`ADR-005-infraestructura-union-por-nivel.md`, `Estado: resuelto`), decidido
en conversación con el dueño del proyecto: reemplazar el guardián booleano
por plantel de `InfraestructuraYaCapturada` por un guardián por-fila.

### Archivos modificados

- `app/Application/Infraestructura/InfraestructuraYaCapturada.php` — nuevos
  métodos `tiposCapturados(int $plantelId): array` y
  `categoriasCapturadas(int $plantelId): array`; `ejecutar()` (bool) se
  conserva sin cambios, ya no tiene consumidores en producción pero sigue
  probado y es una señal general válida.
- `app/Application/Infraestructura/RegistrarInfraestructuraNivel.php` —
  `escribirEspacios()`/`escribirSanitarios()` saltan fila por fila (por
  `tipo_espacio_id`/`categoria`) en vez de saltar el bloque completo cuando
  el plantel "ya tenía algo".
- `app/Livewire/Tramite/Paso3/InfraestructuraNivel.php` — se elimina la
  propiedad `soloLectura`; `mount()` calcula los conjuntos ya capturados;
  `tiposAplicables()` y `categoriasSanitarios()` excluyen lo ya capturado
  (antes devolvían todo lo aplicable al nivel, sin filtrar); `guardar()` ya
  no necesita el condicional `$soloLectura ? [] : …` porque lo que se
  renderiza como editable ya es exactamente lo pendiente.
- `resources/views/livewire/tramite/paso3/infraestructura-nivel.blade.php` —
  de un `@if ($soloLectura) … @else … @endif` a dos bloques independientes:
  "Ya capturado" (si hay algo) y "Por capturar" (si queda algo pendiente).
- `tests/Feature/Livewire/Tramite/Paso3InfraestructuraNivelTest.php` —
  test nuevo `test_un_segundo_nivel_captura_sus_propios_campos_exclusivos_de_inicial`
  (Primaria capturada primero, Inicial después: verifica que `filtro_recepcion`,
  `alumnado_maternal` y sus bacinicas sí se escriben); el test anterior de
  "solo lectura" (Primaria + Preescolar) se reescribió como
  `test_un_segundo_nivel_no_vuelve_a_ofrecer_lo_que_el_plantel_ya_capturo`,
  porque la premisa original ("ambos niveles ven exactamente el mismo
  conjunto") resultó falsa: `biblioteca` es exclusivo de primaria/secundaria
  en `TiposEspaciosSeeder`, así que Preescolar sí tiene pendientes propios
  tras la corrección — lo que se prueba en su lugar es que lo ya capturado
  (`direccion`, `alumnado_masculino`) no se vuelve a ofrecer.

### TDD

Los dos tests nuevos/reescritos se corrieron primero contra el código viejo
y fallaron por la razón esperada (guardián booleano ocultando el formulario
completo) antes de tocar producción. Ver el primer `php artisan test
--filter=Paso3InfraestructuraNivelTest` de la sesión: 2 failed de 12,
mensajes coherentes con el defecto documentado en el ADR.

## Verificación

- `php artisan test` (suite completa): **303/303**, 676 assertions.
- `vendor/bin/pint --test` (archivos tocados): passed.
- `vendor/bin/phpstan analyse --memory-limit=512M` (nivel 5, proyecto completo): **0 errores**.

## Parte 2 — navegación entre niveles (misma rama, mismo día)

Con la unión ya corregida, se cerró el hueco de navegación que ADR-005
documentó como preexistente: la página de "próximos pasos"
(`Paso3ProximosPasos`) no enlazaba a un segundo `escuela_nivel` de la misma
escuela — el único camino era editar la URL a mano.

### Archivos modificados

- `app/Livewire/Tramite/Paso3ProximosPasos.php` — nuevo método público
  `nivelesPendientes()`: niveles hermanos de la misma escuela cuyos 3
  sub-pasos de Paso 3 (inmueble, infraestructura, mobiliario) no están
  `completado` en `escuela_nivel_pasos`. Lectura pura, sin caso de uso de
  Application (mismo patrón que `Progreso.php`, ver su propia nota de
  clase y ADR-001).
- `resources/views/livewire/tramite/paso3-proximos-pasos.blade.php` —
  bloque nuevo "Esta escuela tiene otros niveles por capturar" con un
  enlace a `tramite.paso3-inmueble` por cada nivel pendiente; no se
  renderiza nada si no hay ninguno (caso más común: escuela de un solo
  nivel).

### Deliberadamente fuera de esta parte

- **El widget de progreso** (`<x-tramite.progreso>`) no gana enlaces entre
  niveles — solo muestra el avance del nivel actual. Añadir eso ahí
  implicaría pasarle el listado de niveles hermanos al layout completo (no
  solo el `escuelaNivelId`), un cambio de forma distinta; la página de
  "próximos pasos" ya resuelve la navegabilidad real (es el punto donde el
  solicitante decide "qué sigue"). Si se quiere también en el widget, es un
  siguiente paso separado, no ampliado aquí.
- Paso2Responsable sigue enrutando solo al primer `escuelaNivel`
  (`orderBy('id')->first()`) tras seleccionar niveles — eso ya era el flujo
  correcto para *empezar*; el enlace nuevo cubre *volver* a un nivel que
  quedó a medias.

## Qué se dejó explícitamente fuera (de ambas partes)

- `PENDIENTE-inmueble-relectura-datos.md` y
  `PENDIENTE-matriz-espacios-por-nivel.md` — sin tocar, temas distintos.
- No se corrió el flujo en navegador real (dev DB); evidencia es la suite de
  tests, igual que en la sesión del 2026-09-21.
- Nada de esto se ha fusionado a `master` ni se ha pusheado — la rama queda
  lista para revisión.

## Verificación final (ambas partes)

- `php artisan test` (suite completa): **305/305**, 680 assertions.
- `vendor/bin/pint --test` (archivos tocados): passed.
- `vendor/bin/phpstan analyse --memory-limit=512M` (nivel 5, proyecto completo): **0 errores**.
