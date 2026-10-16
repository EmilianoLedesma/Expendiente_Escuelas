# Reporte: HUD SEDEQ, proyecto 1 (roles y asignación)

**Fecha:** 2026-10-16
**Rama / worktree:** `hud-sedeq-1` (desde `master` cf42b93), `C:\Users\Emili\Documents\sedeq-incorporacion-hud`. Sin commit: el árbol de trabajo está sin confirmar (las tareas completas están en el índice).
**Spec:** `docs/superpowers/specs/2026-10-16-hud-sedeq-roles-asignacion-design.md`
**Plan:** `docs/superpowers/plans/2026-10-16-hud-sedeq-roles-asignacion.md`
**ADR:** `docs/decisions/ADR-016-roles-supervisor-revisor.md`
**Estado:** implementado y verificado con pruebas automáticas. **Nada se vio en un navegador** (Tarea 8, paso 2, no se hizo).

## 1. Qué se construyó

Es el primero de cuatro proyectos (1 roles y asignación, 2 revisión del revisor, 3 devolución por sección, 4 resolución con QR). Entrega a las personas y el reparto del trabajo, no la revisión en sí. Revierte la nota de alcance del 2026-09-11 según la cual la revisión humana de SEDEQ quedaba fuera del MVP.

- **Roles `supervisor` y `revisor`** (Spatie), aparte del rol `sedeq` (administrador del sistema, sin cambios). `RolesSedeq::TODOS` agrupa los tres; `RolesSeeder` los crea. Middleware `rol` (`ExigeRol`, alias en `bootstrap/app.php`) registrado también como middleware persistente de Livewire.
- **Comando `sedeq:crear-supervisor`**: crea al primer supervisor (nunca por registro). Envía el enlace con el broker de contraseñas (`RestablecerContrasena`, en cola) después de confirmar la transacción. Un correo ya existente devuelve código de salida 1 sin cambios.
- **Redirección por rol** (`RutaInicio`): un solo resolvedor usado por el login, `verify-email`, `confirm-password`, `dashboard` y `/tramite` (`MisTramitesController`). Supervisor y revisor ya no caen en "Mis trámites" vacío. `cuenta/menu.blade.php` tiene rama de HUD (supervisor: "Mi perfil" y "Revisores"; revisor: "Mi perfil" y "Mis Revisiones"; tras el lote de correcciones del recorrido, ver `2026-10-16-hud-fixbatch-recorrido.md`).
- **Tablas nuevas** (5 migraciones `2026_10_16_0000{00,100,200,300,400}_*`, DDL crudo con `DB::unprepared`): `asignaciones_revision` (única por `escuela_nivel`), `prioridades_revision` (alta/media/baja con CHECK), `progreso_revision` (secciones revisadas, independiente de la asignación), `bitacora_asignaciones`, `revisores` (con `revocado_at`). Modelos de solo persistencia. El DDL de referencia está en `.gitignore` y no se actualizó.
- **Casos de uso** en `app/Application/Revision/`:
  - `AsignarTramite` / `asignarSeleccion` (una transacción, todo o nada, con bloqueo de la fila `users` del revisor y de los `escuela_niveles`), `DesasignarTramite` (solo `todosDe`; la revisión final eliminó `ejecutar`, que no tenía llamadores), `EstablecerPrioridad`.
  - `InvitarRevisor`, `RevocarRevisor`, con notificación `InvitacionRevisor` y vista de correo.
  - `ListarTablero`, `ListarColaDelRevisor` y los DTO `FilaTramite`, `BarraRevisor` (una consulta para filas, una para barras).
  - `AccesoSedeq` (decisión de lectura) y `ConsultarTramiteSedeq` (modelo de lectura propio; no reutiliza `ResumenTramite::paraEscuela`).
  - `RolesSedeq`, `RutaInicio`, `EstadoRevision`, `LimiteDeRevisorAlcanzado`.
