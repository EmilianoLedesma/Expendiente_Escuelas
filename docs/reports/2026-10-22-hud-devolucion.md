# HUD proyecto 3 — Devolución al solicitante y reenvío de correcciones (reporte)

**Fecha:** 2026-10-22 / 2026-10-23 · **Rama:** `worktree-hud-devolucion` (base `3f44d65`, commits `b46822c` a `147701a` más el de documentación) · **Plan:** `docs/superpowers/plans/2026-10-22-hud-devolucion.md` · **Spec:** `docs/superpowers/specs/2026-10-22-hud-devolucion-design.md` · **Decisión:** `docs/decisions/ADR-020-devolucion-por-secciones.md`

## Qué se hizo
Cuando el revisor termina todos los niveles de una escuela con al menos un rechazo, el solicitante recibe aviso, corrige solo los pasos rechazados y reenvía; el revisor revisa solo lo corregido.

1. **`PasosReabiertos`** (T1, `app/Application/Tramite/`): ronda cerrada (ningún nivel `en_revision`, al menos uno `con_observaciones`) y lista de pasos reabiertos; fixture de pruebas `tests/Concerns/ConTramiteDevuelto.php`.
2. **`TramiteEditable` consciente del paso** (T2): `esEditable`, `asegurarEscuela` y `asegurarPlantel` aceptan clave de paso y nivel; sin paso, el significado anterior.
3. **Políticas y rutas por paso** (T3): `EscuelaPolicy::editarPaso`, `EscuelaNivelPolicy::editarPaso`; las rutas de edición usan `can:editarPaso,…`.
4. **Escritores con la guarda por paso** (T4–T6): `ActualizarContactoPlantel`, `RegistrarResponsableLegal`, `RegistrarNivelesSeleccionados` (con `niveles` reabierto: sin altas, solo quitar niveles `con_observaciones`, al menos uno), `RegistrarDocumento`, `RegistrarDatosNivel`, `MarcarPasoCompletado`, `RegistrarDatosInmueble`, `RegistrarInfraestructuraNivel`, `EditarInfraestructuraCompartida`.
5. **`DecidirPaso`** (T7): no decide sobre un nivel `con_observaciones`; al cerrar la ronda bloquea la escuela, guarda la línea base de G6 y despacha `RondaCerrada` después del commit.
6. **Lectura del solicitante** (T9): `ObservacionesDeEscuela` (dueño: todo; responsable por nivel: solo pasos de su nivel); el Resumen marca las secciones reabiertas con su motivo y bloquea el resto; «Mis trámites» muestra «Con observaciones» solo con la ronda cerrada.
7. **Notificación** (T8): `ObservacionesEnTramite` (correo «Tu trámite tiene observaciones») vía el listener `NotificarObservaciones`.
8. **`ReenviarCorrecciones`** (T10): ruta `POST /tramite/{escuela}/reenviar` (`tramite.reenviar`, solo el dueño) y botón «Reenviar correcciones» en el Resumen.
9. **Revisor** (T11): `ConsultarRevision` agrega `corregido` y `ultimoMotivo`; `RevisionNivel` lista primero los pasos corregidos y oculta las decisiones mientras el nivel está con el solicitante.
10. **Aviso G6** (T12): `ResultadosCambiados` y el aviso «Resultado cambiado desde que se aceptó» en `RevisionNivel` y en la vista del supervisor (`TramiteLectura`).
11. **Ola de correcciones de la revisión final** (ver `final-fixwave-report.md` del ledger): inmueble e infraestructura reabiertos se guardan (políticas `updateInmueble`/`editarCompartido` por paso); cada guardado durante la devolución vuelve al Resumen (`CompuertaPaso3::redirigirTrasGuardar`); corregir el responsable solo descarta el Formato de los niveles devueltos y reabre su `documentos_nivel`; Paso 2 congelado durante la ronda cerrada (`EstadoPaso2`); el «reporte enviado» es la evaluación del envío (`UltimaValidacionFinal::idEnviada`); pruebas vacías corregidas.
12. **Cierre (T13, esta sesión):**
    - `DevolucionPaso2CongeladoTest`: la prueba del cambio de responsable ahora cambia de verdad lo que imprime el Formato (nombre del titular; el tipo de persona solo cuenta como moral/no moral en la huella), comprueba que el Formato se descartó, que el Paso 3 del nivel redirige a 2.4 antes de recargarlo, y luego recarga, corrige y reenvía. Se verificó que con el nombre anterior la prueba falla («El Formato debe haberse descartado»).
    - `ObservacionesDeEscuela::MOTIVO_FORMATO` pasa a ser declarativo («Se reabrió porque el responsable legal está en corrección: …»): lo lee también el responsable del nivel, que no puede corregir el responsable legal. La aserción de `DevolucionResponsableTest` se vio fallar antes del cambio.

