# WS-7b-1 — Edición de Paso 1 y Paso 2 hasta el envío (reporte)

**Fecha:** 2026-10-14 · **Rama:** `ws7b1-edicion` · **Plan:** `docs/superpowers/plans/2026-10-14-ws7b1-edicion-paso1-paso2.md` · **Spec:** `docs/superpowers/specs/2026-10-14-ws7b1-edicion-paso1-paso2-design.md`

## Qué se hizo
- U4: `ConstruirContextoValidacion` y `ValidarVigenciaDocumentos::ejecutar` solo leen documentos aplicables al tipo de persona actual; un documento de un tipo anterior queda oculto por construcción.
- U2: `RegistrarResponsableLegal` crea o actualiza en el lugar; terna por número de propuesta (conserva marcas de validez); regla de nombres distintos en el caso de uso; cambio de subtipo; D1 (descarte del Formato firmado tras commit).
- `Paso2Responsable`: formulario prellenado, sin redirecciones con responsable, resumen con terna, persona autorizada y gestor, aviso D2, aviso D1 con niveles, `?seccion=responsable|niveles`.
- U1: `ActualizarContactoPlantel`, ruta `tramite.plantel`, página "Datos del plantel" (domicilio de solo lectura).
- U3: `ResumenEliminacionNivel`; `RegistrarNivelesSeleccionados` con `agregar`/`quitar`, confirmación con conteos, bloqueo niveles → escuela → documentos, archivos tras commit, cuentas de responsables sin acceso eliminadas.
- Hub: Datos del plantel, Responsable legal y Niveles educativos ofrecen "Revisar" hasta el envío.

## Resumen por tarea
Implementadores: Sonnet u Opus según la tarea. Cada tarea tuvo un revisor Opus separado, con ronda de correcciones cuando hubo cambios solicitados. La rama completa tuvo una revisión final con Opus (Tarea 7).

| Tarea | Contenido | Quién | Resultado (suite) |
|---|---|---|---|
| 0 | Línea base | — | 1278 pruebas / 4262 aserciones, PHPStan 0, Pint limpio |
| 1 | U4: documentos ocultos | Sonnet implementa, Opus revisa | Aprobada; 1280 / 4266 |
| 2 | U2: `RegistrarResponsableLegal` upsert | Opus implementa, Opus revisa | Aprobada tras ronda de correcciones (prueba vacua reforzada, `firstOrFail`); 1297 / 4333 |
| 3 | `Paso2Responsable` prellenado, sin redirecciones, resumen, avisos D1/D2 | Opus implementa, Opus revisa | Aprobada tras ronda de correcciones (captura de `PrecondicionIncumplida`, 3 pruebas, detalles menores); 1308 / 4385 |
| 4 | U1: `ActualizarContactoPlantel`, ruta `tramite.plantel`, página Datos del plantel, enlaces "Revisar" del hub | Opus implementa, Opus revisa | Aprobada tras ronda de correcciones (etiqueta "Guardar cambios", prueba de domicilio rechazado, sin error de plantel duplicado); 1325 / 4431 |
| 5 | U3: `ResumenEliminacionNivel` y `RegistrarNivelesSeleccionados` agregar/quitar | Opus implementa, Opus revisa | Cambios solicitados y ronda de correcciones (conteos incluyen datos y pasos, prueba de rollback, la prueba de cascada demuestra archivos, guardia de cuenta por rol y sin fila de solicitante); 1344 / 4487 |
| 6 | Página de niveles: selección prellenada, diálogo de eliminación, enlace del hub | Opus implementa, Opus revisa | Cambios solicitados y ronda de correcciones (bloqueante: el botón Guardar se perdía por un espacio faltante en la etiqueta; estilo destructivo; prueba de ruta de abuso); 1354 / 4542 |
| 7 | Verificación de rama completa y revisión final | Controlador + Opus | Cambios solicitados y ronda de correcciones (ver abajo); 1358 / 4563 |

## Decisiones tomadas en la implementación (y por qué)
Resueltas por el plan frente al spec ("Spec gaps"). Respuesta del dueño a los puntos 1 a 3: **«ok, se mantienen los valores del plan»**.

