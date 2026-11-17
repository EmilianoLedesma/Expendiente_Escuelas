# Proyecto educativo completable y revisable por un rol nuevo (evaluador del proyecto educativo)

Fecha: 2026-11-17. Rama: `master` (trabajo directo, sin commit ni push). Solo media superior. Plan: `docs/superpowers/plans/2026-11-17-proyecto-educativo-revision.md` (local, ignorado por git), 7 tareas, todas hechas.

## Decisiones del dueño

- **Completar:** botón explícito "Confirmar proyecto educativo", no automático.
- **Quién revisa:** el revisor y el supervisor existentes no revisan el proyecto educativo; lo hace un rol nuevo, `evaluador_proyecto`, paso a paso como el flujo normal del revisor. Las dos pistas corren en paralelo y un nivel se cierra solo cuando ambas terminan.
- **Granularidad:** por sección, 20 decisiones, con motivo obligatorio al rechazar.
- **Ruteo:** cola automática, sin asignación ni tope (una sola persona real lo atiende).
- **Visibilidad:** el supervisor lee en solo lectura; el revisor no lo ve.
- **Rondas:** una sola devolución.
- **Cuenta:** Gabriela Ruiz Espinoza, `gruiz@queretaro.gob.mx`, rol `evaluador_proyecto` (el correo llega al sandbox de Mailtrap).

## Qué se construyó

**T1, confirmar.** `ConfirmarProyectoEducativo` exige las 20 secciones completas (si no, `PrecondicionIncumplida` con las que faltan) y marca el paso `proyecto_educativo`. El índice muestra el botón, la etiqueta "Confirmado" y, en secciones rechazadas, "Con observaciones" con el motivo. Desviación del diseño: no se "deshace" la confirmación al editar, porque una sección guardada no puede volverse incompleta. `EstadoSeccionesProyecto` dejó de ser `final` para poder simularla en pruebas.

**T2, rol.** `RolesSedeq::EVALUADOR_PROYECTO` (en `TODOS` y `esSedeqOperativo`), `RolesSeeder`, `RutaInicio` (a `sedeq.proyecto-cola`), `CrearCuentaSedeq` y el formulario de usuarios de Filament. Ruta `/sedeq/proyecto` con `rol:evaluador_proyecto`.

**T3, decisiones por sección.** Migración `2026_11_18_000000` (ejecutada en la base de desarrollo por la regla permanente): amplía el CHECK de `paso_clave` en `decisiones_revision` y su historial con `pe_01` a `pe_20` (claves cortas, caben en `VARCHAR(30)`). `CierreRevisionNivel` (nuevo) concentra la regla de cierre que estaba en `DecidirPaso`: el nivel queda `aprobado` solo si los pasos del revisor y las 20 decisiones `pe_*` están decididos y ninguno rechazado; si todo está decidido con rechazos, `con_observaciones`. `DecidirSeccionProyecto` exige el rol, nivel `en_revision` y media superior, y motivo al rechazar. El avance del revisor cuenta solo sus pasos.

**T4, devolución.** Cualquier `pe_*` rechazado se junta en un solo paso reabierto `proyecto_educativo` por nivel (`PasosReabiertos`); `ObservacionesDeEscuela` une los motivos y expone `seccionesRechazadas`. La sección del solicitante muestra "Observación del evaluador". `ReenviarCorrecciones` borra solo las decisiones rechazadas, así que el evaluador vuelve a decidir solo esas.

**T5, acceso.** `AccesoSedeq::puedeVerProyecto` y `nivelesConProyecto`, aparte de `puedeVerNivel`: evaluador y supervisor leen niveles de media superior fuera de captura; el revisor nunca.

**T6a, pantallas.** Cola `ColaProyecto` (más antiguos primero, con avance X de 20), y revisión `RevisionProyecto` (20 secciones a la izquierda, la abierta a la derecha, aceptar o rechazar con motivo; el supervisor solo lee). Casos de uso `ListarColaProyecto`, `ConsultarRevisionProyecto` y `ContenidoSeccionProyecto` (contenido genérico de solo lectura desde las clases `*Capturada`, sin rutas de disco). Ruta `/sedeq/proyecto/{escuelaNivel}` con `rol:evaluador_proyecto|supervisor`.

