# Rediseño UI del wizard (sesión de cierre) — accesibilidad, contraste y reporte

Rama `feat/rediseno-ui`. Plan: `docs/superpowers/plans/2026-09-24-rediseno-ui-wizard.md` (Tareas 1–9) +
`docs/superpowers/plans/2026-09-25-rediseno-ui-direccion-b.md` (enmienda de dirección visual B, tareas B1–B3).
Spec: `docs/superpowers/specs/2026-09-24-rediseno-ui-wizard-design.md`.
Ledger de ejecución: `.superpowers/sdd/2026-09-24-rediseno-ui-wizard/progress.md`.

Esta es la Tarea 10 (última): pase de accesibilidad estático, cálculo de contraste, verificación en
navegador (autorizada, DB de pruebas aislada) y este reporte.

## 1. Resumen

Se rediseñó la interfaz completa del trámite de incorporación (hub "Mis trámites", resumen del trámite,
los 7 pasos del wizard, las pantallas de autenticación) sobre un nuevo sistema de componentes
(`x-ui.*`, `x-tramite.*`, `x-shell.*`) con tokens de color y tipografía propios, reemplazando el
Tailwind/Breeze genérico original. La dirección visual pasó por dos rondas: una primera pasada
(Tareas 1–9) y una Dirección B aprobada por el propietario a mitad de sesión (mockup
`B-recorrido.html`) que introdujo la barra lateral "Recorrido", la identidad del trámite (Nº, nombre,
avance) y pulidos de tono B3 (etiquetas de estado más discretas, tabla de "Mis trámites" más silenciosa,
h1 del hub = nombre de la escuela, sin cuadro verde, recorte de domicilio).

Este reporte cubre exclusivamente la Tarea 10: no repite el trabajo de tareas anteriores salvo donde
hace falta para el punto 4 (aserciones que cambiaron) y el punto 6 (pendientes).

## 2. Tareas y commits

| Tarea | Commit(s) |
|---|---|
| 1 — Tokens, shells, foco/movimiento global | `a1e2df3..3ee1567` |
| 2 — Componentes `x-ui.*` (field, input, alert, action-bar…) | `3ee1567..64fc458` |
| 3 — Hub "Mis trámites" + Resumen del trámite | `64fc458..05b6787` |
| B1 — Identidad del trámite (nombre, fecha, Nº, avance) | `05b6787..9c2afa3` |
| B2 — Shell, sidebar "Recorrido", tabla "Mis trámites", hub | `9c2afa3..0f6436f` (+ fix round `0f6436f..4101b9c`) |
| 4 — Paso 1 (Datos del plantel) | `4101b9c..9e92293` |
| 5 — Paso 2 (Responsable legal / Niveles) | `9e92293..bf407a4` |
| 6 — Paso 2 (Documentos) | `bf407a4..bce150b` |
| 7 — Paso 3 (Inmueble, Infraestructura, Mobiliario, próximos pasos) | `bce150b..9d9b7bf` |
| B3 — Pulido de tono (status-tag, tabla, h1, domicilio) | `9d9b7bf..f8ec914` |
| 8 — Páginas de autenticación sobre el shell de invitado | `f8ec914..a6fd485` |
| 9 — Eliminación de `<x-tramite.progreso>` (reemplazado por Recorrido) | `a6fd485..c6ad59b` |
| 10 — Este reporte + corrección de accesibilidad en Mobiliario | *pendiente de commit* (ver §7) |

Base de la rama: `a1e2df3` (429 tests). Estado al cierre de la Tarea 9: `c6ad59b`, 514/514.

## 3. Decisiones y desviaciones de la spec

### 3.1 Tabla D1–D12 (plan original, traducida)