1. **El Formato no imprime la terna ni el gestor.** El spec §2 afirmaba que el Formato imprime la terna; la plantilla `formato-solicitud.blade.php` solo carga `ternasNombres` y nunca la imprime, ni imprime `gestores`. D1 se implementa como "lo que la plantilla imprime hoy": tipo moral / no moral, `domicilio_notificaciones`, `persona_autorizada_recoger`, las columnas de `personas_fisicas` que imprime (`nombre`, `fecha_nacimiento`, `rfc`, `curp`) y las de `personas_morales` que imprime. Una edición solo de terna o solo de gestor, o física ↔ física con gestor, conserva el Formato. Si el dueño quiere que la terna cuente, hay que agregar filas `TernaNombre` a `huellaFormato()`.
2. **La primera captura conserva el flujo actual del asistente.** Se quitan todas las redirecciones de `mount()`; en `guardarResponsable()` se quitan solo para ediciones (la página se queda, muestra "Cambios guardados" y, tras un cambio de tipo, el aviso D2). El primer guardado de un responsable sigue yendo a Documentos / fase de niveles como hoy.
3. **Cómo se llega a la fase de niveles una vez que existen niveles.** Se agrega la cadena opcional `?seccion=responsable|niveles` en `/tramite/paso2/{escuela}`: `seccion` explícita gana; sin `seccion`, sin responsable → formulario vacío, con responsable y sin niveles y Paso 2 completo → fase de niveles (flujo de hoy), cualquier otro caso → formulario de responsable prellenado. El hub enlaza "Responsable legal" a `?seccion=responsable` y "Niveles educativos" a `?seccion=niveles`; el resumen de niveles tiene un enlace "Editar responsable legal".
4. **La regla de la terna vive en el caso de uso y la validación al perder foco de Livewire la reutiliza.** `RegistrarResponsableLegal::propuestaRepetida()` (estático público, puro) es la única comparación; `validar()` la usa y el cierre de Livewire al perder foco la llama en lugar de su propia comparación.
5. **Entrada de estado completo.** El upsert trata `DatosResponsableLegal` como el estado completo: un campo `null` se guarda como `null`, una propuesta `null` borra esa fila de la terna. Livewire siempre envía todos los campos.
6. **Archivos de prueba extra que el spec no listaba** y que codificaban el comportamiento anterior: `Paso2ResponsableTest.php:137-167` (dos pruebas de redirección a documentos), `Paso2ResponsableHttpRoundTripTest.php:59-69` y `ResumenTramiteTest.php:109,134,184`. Se reescriben en la tarea que cambia el comportamiento.
7. **`Paso2Documentos` no se cambia (decisión original del plan).** Al completarse los documentos seguía redirigiendo a `tramite.paso2`, con niveles eso caía en el formulario de responsable prellenado. Esto se modificó después en la ronda de correcciones de la revisión final (ver "Hallazgos de revisión").
- Orden de tareas: reescrituras de pruebas dentro de la tarea que cambia el comportamiento.

## Hallazgos de revisión que cambiaron el diseño
- **El spec se enmendó tras la revisión de Opus** (APROBAR CON CAMBIOS, todos los hallazgos incorporados): el Formato D1 pasó a ser "lo que la plantilla imprime"; la regla de la terna se movió al caso de uso; `agregar`/`quitar` explícitos en lugar de un conjunto completo (una pestaña vieja no puede borrar un nivel agregado en otra); cascada y cuentas de responsables sin acceso al quitar un nivel; filtro de documentos ocultos (U4) para que "oculto" sea cierto por construcción; bloqueo de orden niveles → escuela → documentos como en `EliminarTramite`.
- **Aspectos destacados de las rondas de correcciones:**
  - Botón Guardar perdido en la página de niveles por un espacio faltante en la etiqueta (bloqueante de la Tarea 6).
  - Prueba vacua de la Tarea 2 reforzada.
  - Guardia de cuenta: solo se elimina una cuenta sin acceso si tiene el rol `responsable_nivel` y no tiene fila de solicitante.
  - Los conteos de la confirmación de eliminación incluyen datos y pasos.
  - Revisión final de la rama: `Paso2Documentos` ya no redirige cuando el checklist está completo (completo + niveles → resumen); aviso de eliminación de datos de persona; una eliminación confirmada que no hace nada ahora muestra error; docblock.

## Evidencia
- Suite completa final: **1358 pruebas, 4563 aserciones** (línea base: 1278 / 4262). PHPStan nivel 5 + PHPat: 0 errores. Pint: limpio.
- La suite completa requiere `php -d memory_limit=1G` (con el límite por defecto se cae).
- El diff de la rama no toca `database/`, `docs/` ni `.gitignore` (verificado en la Tarea 7, antes de este reporte).
- Pruebas nuevas y reescritas por tarea (archivo y nombre): no verificado en este reporte; ver el diff de la rama.
- **Nunca se vio en un navegador:** no hubo verificación manual en navegador (no verificado).

## Lo que deliberadamente no se hizo
- Guardas de una sola escritura de Paso 3, filas viejas con superficies nulas, hueco M2 de matrícula, lista de descargas tras el envío (7b-2).
- Ninguna bandera de `escuela_nivel_pasos` se reinicia; `EnviarTramite` reevalúa todo.

## Límites conocidos
- "Datos del plantel" parece editable cuando un trámite hermano ya se envió; al guardar se muestra el error.
- Un Formato firmado viejo puede subirse tras editar el responsable si todavía no había uno subido (`RegistrarDocumento:168` solo comprueba turno/tipo).
- No hay aviso tras descartar el Formato firmado (D1).
- La página de Documentos completa solo ofrece "Volver al resumen".
- Dos primeras capturas simultáneas del responsable pueden chocar (carrera preexistente).
- El historial no se cuenta al quitar un nivel (solo `EnviarTramite` lo escribe).
- Escape cierra el diálogo de eliminación sin limpiar `porEliminar` (inofensivo); el clic en el fondo no lo cierra (inconsistente con otros diálogos).
- `EliminarTramite` aún deja cuentas de responsable huérfanas (asimetría con quitar un nivel; ver ADR-015).
- El spec §2 afirmaba que el Formato imprime la terna y no es así (ver punto 1).
- Ni `ResumenEliminacionNivel` ni `RegistrarNivelesSeleccionados` comprueban propiedad; los llamadores deben mantener `can:update,escuela`.

## Riesgos y preguntas abiertas
- El Formato no imprime la terna ni el gestor: si se agregan al formato, actualizar `huellaFormato()`.
- Cambiar de moral a física (o viceversa) borra los datos del subtipo anterior: volver al tipo anterior obliga a recapturarlos (los documentos sí se conservan).
- El teléfono y correo del plantel se comparten entre las escuelas del mismo solicitante (ADR-009); con un plantel compartido entre dos solicitantes, cualquiera podría sobrescribirlos.

## Git
Cambios sin commit en la rama `ws7b1-edicion` hasta que el dueño pida el merge. `docs/progress.md` lo escribe el controlador al hacer el merge, a partir de este reporte.
