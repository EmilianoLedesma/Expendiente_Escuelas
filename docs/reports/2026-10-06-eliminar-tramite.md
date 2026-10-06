# 2026-10-06 — Eliminar trámite

## Qué se construyó

El dueño de un trámite (el solicitante dueño de la escuela) puede borrarlo de forma definitiva desde "Mis trámites" o desde el Resumen del trámite, siempre que **todos** sus niveles sigan en el estado `en_captura`.

- **Caso de uso** `app/Application/Tramite/EliminarTramite.php` — `ejecutar(int $escuelaId): void`. Dentro de `DB::transaction`: bloquea la escuela (`lockForUpdate()->findOrFail`) y sus `escuela_niveles`, lanza `PrecondicionIncumplida` si algún nivel no está en captura, reúne las rutas de archivos **antes** de borrar (`documentos_escuela`, `documentos_escuela_nivel`, `evaluaciones_validacion`) y ejecuta `$escuela->delete()`; el `ON DELETE CASCADE` de la BD se lleva todo lo demás. El borrado de archivos y la línea `Log::info('Trámite eliminado', escuela_id, solicitante_id, eliminado_en)` van en `DB::afterCommit` (mismo patrón que `RegistrarDocumento`); cada borrado de archivo va envuelto en `rescue(..., report: true)`, así que un fallo de disco se reporta y nunca revierte la acción. Los documentos usan `AlmacenDocumentos::eliminar`; los reportes, `ReporteValidacionPdf::eliminar` (ambos inyectados por constructor).
- **Regla compartida** `EliminarTramite::todosEnCaptura(Collection $estadoIds)`: la usan el caso de uso y `ResumenTramite` para exponer `puedeEliminar`, así la regla vive en un solo lugar. Un trámite sin niveles es eliminable.
- **Política** `EscuelaPolicy::delete` — solo dueño, delega en `VerificarPropietarioEscuela` igual que `update`. Habilidad separada a propósito: abrir `view` a SEDEQ nunca otorga borrar (documentado en el docblock).
- **Ruta** `DELETE /tramite/{escuela}` (`tramite.eliminar`), middleware `can:delete,escuela`, controlador `app/Http/Controllers/Tramite/EliminarTramiteController.php` que solo llama al caso de uso. Éxito → `tramite.index` con flash `status` "Trámite eliminado."; precondición incumplida → `tramite.index` con flash `error`. Un segundo envío (escuela ya inexistente) se resuelve con `->missing()` de la ruta: redirige a Mis trámites con "El trámite ya no existe." en vez de un 404.
- **DTO** `ResumenTramiteDTO::$puedeEliminar` (último parámetro, por omisión `false` para no romper los constructores existentes en pruebas).
- **UI** componente anónimo `resources/views/components/tramite/eliminar.blade.php`, usado en `tramite/index.blade.php` (columna de acción) y `tramite/resumen.blade.php` (al final). Confirmación en dos pasos **sin JS**: un `<details>` cuyo `<summary>` (estilo botón, borde rojo, mínimo 44 px, no parece enlace) abre un panel con "Esta acción no se puede deshacer." y "Se borrará el trámite, sus datos y archivos. El domicilio del plantel no se elimina."; solo el botón "Sí, eliminar trámite" envía el formulario `DELETE` con `@csrf`. Teclado nativo (Enter/Espacio abre, Tab llega al botón). El nombre accesible incluye el Nº del trámite (texto `sr-only`). Se agregó el icono `trash` a `x-ui.icon` y los avisos flash (`x-ui.alert`) a Mis trámites.

## Evidencia

- TDD: las pruebas se escribieron primero y fallaron por la razón correcta (`Target class [App\Application\Tramite\EliminarTramite] does not exist`, `Route [tramite.eliminar] not defined`, `dueño: delete — Failed asserting that false is true`). Un primer intento falló por el fixture (`historial_estados_expediente.usuario_sedeq_id` es NOT NULL), se corrigió el fixture y se volvió a ver rojo por la razón correcta antes de implementar.
- Pruebas nuevas: `tests/Feature/Tramite/EliminarTramiteTest.php` (17) y una en `tests/Feature/Policies/EscuelaPolicyTest.php`. Cubren: cascada completa (escuelas, ternas, responsable, persona física, gestor, niveles, aulas, mobiliario, personal, matrícula, documentos de escuela y de nivel, pasos, historial, evaluaciones); plantel y `documentos_plantel` (filas y archivos) intactos; archivos de escuela/nivel/reporte borrados del disco falso tras el commit; reversión externa conserva filas y archivos y ningún archivo se borra antes del commit; nivel fuera de captura y caso mixto lanzan `PrecondicionIncumplida` sin borrar; archivo ya ausente no truena; otro trámite en el mismo plantel intacto; ruta: dueño redirige con aviso, segundo borrado redirige, otro solicitante 403, usuario sin solicitante 403, invitado al login, fuera de captura redirige con error; UI: botón + confirmación en ambas páginas solo cuando es eliminable; aviso visible tras borrar; política `delete` solo dueño y `view`/`update` sin cambios.
- Suite completa: **1015 pruebas, 1015 pasan, 2832 aserciones** (línea base 997 + 18 nuevas).
- PHPStan (nivel 5 + reglas PHPat): **0 errores**. Pint: **passed**.