| # | Spec original | Lo implementado | Por qué |
|---|---|---|---|
| D1 | "Revisar" en toda fila completada abre la vista de solo lectura de hoy | `accion = 'revisar'` solo para **Infraestructura** y **Mobiliario**; Responsable / Documentos / Niveles / Datos del inmueble completados muestran "Completado" **sin enlace** (`ResumenTramite::REVISABLES`) | Esas cuatro páginas redirigen *hacia adelante* al completarse (`mount()` de Responsable, Documentos, Datos del inmueble); un enlace "Revisar" rebotaría al usuario al Paso 3. Cambiar esos `mount()` es WS-7. **Pregunta al propietario Q1 — respondida: opción (a), sin cambio de comportamiento.** |
| D2 | `x-ui.field` con prop `error-key` | Se eliminó; el `id` del campo es la clave de error | Una sola convención (id = clave de error = ruta `wire:model`) en vez de dos que pueden desalinearse. |
| D3 | Lista de tokens §3.3 | Se agrega `control: #6B7280` para bordes de controles de formulario | `hairline #e5e7eb` sobre blanco da 1.24:1, falla WCAG 1.4.11 (no-texto 3:1); `#6B7280` da 4.83:1. |
| D4 | Estados de `x-ui.status-tag` = estados de sección | Se agrega `error` ("Requiere corrección") y un `texto` opcional | Necesario para la fila de documento vencido (§5) y las etiquetas "En captura" / "Captura inicial completa" de "Mis trámites". |
| D5 | `x-ui.button-primary/secondary` "sustituidos o fusionados" | Restilizados en el mismo lugar (44 px, foco, prop `href` renderiza `<a>`) | Mantiene válidos todos los sitios de invocación existentes a mitad de rama; solo se elimina `x-ui.text-input` (Tarea 9). |
| D6 | Cambio de redirección para login / registro / verificación / dashboard | También cambia los otros dos "hogares" por defecto: `confirm-password` y el reenvío de verificación del perfil cuando ya está verificado | Es la misma decisión de "a dónde llega un solicitante por defecto"; dejar dos casos sueltos en `tramite.preregistro` sería inconsistente. |
| D7 | — | `ResumenTramite::encabezado()` construye el eyebrow y devuelve `null` (nunca lanza) para una clave sin posición | `MobiliarioNivel` redirige en `mount()` para niveles sin mobiliario; un helper que lanzara excepción en `render()` sería riesgo de 500. |
| D8 | — | Títulos "Preregistro" → "Datos del plantel", "Selección de niveles" → "Niveles educativos", "Infraestructura del nivel" → "Infraestructura"; botones de envío "Continuar" → "Guardar y continuar" | Alinea con los nombres de sección del hub y la barra de acciones de la spec. Ninguna prueba existente afirmaba los textos anteriores. |
| D9 | — | La alerta `bifurcacion` del Paso 1 y la alerta `cantidades` de Mobiliario se sustituyen por el resumen de errores; Documentos conserva su alerta `vigencia` (el resumen la excluye) | Evita repetir el mismo mensaje dos veces; la spec §5 pide una alerta específica para vigencia. |
| D10 | — | Los id de input de Mobiliario `concepto-{id}` → `cantidades.{id}` | Regla id = clave de error. Ninguna prueba referenciaba `concepto-`. |
| D11 | Páginas de auth "mismos campos/comportamiento" | El copy de verify-email / reset-password / confirm-password pasa de inglés (Breeze) a español | El resto de las páginas de auth ya estaban en español; ninguna prueba afirmaba los textos en inglés. |
| D12 | — | `tramite.paso3-proximos-pasos` redirige al hub **incondicionalmente** (mantiene `can:view,escuelaNivel`, elimina la compuerta de Paso 3) | No escribe nada; el hub ya muestra el estado real y el motivo de bloqueo de una fila. Spec §2.2. |

### 3.2 Dirección B (enmienda del 2026-09-25, aprobada por el propietario)

La pasada visual inicial (Tareas 1–3) fue juzgada "genérica" por el propietario antes de continuar a la
Tarea 4. Se generaron mockups A/B con las skills `frontend-design` + `ui-ux-pro-max`; el propietario
eligió la **Dirección B** (`B-recorrido.html`), que reemplazó la spec §3 e introdujo:

| Ítem | Resuelto en | Descripción |
|---|---|---|
| B1 | `9c2afa3` | Identidad del trámite: nombre de la escuela, fecha de inicio, "Trámite Nº…", barra de avance en el eyebrow/encabezado. |
| B2 | `0f6436f`/`4101b9c` | Shell de dos columnas, barra lateral "Recorrido" (identidad + navegación de secciones, `<details>` en móvil), tabla "Mis trámites" rediseñada, workspace del hub; se retira el widget "Progreso" antiguo antes de lo previsto (Tarea 9 original). |
| B3 | `f8ec914` | Pulido pedido tras revisar la vista previa de B2: etiqueta de estado más discreta, tabla más silenciosa, `h1` del hub = nombre de la escuela, se retira el recuadro verde, se recorta el domicilio (arreglo de un bug de `', '` colgante que también existía en el código postal — corregido en la misma tarea). |