- **Límite por revisor**: un solo valor en config, `sedeq.revision.limite_por_revisor = 5`.
- **Políticas de solo lectura**: `EscuelaPolicy::view` y `EscuelaNivelPolicy::view` se abren a SEDEQ con la regla acotada por nivel (supervisor: todo nivel que no esté `en_captura`; revisor: solo niveles asignados y no `en_captura`). `update`, `updateInmueble`, `gestionarResponsables`, `delete` y `verResumen` no cambian. Nueva `EscuelaPolicy::verReporte` (solo dueño).
- **`EliminarCuenta` y `Perfil`** rechazan `sedeq`, `supervisor` y `revisor` con una sola lista (`RolesSedeq::TODOS`).
- **Pantallas** (Livewire, solo presentación):
  - `/sedeq` `TableroSupervisor`: tabla (casilla, N.º, plantel, prioridad con select por fila, espera, revisor), resumen de carga por revisor ("N/L", rojo con el texto " · llena"), selector "Asignar a…" y botón "Asignar seleccionados", aviso emergente y ventana modal de sobrecarga con "Asignar de todos modos" / "Cancelar". Invitar y Revocar (con `<dialog>`) pasaron a la página `/sedeq/revisores` tras el recorrido del propietario (el texto original de este reporte las ubicaba en el tablero). Arrastrar y soltar no se construyó (era opcional).
  - `/sedeq/cola` `ColaRevisor`: barra "N de L asignados", lista ordenada (prioridad, luego más antiguo enviado), panel derecho con resumen, enlace "Ver trámite (solo lectura)" y un marcador vacío "Hallazgos del motor" para el proyecto 2.
  - `/sedeq/tramite/{escuela}` `TramiteLectura`: vista de solo lectura sin formularios ni enlaces de edición; solo enlaces de descarga. `#[Locked]` en `$escuelaId`.

## 2. Evidencia

Todas las corridas con `$env:DB_DATABASE='sedeq_incorporacion_testing_ui'` (base de pruebas aparte; nunca la de desarrollo).

| Tarea | Suite completa | Notas |
|---|---|---|
| 1. Roles, comando, redirecciones | 1382/1382 | RED: clase `RolesSedeq` inexistente, comando inexistente, rutas 404. GREEN focalizado 71 pruebas, 256 aserciones |
| 2. Bloqueo de eliminación de cuenta | (no registrada en el reporte) | RED: 2 fallos en `EliminarCuentaTest` (9 pruebas) y 2 en `PerfilTest` (19). GREEN 9/9 y 19/19 |
| 3. Esquema y asignación | 1415, luego 1417 tras la ronda de corrección | GREEN focalizado 28, luego 30 pruebas (80 aserciones) |
| 4. Invitar y revocar | 1426 | 35/35 en `tests/Feature/Application/Revision` tras la corrección |
| 5. Modelos de lectura | 1434 | 7 pruebas nuevas |
| 6. Acceso de solo lectura | 1447, luego 1450 tras la corrección | 13 pruebas nuevas; tras la corrección 85/85 en el conjunto relacionado |
| 7. Pantallas HUD | 1469, luego **1475** tras la corrección | `tests/Feature/Livewire/Sedeq` 25/25 y luego 31/31 (145 aserciones) |

**Cifras finales:** suite completa **1475/1475** (5044 aserciones), PHPStan **0 errores** (con las reglas PHPat), Pint limpio, `npm run build` correcto.

### RED/GREEN destacados
- **Tarea 3**: el CHECK de prioridad `'urgente'` nunca llega al CHECK porque `VARCHAR(5)` falla antes (SQLSTATE 22001); la prueba afirma ambos casos (`urgente` por longitud, `nula` por `prioridades_revision_prioridad_check`). La prueba del CHECK pasaba en vacío en el RED (la tabla faltante también lanza `QueryException`); se corrigió.
- **Tarea 3, ronda 1**: RED con 16 pruebas, 14 pasaron y 2 fallaron ("Failed asserting that null is identical to 81" porque la sobrecarga desasignaba un nivel de la propia selección; y "Se esperaba LimiteDeRevisorAlcanzado").
- **Tarea 6, ronda 1**: RED con 34 pruebas, 31 pasaron y 3 fallaron (el revisor asignado al nivel A descargó el reporte, 200 en vez de 403; propiedad bloqueada sin excepción; matriz de `AutorizacionEscrituraTest`).
- **Tarea 7**: RED con 25 pruebas, 8 pasaron y 17 fallaron por las razones correctas; ronda 1: RED con 31 pruebas, 26 pasaron y 5 fallaron (errores de campo obsoletos, `sobrecarga` sin limpiar, evento de foco no disparado).