Sin migraciones ni columnas nuevas. Un commit lateral fuera del plan (`80fa412`, «documentos generales completos ofrecen revisar») ya está en `master` como `aadbeea`.

## Decisiones
Las del dueño y las de diseño están en ADR-020. Resoluciones del controlador durante la ejecución (ledger `.superpowers/sdd/2026-10-22-hud-devolucion/progress.md`):
- La guarda vive en cada paso: `asegurarPlantel` recibe además el nivel (inmueble e infraestructura son pasos del nivel escritos a ámbito plantel).
- Las políticas reciben los pasos como argumentos variádicos (Laravel parte los argumentos de `can:` por comas).
- Quitar todos los niveles sigue lanzando la `InvalidArgumentException` existente.
- `DecidirPaso` bloquea la escuela después del nivel para serializar los cierres de ronda (un revisor demostró una carrera de cierre perdido).
- La línea base se evalúa sin exigir el hub completo (`evaluar(exigirCompleto: false)`): con la ronda cerrada el hub no está completo y la línea base nunca se guardaba.
- Las filas documentales de la escuela solo bloquean el reenvío si `documentos` se reabrió.
- Un reenvío sin cambios se permite (R4).
- Responsable reabierto ⇒ `documentos_nivel` derivado reabierto en los niveles `con_observaciones` (regla b; la anchura de esa reapertura se aceptó, ver ADR-020).
- Durante la ronda cerrada las compuertas de Paso 2 se dan por satisfechas, salvo `documentos` reabierto.
- El «reporte enviado» es la primera evaluación guardada desde el envío; G6 lee la última.

## Evidencia
Base de pruebas: `sedeq_incorporacion_testing_ui` (nunca la de desarrollo). Suite completa en bloques por directorio con `php -d memory_limit=1G`:

| Bloque | Pruebas | Aserciones | Resultado |
|---|---|---|---|
| tests/Unit | 241 | 407 | verde |
| tests/Architecture | 0 (las reglas PHPat corren dentro de PHPStan) | — | — |
| Feature/Auditoria | 8 | 13 | verde |
| Feature/Auth | 49 | 218 | verde |
| Feature/Database | 152 | 351 | verde |
| Feature/Filament | 3 | 3 | verde |
| Feature/Notifications | 8 | 21 | verde |
| Feature/Policies | 47 | 207 | verde |
| Feature/Sedeq | 8 | 71 | verde |
| Feature/Tramite | 101 | 559 | verde |
| Feature/Validacion | 72 | 322 | verde |
| Feature/View | 77 | 725 | verde |
| Feature/Application/Captura | 17 | 50 | verde |
| Feature/Application/Documentos | 265 | 956 | verde |
| Feature/Application/EscuelaNiveles | 48 | 111 | verde |
| Feature/Application/Geografia | 25 | 61 | verde |
| Feature/Application/Infraestructura | 39 | 113 | verde |
| Feature/Application/Tramite (corrida aparte, tras los cambios de esta sesión) | 219 (218 + 1 omitida preexistente) | 621 | verde |
| **Subtotal corrido en esta sesión** | **1379** | **4809** | **0 fallos** |