Ruling registrado en el ledger para B2: los dos fallos de `Paso3ProximosPasosTest` que surgieron con B2 se
corrigieron en la misma ronda de B2 (no se esperó a la Tarea 7), para mantener la suite verde en las
Tareas 4–6; ver §4.

## 4. Aserciones de pruebas existentes que cambiaron

### 4.1 Tabla original del plan (resumen; ver el plan para la lista completa línea por línea)

Cambios de ruta por D6 (login/registro/verificación/dashboard/confirm-password/perfil):
`tramite.preregistro` → `tramite.index`, en `AuthenticationTest`, `RegistrationTest`,
`EmailVerificationTest`, `PasswordConfirmationTest`, `ProfileTest`.

Cambios en el flujo Paso 3 → hub (Tarea 7, D12): `Paso3ProximosPasosTest` renombrada y cambiada de
`assertOk()+assertSee(...)` a `assertRedirect(route('tramite.resumen', …))`; se elimina el test del
widget de progreso (cubierto ahora por `ResumenTramiteTest`); `Paso3OrdenSubPasosHttpRoundTripTest` y
`Paso3RequierePaso2CompletoTest` ajustadas de forma equivalente.

### 4.2 Adiciones de la enmienda Dirección B

| Tarea | Prueba | Antes → después |
|---|---|---|
| B1 | `Application/Tramite/ResumenTramiteTest::test_encabezado` | `'Paso 2 de 4 · Responsable legal'` → `'Datos generales · Paso 2 de 4'`; `encabezado('infraestructura','primaria')` → `encabezado('infraestructura', $primariaModel)` = `'Primaria · Paso 6 de 6'` |
| B2 | `Tramite/MisTramitesTest::test_lista_solo_las_escuelas_del_solicitante` | `assertSee('Ver trámite')` → `assertSee('Continuar')`: una fila en captura ahora ofrece "Continuar"; "Ver trámite" queda para filas completas |
| B2 | `View/Components/Tramite/ProgresoTest` (8 pruebas) | Eliminada junto con el componente (movido de la Tarea 9 a B2); el mapeo de cada caso queda igual que en la Tarea 9 original |

### 4.3 Mapeo de `ProgresoTest` (eliminado en B2, ver Tarea 9 del plan)

El componente `<x-tramite.progreso>` fue reemplazado por `<x-tramite.recorrido>` (barra lateral con
identidad + lista de secciones). Cada una de las 8 aserciones de `ProgresoTest` sobre el widget de
puntos de progreso queda cubierta por `ComponentesTramiteTest`/`RecorridoTest` (estados
completado/actual/bloqueado/no-disponible) y por `ResumenTramiteTest` (origen de los datos).

### 4.4 Corrección de la Tarea 10 (este pase)

`Paso3MobiliarioNivelTest::test_el_error_de_una_cantidad_se_muestra_junto_al_campo` — **nueva**, TDD
completo (ver §7): fija el defecto descrito en §6.1.

## 5. Contraste y checklist de accesibilidad

### 5.1 Tabla de contraste (Paso 1)

Salida real del script del Paso 1 (Node, sin dependencias) sobre todos los pares del plan más los 5
pares añadidos por la enmienda de la Tarea 10:

```
success-ink/soft #1E6B34 #E8F5EC 5.83
warning-ink/soft #8A4B08 #FEF3E2 6.19
error-ink/soft #B42318 #FDECEA 5.75
info-ink/badge-blue #242B57 #e8edf8 11.49
muted/blanco #4B5563 #ffffff 7.56
muted/hairline-soft #4B5563 #f3f4f6 6.87
muted/surface-soft #4B5563 #f8f9fa 7.17
muted anterior/blanco #707F8F #ffffff 4.10
on-dark/surface-dark #ffffff #242B57 13.48
primary/blanco #242B57 #ffffff 13.48
error-ink/blanco #B42318 #ffffff 6.57
control/blanco (no texto) #6B7280 #ffffff 4.83
hairline/blanco (no texto) #e5e7eb #ffffff 1.24
foco/blanco (no texto) #4996C4 #ffffff 3.26
foco/surface-soft (no texto) #4996C4 #f8f9fa 3.09
muted/primary-disabled #4B5563 #e5e7eb 6.10
on-dark/80 (mezclado) sobre surface-dark #D3D5DD #242B57 9.20
accent barra sobre identidad (no texto) #4996C4 #242B57 4.13
primary sobre surface-card (Aquí estás) #242B57 #f5f7fa 12.56
success-ink círculo (no texto) #1E6B34 #ffffff 6.55
accent como texto (NO usar; referencia) #4996C4 #ffffff 3.26
```