### Pruebas de mutación
- **Tarea 3**: se quitó `lockForUpdate()` de la fila `users` del revisor; la prueba de orden de bloqueo falló ("No se bloqueó la fila users del revisor."); restaurado.
- **Tarea 4 (ronda de corrección)**: se quitó la guarda `|| getRoleNames()->isNotEmpty() || solicitante()->exists()`; RED con "Se esperaba DatosInvalidos." (caso revocado + `sedeq` aceptado); restaurado byte a byte (`cmp` idéntico). Salvedad: ambas cuentas están en una sola prueba y se detiene en la primera, así que el caso solicitante no se vio fallar por separado.

### Pruebas que pasaron en el RED (solo cobertura o guardas de regresión)
- Tarea 6: la prueba de 403 en rutas de escritura ya pasaba (guarda de regresión). La fila `verReporte` de la matriz pasó en RED porque Gate devuelve falso para una habilidad no definida.
- Tarea 7: las dos pruebas M7 (403 al montar y `verified`) pasaron en RED.
- Tarea 4: la prueba nueva de la ronda de corrección se aplicó junto con el arreglo, así que su RED no se observó por separado (la mutación posterior sí lo muestra).

### Revisiones y rondas de corrección
Cada tarea tuvo revisión separada. Tareas 1, 2 y 5 salieron limpias. Rondas de corrección:
- **Tarea 3 (1 ronda, 3 atendidos)**: Importante, la sobrecarga podía desasignar un nivel de la propia selección (se añadió `whereNotIn` sobre la selección y el rechazo compara la selección completa); menores: prueba de bloqueo más estricta (bindings del `users ... for update`) y docblocks que afirmaban un orden de bloqueo que no se cumple.
- **Tarea 4 (1 ronda, 3 atendidos)**: Importante, la reinvitación de un revisor revocado podía apilar el rol revisor sobre una cuenta con otro rol o fila de solicitante; menores: endurecer pruebas.
- **Tarea 6 (1 ronda, 5 atendidos)**: dos Importantes por el reporte de validación (ver Ruling de la Tarea 6) y menores: prueba real de capacidad de escritura, `#[Locked]` en `TramiteLectura::$escuelaId`, prueba de persona moral.
- **Tarea 7 (1 ronda, 6 atendidos)**: Importantes, errores de campo obsoletos; el confirmar de sobrecarga entraba en bucle cuando la selección superaba el límite; el select de prioridad perdía el foco del teclado. Menores: limpiar `sobrecarga` al fallar el confirmar, pruebas de 403 al montar y de cola sin verificar, regiones vivas anidadas.

## 3. Decisiones y rulings

Se copian los `Ruling:` del libro mayor SDD, en español, con lo que cuesta si están mal.

1. **Ruling: sin commits en ningún lado.** `CLAUDE.md` dice que nunca se hace commit si no se pide, y eso prevalece sobre el "el implementador hace commit" de la habilidad. Los diffs de cada tarea salen del árbol de trabajo (`git add -N` + `git diff`); las tareas completas se dejan en el índice (`git add -A`). *Si está mal:* el dueño debe confirmar la rama él mismo (de todos modos lo haría).
2. **Ruling: las pruebas corren con `DB_DATABASE='sedeq_incorporacion_testing_ui'`** (base de pruebas aparte, nunca la de desarrollo), por la regla del dueño sobre bases de ramas. *(El libro mayor no registra costo para este ruling.)*
3. **Ruling: Tarea 3, `asignarSeleccion` con una selección mayor que el límite lanza `LimiteDeRevisorAlcanzado` aun con la sobrecarga confirmada.** La spec no lo dice; una sobrecarga desasigna trabajo existente, nunca rechaza ni crece más allá del tope solo por trabajo nuevo. *Si está mal:* el supervisor tendrá que dividir las selecciones grandes. (En la ronda de corrección la comparación pasó a ser contra la selección completa, no solo los niveles nuevos.)
4. **Ruling: Tarea 6, el reporte de validación (`tramite.validacion.reporte`) sigue siendo solo del dueño** (nueva `EscuelaPolicy::verReporte`) en el proyecto 1; no se abre a SEDEQ. Filtra datos de niveles hermanos a un revisor parcialmente asignado y expone instantáneas previas al envío (ids secuenciales). *Si está mal:* SEDEQ no podrá descargar el reporte del motor hasta que el proyecto 2 lo abra acotado por nivel y "solo evaluación enviada".

