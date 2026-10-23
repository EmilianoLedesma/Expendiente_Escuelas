# ADR-020 — Devolución por secciones al solicitante y reenvío de correcciones

**Estado:** resuelto el 2026-10-23 (rama `worktree-hud-devolucion`, HUD proyecto 3)
**Fecha:** 2026-10-22
**Relacionado:** ADR-001 (capas), ADR-015 (responsables por nivel), ADR-016 (roles supervisor/revisor), ADR-019 (edición hasta el envío), spec `docs/superpowers/specs/2026-10-22-hud-devolucion-design.md`, reporte `docs/reports/2026-10-22-hud-devolucion.md`

## Contexto

Con el proyecto 2 del HUD el revisor decide cada paso de un nivel; si todos quedan decididos y alguno se rechaza, el nivel pasa a `con_observaciones`. Hasta esta rama nada pasaba después: el solicitante no veía los motivos y no podía corregir, porque `TramiteEditable` trataba como «enviado» todo trámite con un nivel fuera de `en_captura` y bloqueaba a todos los escritores.

## Decisiones del propietario (spec §4)

1. Los rechazos son privados hasta que el revisor decide todos los pasos.
2. El solicitante corrige solo los pasos rechazados; lo aceptado queda bloqueado.
3. Tras el reenvío, el revisor revisa solo lo corregido; las decisiones aceptadas se conservan.
4. El revisor ve el valor nuevo junto al motivo anterior; sin instantáneas ni diferencias.
5. Aviso en la aplicación más correo.
6. Los efectos cruzados (una corrección o el envío de un nivel hermano cambian el resultado de una regla) levantan una marca para revisor y supervisor; nada se reabre solo.
7. Mismo revisor, sin límite de rondas.
8. La devolución es por **ronda de escuela**: nada llega al solicitante mientras algún nivel de la escuela siga `en_revision`.
9. El paso `niveles` rechazado se reabre con restricciones: sin altas; solo se quitan niveles `con_observaciones`.
10. El correo va al dueño del plantel y a los responsables por nivel.

## Decisión de diseño

### Ronda y paso reabierto
- **Ronda cerrada:** ningún nivel de la escuela en `en_revision` y al menos uno `con_observaciones` (`PasosReabiertos::rondaCerrada`). Solo con la ronda cerrada hay motivos visibles y pasos editables.
- **Paso reabierto:** ronda cerrada + decisión vigente `rechazado` en un nivel `con_observaciones`. Un paso general (`plantel`, `responsable`, `niveles`, `documentos`) se reabre si cualquier nivel lo rechazó; uno del nivel, solo en ese nivel (`PasosReabiertos::reabiertos`, `estaReabierto`).
- Sin tablas ni columnas nuevas: los pasos «corregidos» se derivan (sin decisión vigente y con un rechazo en `historial_decisiones_revision`, `ConsultarRevision`).

### Guarda por paso
- `TramiteEditable` sigue siendo la única definición de «editable». `esEditable`, `asegurarEscuela` y `asegurarPlantel` aceptan la clave del paso (y el nivel); sin paso, el significado es el anterior (todo `en_captura`). `EjecutarValidacionFinal::ejecutar` («validar de nuevo») nunca se reabre.
- Políticas `EscuelaPolicy::editarPaso` y `EscuelaNivelPolicy::editarPaso` (lista variádica de pasos); las rutas de edición usan `can:editarPaso,…`. `updateInmueble` y `editarCompartido` también consultan el paso (`inmueble`, `infraestructura`). Los pasos de `PasosRevision::SOLO_DUENO` (generales, inmueble, infraestructura) son solo del dueño; un responsable por nivel corrige los pasos de su nivel.
- Los escritores de Paso 1, 2 y 3 y de documentos pasan su clave de paso a la guarda.

### Cierre de ronda (DecidirPaso)
- `DecidirPaso` rechaza decisiones sobre un nivel `con_observaciones` (el nivel está con el solicitante).
- La decisión que deja la ronda cerrada la cierra: bloquea la escuela después de los niveles (orden `escuela_niveles` → `escuelas`), guarda una evaluación nueva como **línea base** de G6 (`evaluar(exigirCompleto: false)` + `guardar`; si falla se reporta y la decisión persiste, salvo un `DeadlockException`, que se relanza) y despacha `RondaCerrada` después del commit. `NotificarObservaciones` envía `ObservacionesEnTramite` al dueño (todos los motivos) y a cada responsable de nivel con motivos de su nivel.