Todos los pares de texto pasan AA (≥4.5:1 texto normal, ≥3:1 texto grande) o el 3:1 de no-texto según
corresponda. `hairline/blanco` (1.24) es exclusivamente decorativo (separadores, bordes de panel) y no
transmite información por sí solo. `foco` (3.26/3.09) y `accent como texto` (3.26) están documentados
como "no usar como texto" — se usan como anillo de foco / barra decorativa (no-texto, umbral 3:1, ambos
cumplen), y el eyebrow de "Aquí estás" usa `primary` (12.56:1) en vez de `accent`, tal como predijo la
nota del plan.

Los 5 pares nuevos de la enmienda coinciden con lo esperado: `on-dark/80` ≈9.20 (esperado ≈10:1, la
aproximación del plan era sobre el valor sin mezclar con la opacidad real del token; el valor medido
sigue siendo AA-aa para texto pequeño de apoyo, que es su único uso — fecha de inicio, "Trámite Nº"),
`accent` sobre `surface-dark` ≈4.13 (esperado ≈4.1, es la barra de avance, no texto), `primary` sobre
`surface-card` = 12.56 (muy por encima de 4.5), `success-ink` en el círculo del paso completado = 6.55
(no-texto, muy por encima de 3:1), y la confirmación de que `accent` como texto (3.26) queda por debajo
de AA — de ahí que el eyebrow use `primary`.

### 5.2 Checklist de accesibilidad por pantalla (Paso 2 — revisión estática)

Revisión de los archivos Blade y del HTML renderizado por las pruebas de característica existentes; no
requirió app corriendo. ✓ = cumple, — = no aplica a esa pantalla.

| Pantalla | h1 único / orden | label/legend | error `id`+`aria-invalid`+`aria-describedby` | resumen `role=alert` + anclas | orden de foco / skip link | `min-h-11` | 375 px sin ancho fijo | iconos `aria-hidden` |
|---|---|---|---|---|---|---|---|---|
| Mis trámites | ✓ | — | — | — | ✓ | ✓ | ✓ | ✓ |
| Resumen | ✓ | — | — | — | ✓ | ✓ | ✓ | ✓ |
| Paso 1 (Datos del plantel) | ✓ | ✓ (`x-ui.field`/radio-group `fieldset+legend`) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Responsable | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Niveles | ✓ | ✓ (`fieldset`+`legend`, checkboxes) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Documentos | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Inmueble | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Infraestructura | ✓ | ✓ (labels `md:sr-only` en tabla responsive) | ⚠ ver §6.1 (inputs sueltos fuera de `x-ui.field`) | ✓ | ✓ | ✓ | ✓ | ✓ |
| Mobiliario | ✓ | ✓ (`<label for>` por fila) | ✓ **corregido en esta tarea** (ver §6.1/§7) | ✓ | ✓ | ✓ | ✓ | ✓ |
| Login | ✓ | ✓ | ✓ | ✓ | ✓ (`autofocus` en el primer campo) | ✓ | ✓ | ✓ |
| Registro | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Recuperar contraseña | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Restablecer contraseña | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Verificar correo | ✓ | — | — | — | ✓ | ✓ | ✓ | ✓ |
| Confirmar contraseña | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

Verificaciones transversales (grep sobre `resources/views`, todas las vistas rediseñadas):
- `tabindex` positivo: ninguno.
- `w-[…]` fuera de `max-w-[…]`: ninguno fuera de `welcome.blade.php` (plantilla por defecto de Laravel,
  fuera del alcance del rediseño).
- Solo una copia de "Secciones del trámite" queda expuesta por ancho (móvil: `<details>` con
  `lg:hidden`; escritorio: `hidden lg:block` — mutuamente excluyentes por CSS, nunca ambas visibles a
  la vez, confirmado leyendo `resources/views/components/tramite/recorrido.blade.php`).
- `aria-current="page"` en "Mis trámites" del encabezado y en "Resumen del trámite" del Recorrido cuando
  corresponde; `aria-current="step"` en la sección activa del Recorrido — confirmado en
  `recorrido-pasos.blade.php` y `shell/encabezado.blade.php`.