### Otras decisiones tomadas por el constructor (con soporte en los reportes)
- Asignación por `escuela_nivel`; "revisor activo" = tiene el rol `revisor`. `DesasignarTramite` no exige `en_revision` ni que el revisor conserve el rol (la revocación puede quitarlo antes).
- El límite cuenta solo niveles `en_revision`; las asignaciones obsoletas de niveles que salieron de `en_revision` ni cuentan ni se desasignan.
- Orden de bloqueo: fila `users` del revisor, luego `escuela_niveles` por id, luego `escuelas`. Poner `users` antes de `escuela_niveles` es una excepción deliberada al orden del proyecto (`InvitarResponsableNivel` y `RevocarResponsableNivel` bloquean niveles y luego usuarios); se documentó en el docblock y es seguro en la práctica porque las cuentas SEDEQ y de solicitante son separadas; si Postgres detectara un deadlock aborta una transacción sin corromper datos.
- `ColaRevisor::seleccionar` sobre un trámite fuera de la cola se ignora con un toast de error ("Ese trámite ya no está en tu cola") en vez de 403; el revisor no ve el otro trámite de ninguna forma. Probado.
- Nombre del plantel: no hay columna de nombre en `planteles`; se usa `COALESCE(escuelas.nombre_aprobado, primer ternas_nombres.nombre_propuesto, 'Plantel sin nombre')` (mismo respaldo que `ResumenTramite`).
- Tiempo de espera = días de calendario desde la última fila `en_revision` en `historial_estados_expediente`; `usuario_sedeq_id` de esa fila no se lee como actor SEDEQ (hoy guarda el `users.id` del solicitante).
- El foco del select de prioridad: se mantuvo la actualización en vivo y se restaura el foco con un evento `prioridad-cambiada`. Efecto secundario: cada flecha envía una petición y muestra un toast "Prioridad actualizada". Alternativa si el dueño lo prefiere: botón "Guardar" por fila.
- Aviso de sobrecarga: un solo anunciador (`role="status" aria-live="polite"` siempre presente; la alerta interior con `role="none"`).
- Si la selección supera el límite, no se ofrece confirmar: error de campo "Seleccionaste N; el tope por revisor es L."

## 4. Lo que quedó sin hacer a propósito

- **Proyecto 2**: revisión en sí (panel de hallazgos, aceptar / rechazar / devolver, historial de decisiones, marcar secciones revisadas). Hasta entonces el progreso es siempre 0 y desempatan la prioridad y el envío más antiguo. Abrir el reporte de validación a SEDEQ acotado por nivel.
- **Proyecto 3**: devolución por sección, instantáneas y diferencias.
- **Proyecto 4**: resolución en PDF con QR y página pública de verificación.
- **Creación de supervisores desde `/admin`**: futura; hoy solo por `sedeq:crear-supervisor`.
- **Columna de folio** en el tablero: la columna "N.º" muestra el id de `escuela_nivel` porque `FilaTramite` no trae folio.
- **Nombrar en el toast qué trámite se desasignó** en la sobrecarga: `AsignarTramite` no devuelve nada; el toast solo dice cuántos se asignaron.
- **Reporte de validación para SEDEQ**: no se abre en este proyecto (ver Ruling 4).
- Arrastrar y soltar para asignar (opcional en la spec).
- Notificaciones más allá del correo de invitación.
- **Tarea 8, paso 2** (recorrido en navegador sobre `:8001`): no se hizo; requiere autorización del dueño.

## 4b. Limitaciones conocidas de la revocación

- Revocar a un revisor **no impide iniciar sesión**: la cuenta pierde el acceso al HUD (403), pero puede entrar, es enviada a `/tramite` y ve un "Mis trámites" de solicitante vacío. Una vista dedicada de "acceso revocado" queda como mejora posible.
- Una cuenta de revisor revocado **no puede eliminarse a sí misma** (el botón no se muestra y `EliminarCuenta` lo rechaza): su fila en `revisores` referencia a `users`. Se cubre con `RolesSedeq::cuentaProtegida`.

## 5. Menores diferidos (del libro mayor)