## Decisiones

- Borrado físico (no lógico): todas las FK desde `escuelas`/`escuela_niveles` y sus hijas son `ON DELETE CASCADE` (verificado en las migraciones `2026_01_01_*`, `2026_09_07_000001`, `2026_09_30_*`); `historial_estados_expediente.usuario_sedeq_id` apunta a `users` y no se ve afectado.
- El plantel y sus documentos nunca se tocan; el plantel puede quedar huérfano.
- Borrar el trámite borra también sus evaluaciones y PDFs de validación (decisión del dueño en el diseño aprobado). ADR-007 no decía "se conservan todos los reportes": dejó la retención como pendiente. Esta decisión resuelve una parte; la política general de limpieza sigue abierta (anotado en la sección "Pendiente" de ADR-007).
- La línea de log se emite en `afterCommit`, no antes: si la transacción se revierte no queda un registro falso de borrado.
- Confirmación con `<details>` nativo en lugar de un modal con JS: accesible por teclado sin código extra.

## Ronda de correcciones (revisión Opus: APROBADO con 8 hallazgos menores)

1. Rutas `archivo_path` NULL (columna anulable en `documentos_escuela` y `documentos_escuela_nivel`): se filtran con `whereNotNull`. Antes provocaban un `TypeError` que `rescue` atrapaba y reportaba. Prueba nueva `test_un_documento_sin_archivo_no_reporta_errores` (vista en rojo: "The following exceptions were reported: TypeError, TypeError").
2. Las consultas de rutas de documentos usan `lockForUpdate()`, para que un `RegistrarDocumento` concurrente no deje un archivo huérfano. No tiene prueba automatizada (requiere dos conexiones concurrentes).
3. Redacción sobre ADR-007 corregida en el docblock de `EliminarTramite`, en este reporte y en el docblock de `ReporteValidacionPdf`. Se agregó una línea en la sección "Pendiente" de ADR-007.
4. La prueba de archivo ausente ahora verifica que los demás archivos sí se borran y que no se reporta ninguna excepción (`Exceptions::fake()`).
5. La prueba de otro trámite en el mismo plantel ahora le da a la escuela ajena un nivel y un documento con archivo; ambos siguen existiendo después.
6. La prueba de doble envío verifica el flash `status` = "El trámite ya no existe.".
7. `EliminarTramite::idEnCaptura()` se separa de `todosEnCaptura(Collection, ?int)`. `ResumenTramite` memoriza el id por instancia, así que Mis trámites hace una sola consulta a `estados_expediente` sin importar cuántos trámites haya. Prueba nueva `test_mis_tramites_busca_el_estado_en_captura_una_sola_vez` (vista en rojo: "3 is identical to 1").
8. Con la confirmación abierta, el `<summary>` muestra "Cancelar" (icono x) en lugar de "Eliminar trámite", mediante `group-open:`. Se conservan el objetivo de 44 px y el comportamiento nativo de teclado. La prueba de UI ahora lo verifica (vista en rojo antes del cambio).

Resultado tras la ronda: suite completa **1017 pruebas, 1017 pasan, 2861 aserciones**; PHPStan **0 errores**; Pint **passed**.

## Deliberadamente no hecho

- Sin tabla de auditoría (solo `Log::info`).
- Sin limpieza de planteles huérfanos.
- No se recompilaron los assets (`npm run build`): las clases nuevas `bg-error-ink`, `hover:bg-error-soft` y `list-none`/marcador de `<details>` solo aparecen tras el build normal.
- No se abrió `delete` a SEDEQ ni se agregó al panel Filament.
- No se probó en navegador real (sin permiso para usar herramientas externas).