**Corrida incompleta:** Claude Code detuvo la corrida por falta de memoria del sistema (no por un fallo de las pruebas) antes de llegar a Application/{Inmueble, Matricula, Mobiliario, Personal, PlanEstudios, Preregistro, ResponsableLegal, Revision, Validaciones}, Livewire/{Sedeq, Tramite} y los archivos raíz de Feature, Feature/Application y Feature/Livewire. No se volvió a lanzar. Esos bloques estaban en verde en la ola de correcciones anterior (`08430fb`, ver `final-fixwave-report.md` del ledger); los cambios de esta sesión solo tocan `ObservacionesDeEscuela::MOTIVO_FORMATO` y dos pruebas. Falta volver a correrlos antes de fusionar.

Pint `--test`: limpio. PHPStan nivel 5 + PHPat: 0 errores. No hubo verificación manual en navegador (no verificado).

## Lo que quedó sin hacer (deliberadamente)
- **Heurística de `idEnviada`** (precisión de segundos): una evaluación previa guardada en el mismo segundo del envío se tomaría como la enviada. El arreglo exacto necesita una columna; es decisión de esquema del dueño.
- **Ronda ≥ 2:** el «reporte enviado» sigue siendo el de la ronda 1.
- **Línea base por escuela reiniciada en cada cierre de ronda:** una marca G6 puede desaparecer cuando cierra otra ronda.
- **ADR-019 sigue aplicando** sobre inmueble/infraestructura reabiertos (N1): en planteles con varios niveles solo se completan campos vacíos.
- **Anchura de `documentos_nivel` derivado:** se reabre aunque estuviera aceptado y aunque el responsable no cambie nada que imprima el Formato (decisión del dueño, no se acotó). Un nivel aprobado conserva un Formato firmado con datos anteriores.
- **Reactivar el envío real de correo** (`MAIL_MAILER`/`QUEUE_CONNECTION`): pedido por el dueño condicionado a la aprobación; no se tocó el `.env`.
- **Menores diferidos del ledger:**
  - `estaReabierto` hace dos consultas por llamada y `rondaCerrada` corre varias veces por petición (cachear si pesa).
  - `rondaCerrada` sería verdadera con una mezcla `en_captura` + `con_observaciones` que la aplicación no produce hoy.
  - Los casos de uso de Paso 3 no verifican que el nivel pertenezca al plantel antes de `asegurarPlantel` (inalcanzable hoy; defensa en profundidad).
  - No hay prueba con dos escuelas del mismo plantel editando inmueble/infraestructura reabiertos mientras la otra está en revisión (por diseño, solo G6 avisa).
  - El PDF de la línea base puede quedar huérfano si el COMMIT falla (mismo compromiso que `EnviarTramite`); no hay prueba del relanzamiento de `DeadlockException`.
  - Con `QUEUE_CONNECTION=sync`, un destinatario que falla aborta los siguientes (solo desarrollo).
  - `ResumenTramite::sinCongelar` usa una bandera mutable con `try/finally`; la constante de etapa de `PrecondicionIncumplida` sin ronda cerrada es `EN_REVISION` (cosmético).
  - Pruebas del revisor simulan el reenvío con SQL directo en dos archivos en vez de llamar a `ReenviarCorrecciones`; el resaltado `bg-surface-soft` puede no verse junto a `bg-canvas`; la lista se reordena al decidir un paso corregido.
  - El motor corre unas dos veces por carga de `RevisionNivel` y una por nivel visible en la vista del supervisor.
  - `ObservacionesDeEscuela::paraUsuario` duplica la regla de `ResumenTramite::nivelesVisibles`.

## Recorrido manual propuesto para el dueño
1. Enviar un trámite de dos niveles; como revisor, rechazar un paso del nivel A (por ejemplo, Plan de estudios) y aceptar todo el nivel B.
2. Antes de decidir B, comprobar que el solicitante sigue viendo «En revisión» y sin motivos.
3. Al decidir el último paso de B: «Mis trámites» muestra «Con observaciones»; el Resumen muestra el motivo y enlace solo en el paso rechazado; un paso aceptado responde 403. El correo queda en el log.
4. Corregir el paso, volver al Resumen, pulsar «Reenviar correcciones».
5. Como revisor: el paso aparece primero como corregido, con el motivo anterior; aceptarlo deja el nivel aprobado.
6. Repetir rechazando «Responsable legal»: tras cambiar el nombre del titular, el nivel pide recargar el Formato de solicitud antes del reenvío.