- T1: `shell/encabezado.blade.php:51` muestra el enlace "Mis trámites" a supervisor/revisor (redirige al inicio; detalle de UX).
- T1: `sedeq:crear-supervisor` deja la cuenta con contraseña aleatoria si falla el envío del correo y el reintento se rechaza (agregar nota en documentación).
- T1: la ruta `redirectIntended` no está cubierta por la prueba de login.
- T2: los proveedores de datos `rolesSedeq()` están duplicados en `EliminarCuentaTest` y `PerfilTest`; derivarlos de `RolesSedeq::TODOS`.
- T3: `DesasignarTramite::ejecutar` se eliminó en la revisión final (sin llamadores; su valor por defecto habría etiquetado mal una desasignación manual).
- T3: la fila `reasignar` de la bitácora solo guarda al revisor nuevo; se pierde el anterior.
- T3: el brief hablaba de `urgente`; la prueba usa `nula` (el `VARCHAR(5)` falla antes).
- T4: búsqueda de correo con coincidencia exacta; el índice único distingue mayúsculas, así que `Ana@X.com` y `ana@x.com` pueden coexistir (paridad con `InvitarResponsableNivel`; considerar `lower(email)` en ambos).
- T4: `RevocarRevisor` solo comprueba el rol revisor; un usuario con supervisor + revisor podría revocarse dejando fila con `revocado_at`.
- T5: `ListarTablero::filas()` es `public static` sin autorización (marcar `@internal` o mover a una clase de consulta compartida).
- T5: el texto "Plantel sin nombre" es una cadena nueva de cara al usuario; el dueño puede cambiar la redacción.
- T5: faltan pruebas para nivel sin historial (`diasEspera` 0), desempate por id con la misma fecha y exclusión de `en_captura` en la cola.
- T6: `formato-solicitud` (imprime datos personales del representante legal) y `paso3-proximos-pasos` (redirección sin salida) siguen abiertos a SEDEQ por `view`, según la spec.
- T7: `public bool $confirmarSobrecarga` permite a un supervisor llamar `asignarSeleccion(..., true)` directamente (aceptado: puede forzar y la bitácora lo registra); opcional un método de confirmar sin argumentos.
- T7: la columna "N.º" muestra el id de `escuela_nivel` (sin folio).
- T7: el toast de sobrecarga no nombra el trámite desasignado.
- T7: un cambio de prioridad fallido puede dejar el select mostrando el valor rechazado; el `<nav>` envolvente de los botones de la cola; parámetros no enteros dan 500 en lugar de toast; falta el filtro "Sin asignar (N) · Todos" del mockup.

## 6. Qué debe revisar una persona en un navegador

Nada se miró en un navegador. Pendiente de ojo humano:

- **Retorno del foco en el select de prioridad**: tras cambiar con flechas o ratón, el foco debe quedarse en el select de esa fila aunque la fila se mueva (Chrome/Edge en Windows, con el select cerrado).
- **Aviso de sobrecarga anunciado una sola vez** por un lector de pantalla (cambio solo de marcado, sin prueba).
- **Clic en "Asignar seleccionados" (Alpine)**: llama `$wire.asignarSeleccion($wire.seleccion, Number($wire.revisorDestino) || 0)`; `Livewire::test` no ejecuta JS. Si `$wire.seleccion` no refleja el estado diferido de las casillas, el clic mostrará "Selecciona al menos un trámite".
- **Diseño en 375 px**: tabla con desplazamiento horizontal sin desplazamiento horizontal de la página; dos columnas en xl y apiladas debajo.
- Además (de la Tarea 7): el botón con estado "+ Invitar revisor" (cargando, éxito, error), el diálogo de confirmación de Revocar, las barras en rojo con " · llena" en 5/5, foco visible y borde grueso del elemento seleccionado en la cola, el marcador "Hallazgos del motor", y el contraste del texto rojo (`text-error-ink`) en modo oscuro.

## 7. Nota para el proyecto 3

La devolución por sección **no debe mover el nivel de vuelta a `en_captura`**: el revisor asignado perdería el acceso (el acceso y el conteo exigen que el nivel no esté `en_captura` / esté `en_revision`). O bien se le da su propio estado. Ya hay una prueba que muestra que una asignación cuyo nivel vuelve a captura queda oculta.

- `bitacora_asignaciones` es `ON DELETE CASCADE` desde `escuela_niveles`: si el proyecto 3 devuelve niveles a `en_captura`, quitar un nivel borraría el historial de asignaciones.

## 8. Otras notas

- El reporte de validación no se abre a SEDEQ en este proyecto.
- Consumos de proyecto 2 ya previstos: `historial_estados_expediente.usuario_sedeq_id` (`NOT NULL`, sin `ON DELETE`) y la bitácora apuntan a `users`, por eso revocar conserva la fila del usuario.
- `docs/progress.md` no se tocó (un solo escritor: el controlador).