- La barra de acciones (`x-ui.action-bar`) es `sticky bottom-0` solo desde `sm:`; el `<html>` lleva
  `sm:scroll-pb-28` para que el desplazamiento a un ancla de error nunca deje el campo enfocado bajo la
  barra fija (WCAG 2.4.11) — confirmado en `layouts/tramite.blade.php` línea 2 (comentario explícito) y
  `components/ui/action-bar.blade.php`.
- El estado nunca se transmite solo por color: `x-ui.status-tag` siempre lleva texto (`status-tag.blade.php`).

## 6. Lo que quedó pendiente a propósito

1. **Mobiliario — error en línea por concepto apuntando a un elemento nunca renderizado (Tarea 7).**
   Encontrado y **corregido en esta tarea** (ver §7): el `aria-describedby="cantidades.{id}-error"`
   generado por `x-ui.input` no tenía elemento correspondiente porque la tabla de Mobiliario usa el
   input suelto (sin `x-ui.field`, que es el que renderiza el `<p id="{id}-error">`). Se agregó un
   párrafo de error en línea directamente en `mobiliario-nivel.blade.php`, con TDD.
2. **Infraestructura — mismo patrón de aria-describedby "colgante" en inputs sueltos de tabla
   (`espacios.*`, `sanitarios.*`, `materialesBiblioteca.*`) y el select de distancia (Tarea 7, deferred).**
   No se corrige en esta tarea: a diferencia de Mobiliario (una sola columna de cantidad por fila, fix
   de una línea), Infraestructura tiene 3–7 campos sueltos por fila en varias secciones; una solución
   uniforme requeriría un componente nuevo (p. ej. una variante de `x-ui.input` para uso fuera de
   `x-ui.field` que renderice su propio error) — cambio de alcance mayor al de "restaurar un elemento
   de error dentro de la vista ya rediseñada" que pidió esta tarea. Además, el ledger nota que las
   claves de error de `RegistrarInfraestructuraNivel` (`.tipoEspacioId`, `.categoria`) no tienen `id`
   correspondiente en la UI actual (preexistente, la interfaz no puede producir esos errores hoy).
3. **Revisión por teclado / 375 px en navegador real / auditoría Lighthouse — bloqueada por entorno,
   no por autorización.** El propietario autorizó explícitamente estas verificaciones contra
   `sedeq_incorporacion_testing_ui` (server aislado en `:8001` vía `--env=browsercheck`, dev DB nunca
   tocada). Se preparó el entorno completo (`.env.browsercheck` con `DB_DATABASE` y `APP_URL`
   sobrescritos, verificado con `tinker --env=browsercheck` antes de cualquier escritura,
   `migrate:fresh --seed --env=browsercheck --force`, un solicitante falso creado con factories,
   `npm run build`, servidor arrancado en `:8001`) pero las herramientas de Chrome DevTools MCP
   disponibles en esta máquina solo buscan un ejecutable de Google Chrome (`chrome.exe`) en las rutas
   estándar de Windows, y esta máquina no tiene Chrome instalado (solo Microsoft Edge, que las
   herramientas no aceptan como destino). El servidor se detuvo y `.env.browsercheck` se eliminó sin
   dejar rastro (nunca estuvo en `git status` como *tracked*; solo apareció como `??` y se borró antes
   de cualquier commit). Queda pendiente repetir este paso desde una máquina con Chrome disponible.
4. **Mensajes de validación en inglés (`APP_LOCALE=en`).** El `.env` del proyecto (y el `.env.example`)
   configura `APP_LOCALE=en`, así que los mensajes de validación de Laravel que no están sobrescritos
   explícitamente en español (la mayoría de textos de la UI sí lo están, a mano, en cada Blade) salen en
   inglés cuando el framework los genera automáticamente. No es un defecto introducido por esta rama.
5. **Texto "Cambiarlos requiere autorización previa de la Dirección de Educación…" contradice la
   decisión WS-7.** Aparece en `paso2-responsable.blade.php` (resumen de responsable ya capturado) e
   `infraestructura-nivel.blade.php` (resumen de infraestructura del plantel ya capturada). WS-7 decidió
   en otra sesión que esos datos sí deberían poder reabrirse para edición sin ese requisito de
   autorización previa; el texto quedó desalineado con esa decisión y debe corregirse cuando WS-7 se
   implemente (fuera del alcance de este rediseño puramente visual).