**T6b, PDF para revisión.** `ArchivoRevisionProyecto` (autoriza con `puedeVerProyecto`; `ruta()` y `disponibles()`) y `DescargarArchivoProyectoController`, ruta `sedeq.proyecto-archivo`. Sirve 7 PDF: hoja de ruta y planeación del PAEC, planeación de formación continua, mapa curricular, programas de estudios, planeación académica y calendario académico. Evaluador y supervisor descargan; el revisor y un solicitante reciben 403; clave desconocida o archivo no subido, 404. La pantalla recibe claves y etiquetas, nunca la ruta de disco.

**T7, cuenta.** Creada en la base de desarrollo con `CrearCuentaSedeq` (usuario id 9, rol `evaluador_proyecto`, correo verificado y contraseña aleatoria). El correo de restablecimiento se envió con `queue:work --stop-when-empty` (job `RestablecerContrasena` terminado, cola vacía, sin fallos); no se emitió un segundo restablecimiento para no invalidar el enlace.

## Evidencia

- Pruebas enfocadas en verde: `ConfirmarProyectoEducativo` 7 + Livewire 6, rol 9, `DecidirSeccionProyecto` 18, devolución 9 + 3, acceso 10, lectura de revisión 14, Livewire de revisión 12, archivos para revisión 6, descarga HTTP 4. Regresiones por tandas: Livewire/Sedeq 80, Application/Revision 175, Revision 27, Sedeq 8, `RutaInicioTest` 19. Pint limpio, PHPStan 0 errores.
- Las pruebas de T4 en Livewire se escribieron antes de la interfaz pero no se corrieron fallando antes de implementar (se avisó al dueño). Las de T6b se vieron fallar primero (clase inexistente, clave `descargas` ausente).
- Suite completa: la corre CI; no se ejecutó localmente (regla del dueño). Nada visto en navegador.

## Pendiente

- Commit, push y CI (solo cuando el dueño lo pida).
- El supervisor llega a la revisión solo por URL; falta un enlace desde su tablero.
- Las etiquetas del visor salen sin acentos (`Str::headline`).
- Niveles de media superior ya `en_revision` en desarrollo necesitarían decisiones del evaluador para cerrarse (solo datos de desarrollo).
- No se corrió una revisión por tarea con un revisor separado (CLAUDE.md la pide).
- Abiertos de antes: defecto de savepoint en las pruebas CHECK de secciones anteriores, rama "Contenido por definir" sin uso, `ResumenEliminacionNivel` no cuenta filas de texto, recorrido en navegador.

## Envío bloqueado hasta confirmar (verificado)

En media superior el trámite no se puede enviar sin confirmar el proyecto educativo. El paso `proyecto_educativo` es una fila obligatoria del resumen del trámite; la validación final no corre si alguna fila no está completada (`ResumenTramite::completo`), y solo "Confirmar proyecto educativo" marca ese paso. No hubo que cambiar código de producción; se agregó `EnvioConProyectoEducativoTest` (2 pruebas): sin confirmar, el trámite no está completo y `EnviarTramite` lo rechaza; confirmado, queda completo.

## Correos

No se escribieron plantillas nuevas: se reutilizan `ObservacionesEnTramite` (ronda cerrada con rechazos, motivos del revisor y del evaluador juntos) y `RevisionSinObservaciones` (aprobación total, cuando todos los niveles están aprobados), además de los avisos existentes al enviar, reenviar y cerrar. Todos van al dueño del trámite (y a los responsables por nivel en el de observaciones). No hay correo por sección ni aviso a Gabriela cuando entra un proyecto a su cola. Sin prueba de que el correo salga tras una decisión del evaluador (las pruebas de correo existentes cubren solo al revisor).