### Reenvío
- `ReenviarCorrecciones` (solo el dueño, `EscuelaPolicy::reenviar`, ruta `tramite.reenviar`): exige ronda cerrada, cada paso reabierto completo según el hub sin el congelamiento de «enviado» (`ResumenTramite::sinCongelar`) y nada bloqueante entre las filas de los niveles devueltos; las filas documentales de la escuela solo bloquean si `documentos` se reabrió. Un hermano `aprobado` nunca bloquea.
- Efectos por nivel `con_observaciones`: borra sus decisiones rechazadas (y la de un paso reabierto derivado), recalcula `progreso_revision`, pasa a `en_revision` e inserta historial «Correcciones reenviadas por el solicitante». No toca `asignaciones_revision` ni `historial_decisiones_revision`, ni guarda evaluación.

### Aviso «Resultado cambiado desde que se aceptó» (G6)
- `ResultadosCambiados` marca, para cada paso con decisión vigente `aceptado`, las filas del motor que en la última evaluación guardada (la línea base del cierre de ronda, o la del envío) eran `cumple` y ahora no lo son. Solo lectura: nunca cambia decisiones ni estados; si el motor falla devuelve vacío y lo reporta. Se muestra en `RevisionNivel` y en la vista del supervisor (`TramiteLectura`).

## Fallos de la revisión final (resueltos en la rama)
- **Paso `responsable` reabierto ⇒ `documentos_nivel` derivado.** Corregir lo que imprime el Formato de solicitud lo descarta (`RegistrarResponsableLegal`), pero ahora solo en niveles `en_captura`/`con_observaciones`; un nivel aprobado conserva su Formato. Para que el solicitante pueda volver a cargarlo, `PasosReabiertos` reabre `documentos_nivel` en cada nivel `con_observaciones` cuando `responsable` está reabierto, con un motivo genérico (`ObservacionesDeEscuela::MOTIVO_FORMATO`). **Anchura aceptada (decisión del dueño):** se reabre aunque el revisor hubiera aceptado `documentos_nivel` y aunque el responsable no cambie nada que imprima el Formato; el reenvío borra esa decisión y el revisor la vuelve a tomar.
- **Compuertas de Paso 2 congeladas durante una ronda cerrada.** `EstadoPaso2::etapaFaltante` da por satisfecha la etapa de documentos generales como al envío (una vigencia vencida o un cambio de tipo de persona no bloquean los pasos reabiertos ni el reenvío), salvo que `documentos` mismo esté reabierto. Costo: un cambio de tipo de persona vía `responsable` no vuelve a exigir los documentos hasta que el revisor ve la marca G6.
- **«Reporte enviado» frente a línea base.** Los lectores del reporte enviado (`ReporteValidacionGuardado::rutaEnviada`, `ResumenTramite`, `ConsultarTramiteSedeq`) leen la evaluación guardada al envío (`UltimaValidacionFinal::idEnviada` / `enviadaParaEscuela`); G6 sigue leyendo la última.

## Límites conocidos y aceptados
- **Heurística de `idEnviada`:** «la primera evaluación con `created_at` ≥ la primera fila `en_revision` del historial», con precisión de segundos. Una evaluación previa al envío guardada en el mismo segundo se tomaría como la enviada. Arreglarlo exige una columna que marque la evaluación enviada (decisión de esquema del dueño); queda señalado con un comentario `ponytail:` en `UltimaValidacionFinal`.
- **En la ronda 2 o posterior, el «reporte enviado» es el de la ronda 1** (el del envío original): el reenvío no guarda evaluación y la línea base del cierre de ronda no cuenta como reporte enviado.
- **Línea base por escuela, reiniciada en cada cierre de ronda:** una marca G6 desaparece si otra ronda cierra después, porque la comparación es contra la última evaluación guardada; el título dice «desde que se aceptó» pero compara contra la última línea base.
- **ADR-019 sigue aplicando** sobre `inmueble` e `infraestructura` reabiertos: en un plantel con varios niveles que ya capturaron Paso 3, solo se pueden completar campos vacíos (N1).
- Un paso compartido editado sin mover ninguna fila del motor no se detecta (sin instantáneas, spec §5.7). Un nivel hermano `aprobado` puede quedar con una aceptación sobre datos generales que cambiaron; solo G6 lo avisa (spec R3). Un nivel aprobado conserva un Formato firmado con datos anteriores del responsable.
- Un reenvío sin cambios es posible (spec R4): el revisor vuelve a rechazar.

## Consecuencias
- Toda nueva ruta o caso de uso que escriba datos del trámite debe pasar su clave de paso a `TramiteEditable`; sin paso, queda bloqueado durante una devolución.
- Cualquier lectura del solicitante sobre rechazos debe pasar por `ObservacionesDeEscuela` (vacía con la ronda abierta).