6. **Líneas desactualizadas de `CLAUDE.md`** (a actualizar por el controlador al fusionar, no por esta
   tarea, según la disciplina "un solo escritor" del repo): el árbol de arquitectura todavía lista
   `Paso3ProximosPasos` (eliminado en la Tarea 7 — pasó a redirigir sin vista propia) y
   "View/Components (3)" (el conteo cambió con los componentes `x-shell.*`/`x-tramite.*`/`x-ui.*`
   agregados en esta rama).
7. **`nivel-posgrado` sin token de color de nivel.** `resources/views/components/tramite/nivel.blade.php`
   asigna un color por clave de nivel educativo; la clave `posgrado` no tiene entrada, así que cae al
   color por defecto en vez de tener uno propio. No afecta la accesibilidad (el color nunca es la única
   señal, el nombre del nivel siempre está en texto) — es una mejora visual pendiente, no un defecto.
8. **N+1 preexistente en `ListarTramitesDelSolicitante`** (detectado en B1, comentado con `ponytail:` en
   el propio código, no empeorado por esta rama).
9. **Deuda menor documentada en el ledger de ejecución, sin impacto de comportamiento:** duplicación
   plan-mandada de bloques `@error` en `field`/`radio-group` (Tarea 2); `tests/TestCase.php` comparte un
   `$errors` bag vacío entre pruebas (Tarea 2, fuera de la lista de archivos del brief pero necesario
   para el helper `blade()`); redundancia de una consulta de `Escuela` (route binding + refetch en
   `ResumenTramite::paraEscuela`, Tarea 3, plan-mandada); `ShellTest` de "Mis trámites" solo cubre el
   estado vacío (B2); la prueba de renderizado del Recorrido no afirma que una fila completada
   no-revisable carezca de enlace (B2); el bloque móvil `tr/td` de la tabla de "Mis trámites" pierde
   semántica de tabla nativa a favor de las clases responsivas (`block`/`table-row`), decisión del
   brief (B2, revisado en esta tarea — no se encontró alternativa sin duplicar la fila); repetición del
   llamado de 6 atributos de `documento-row` en 5 ramas y de un `<h2>` por fila (Tarea 6, plan-mandada);
   comentario obsoleto en `Paso3ProximosPasosTest.php:40` (Tarea 7); grep del reporte de la Tarea 9
   sobre referencias a `Progreso` incluye coincidencias fuera de alcance (comentarios de migración,
   nombre de la clase de prueba) que no son referencias vivas al componente eliminado.

## 7. Evidencia

### 7.1 TDD del hallazgo de esta tarea (§6.1)

**RED** — `$env:DB_DATABASE='sedeq_incorporacion_testing_ui'; php artisan test --filter=test_el_error_de_una_cantidad_se_muestra_junto_al_campo`:

```
Failed asserting that '...aria-describedby="cantidades.1-error" aria-invalid="true" ...' contains
"id=\"cantidades.1-error\"".
```

(el `aria-describedby` apuntaba a un `id` que nunca se renderizaba — falla por la razón correcta).

**GREEN** — mismo comando tras agregar el párrafo de error en `mobiliario-nivel.blade.php`:

```json
{"tool":"phpunit","result":"passed","tests":7,"passed":7,"assertions":20,"duration_ms":2708}
```

(las 7 pruebas de `Paso3MobiliarioNivelTest`, incluida la nueva).

### 7.2 Verificación final

```
$env:DB_DATABASE='sedeq_incorporacion_testing_ui'; php artisan test
{"tool":"phpunit","result":"passed","tests":515,"passed":515,"assertions":1372,"duration_ms":120722}

vendor\bin\pint --test
{"tool":"pint","result":"passed"}

vendor\bin\phpstan analyse --memory-limit=512M
{"tool":"phpstan","result":"passed","errors":0}

npm run build
✓ built in 990ms
```

515/515 (514 al cierre de la Tarea 9 + 1 prueba nueva de esta tarea), Pint limpio, PHPStan sin errores
(incluye la regla PHPat de ADR-001), build de Vite exitoso.

### 7.3 Archivos modificados en esta tarea

- `app-laravel/resources/views/livewire/tramite/paso3/mobiliario-nivel.blade.php` — párrafo de error en
  línea por concepto (`id="cantidades.{id}-error"`), corrige el `aria-describedby` colgante.
- `app-laravel/tests/Feature/Livewire/Tramite/Paso3MobiliarioNivelTest.php` — prueba nueva que fija el fix.
- `docs/reports/2026-09-25-rediseno-ui.md` — este reporte.
