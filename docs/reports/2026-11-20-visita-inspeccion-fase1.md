# Visita de inspección, fase 1 (2026-11-20)

Rama: `worktree-visita-inspeccion` (base `master` e94f51e). Estado: trabajo en el árbol de trabajo y en el índice, **sin commits y sin push**. Nada de esto está fusionado en `master`. `docs/progress.md` no se tocó (lo escribe el controlador al fusionar).

## Qué se pidió y por qué

Tras la revisión documental aprobada (`aprobado`), la norma y los documentos de requisitos de SEDEQ ponen una **visita de inspección** antes de cualquier resolución: se notifica la visita, el plantel debe estar equipado como el primer día y se cotejan originales, se levanta un acta y la escuela tiene 5 días hábiles para corregir las observaciones; solo después viene la resolución (fase 2). Hasta ahora un nivel aprobado se quedaba en "Revisión documental aprobada" sin nada que hacer. La fase 1 permite que el **supervisor** programe la visita, registre el resultado y acepte las correcciones, y que el **solicitante** vea la visita y responda las observaciones, de modo que nada parezca terminado sin estarlo. Marco y fuentes: `docs/reports/2026-11-19-investigacion-posaprobacion.md`. Diseño: `docs/superpowers/specs/2026-11-19-visita-inspeccion-design.md` (local, ignorado por git).

## Decisiones del dueño (2026-11-19)

- El supervisor programa **y** registra la visita; no hay rol nuevo.
- Los plazos se **muestran, no se aplican** (sin desechamiento automático).
- Enfoque A: tabla propia y estados nuevos del nivel.
- No se genera oficio ni acta oficial (aún no hay muestra real).
- Durante la ejecución ("lo que sea más barato"): se deja la visibilidad del responsable por nivel tal como quedó (ve el detalle de la visita, observaciones, notas y nombres de archivo, sin descargar ni responder); el "SEDEQ" del chrome compartido (título de pestaña, alt del logo) se deja como está.
- Sin campo de lugar: la visita es al domicilio del plantel capturado; se muestra el domicilio actual del plantel, no una copia congelada (la columna `lugar` se eliminó de la migración aún no fusionada).
- Compactar tras cada tarea: el dueño lo dispensó por ahora. Hubo órdenes de parar y reanudar por falta de créditos; consta en el libro de la ejecución.
- Registro de visitas realizadas: tras hacerse una visita no había dónde consultar el historial de todas las escuelas; se pidió una página rápida solo para el supervisor (pantalla `/sedeq/visitas`, enlace "Visitas" junto a "Expedientes"). No se añadió historial para el solicitante ni exportación.

## Qué se construyó

| Pieza | Archivo principal |
|---|---|
| Estados `visita_programada`, `en_subsanacion`, `visita_aprobada` (orden 7-9) | `app-laravel/database/seeders/CatalogoMinimoSeeder.php` |
| Tablas `visitas_inspeccion`, `observaciones_visita`, `respuestas_visita`, `respuestas_visita_archivos` (DDL crudo, CHECK con nombre, índice parcial: una sola visita `programada` por nivel) | `app-laravel/database/migrations/2026_11_20_0000*` a `0003*` |
| Modelos | `app/Models/VisitaInspeccion.php`, `ObservacionVisita`, `RespuestaVisita`, `RespuestaVisitaArchivo`; `EscuelaNivel::visitas()` |
| Estados posteriores a la aprobación (conjunto único) | `app/Application/Tramite/EstadosPosteriores.php` |
| Días hábiles (sábado y domingo; sin festivos) | `app/Domain/Calendario/DiasHabiles.php` |
| Casos de uso | `app/Application/Visita/`: `ProgramarVisita`, `ActualizarVisita`, `RegistrarActaVisita`, `AdjuntarActaVisita`, `ResponderObservaciones`, `AceptarSubsanacion`, `ConsultarVisita`, más `DatosVisita`, `ArchivoActaVisita` y los eventos `VisitaProgramada`, `ActaDeVisitaRegistrada` |
| Permisos `verVisita` (supervisor, dueño, responsable de ese nivel) y `responderVisita` (solo dueño) | `app/Policies/EscuelaNivelPolicy.php` |
| Correos en cola, solo al dueño, redacción neutra ("la autoridad educativa") | `app/Notifications/VisitaProgramadaAviso.php`, `ResultadoDeVisita.php`; `app/Listeners/NotificarVisita.php`; vistas `resources/views/correo/` |
| Pantalla del supervisor `/sedeq/visita/{escuelaNivel}` | `app/Livewire/Sedeq/VisitaNivel.php` |
| Pantalla del solicitante `/tramite/visita/{escuelaNivel}` | `app/Livewire/Tramite/VisitaSolicitante.php` |
| Registro de visitas realizadas, solo supervisor (`/sedeq/visitas`): tarjetas de todas las visitas registradas, más recientes primero, con filtros (escuela o nivel, resultado, rango de fechas), enlaces a la pantalla de la visita y descarga del acta; solo lectura, sin migración, tope de 100 filas con el total | `app/Application/Visita/ListarVisitasRealizadas.php`, `app/Livewire/Sedeq/VisitasRealizadas.php` |
| Descargas protegidas de acta y archivos de respuesta (solo supervisor y dueño) | `app/Http/Controllers/DescargarArchivoVisita.php` |
| Expediente: tarjetas con enlaces, filtro con los estados nuevos, insignia "Respuesta recibida" | `app/Application/Revision/ListarExpedientes.php`, `DTO/FilaExpediente.php` |
| Hub y "Mis trámites": texto por nivel y enlace "Ver visita" / "Responder observaciones" | `app/Application/Tramite/ResumenTramite.php`, `DTO/NivelDelTramite.php` |
| Cierre de la revisión cuenta estados posteriores | `app/Application/Revision/CierreRevisionNivel.php` |
| Purga del trámite borra acta y archivos de respuesta tras el commit (esta tarea) | `app/Application/Archivo/PurgarTramite.php` |

Archivos en el disco `documentos` bajo `visitas/{escuela}/...` (acta: `visitas_inspeccion.acta_path`; respuestas: `respuestas_visita_archivos.archivo_path`).

Flujo: `ProgramarVisita` (nivel `aprobado` o `en_subsanacion`) lo pasa a `visita_programada`; `RegistrarActaVisita` lo pasa a `visita_aprobada` (cumple) o `en_subsanacion` (con observaciones, plazo = fecha de realización + 5 días hábiles, aproximado); `ResponderObservaciones` (solo el dueño) agrega una respuesta sin cambiar el estado; `AceptarSubsanacion` lo pasa a `visita_aprobada`, o el supervisor programa una segunda visita. Toda transición va en una transacción, bloquea `escuela_niveles` y luego `escuelas`, y escribe `historial_estados_expediente`.

Decisiones propias del constructor (marcadas para el dueño): acta PDF opcional al registrar (puede adjuntarse después); varias respuestas del solicitante por visita; segunda visita permitida desde `en_subsanacion`; solo fines de semana como días inhábiles; inspector como texto libre.

## Rulings tomados durante la ejecución

- **Sin commits** de implementadores ni del controlador: la regla del CLAUDE.md del proyecto prevalece sobre el paso de commit de la habilidad. El diff por tarea se obtuvo con `git diff -U10` y el controlador hacía `git add -A` (solo índice) al cerrar cada tarea.
- **`PrecondicionIncumplida('rol')` en vez de `AuthorizationException`** para los casos de uso del supervisor (`ProgramarVisita`, `ActualizarVisita`, `RegistrarActaVisita`, `AceptarSubsanacion`), vía `RolesSedeq::exigir`: es el patrón vigente y la UI mapea esa etapa a 403. Para el dueño (`ResponderObservaciones`) se usa `AuthorizationException`, igual que `ActualizarContactoPlantel`.
- **Sin `Event::listen` en `AppServiceProvider`**: Laravel descubre solo los métodos `handle*` de `app/Listeners` (así funciona `NotificarAvanceDeTramite`); registrarlos de nuevo duplicaría los correos. El plan decía registrar; no se hizo.
- Las acciones de aplicación pueden recibir `UploadedFile` (PHPat solo prohíbe Livewire, Http y vistas).
- Las descargas se limitan a supervisor y dueño, **no** a `verVisita` (que admite también al responsable por nivel).
- Las acciones de visita no deben envolverse en `DB::transaction` desde Livewire: el borrado de archivos huérfanos depende de la transacción interna.
- Las migraciones nunca se corrieron en la base de desarrollo; solo en la de pruebas.
- Hotfix de la fase 0: el commit **4187ce7 en `master`** corrige el fallo de CI de `ArchivarTramiteTest` (dos consultas a `estados_expediente` por listado de borradores; guarda `$hayEnviados` en `ResumenTramite`). CI run 44565474132 en verde. Esta rama contiene el mismo hunk, pero **no fusiona sin conflicto**: la rama y `master` (4187ce7) chocan en dos hunks de `ResumenTramite.php`. Resolución: conservar el lado de la rama en ambos hunks y volver a correr `ArchivarTramiteTest` y `ResumenTramiteAprobadoTest`.
- Decisión sobre purga (esta tarea): se espeja el mecanismo existente (rutas reunidas antes del borrado, archivos borrados en `DB::afterCommit` con `rescue(..., report: true)`). Archivar y restaurar no necesitan cambio; hay prueba que lo demuestra.
- `ConsultarReportes` ya agrupa por todos los estados de `estados_expediente` ordenados por `orden`; los tres nuevos salen como filas propias sin tocar el código. Se añadió una prueba que lo documenta.

## Evidencia

Por tarea (RED visto antes de implementar, conteos de los reportes de tarea):

| Tarea | Pruebas nuevas / resultado |
|---|---|
| T1 esquema, modelos, estados | `EsquemaVisitaTest` 9 (RED 5 fallas); `tests/Feature/Visita` + `Database` 168 verdes; `CatalogoMinimoSeederTest` ajustado (6 a 9 estados) |
| T2 `DiasHabiles`, `EstadosPosteriores`, costuras | 16 pruebas (RED 7 fallas); 28 enfocadas verdes en la revisión |
| T3 `ProgramarVisita`/`ActualizarVisita` | 14 (RED 14 fallas), 14 verdes |
| T4 `RegistrarActaVisita` + acta | 13 (RED 12 fallas); tras la corrección 27 verdes en `Application/Visita` |
| T5 `ResponderObservaciones`/`AceptarSubsanacion` + políticas | 19 (RED 18 fallas); tras la corrección 94 verdes en `Application/Visita` + `Policies` |
| T6 correos | `NotificarVisitaTest`; Notifications + Visita 63 verdes |
| T7 pantalla del supervisor | 36 (RED 25 fallas); Livewire/Sedeq 116, Visita 19, Application/Visita 53, Application/Revision 190 tras la corrección |
| T8 pantalla del solicitante | `VisitaSolicitanteTest` 16 tras la corrección (RED 15 errores); Tramite 89, ArchivarTramiteTest 19, Notifications 17, View 82, VisitaNivel 19 |
| T9 purga y reportes | RED: 2 de 3 pruebas nuevas de purga fallaron ("Found unexpected file ... visitas/N/acta-x.pdf", los archivos seguían); GREEN tras implementar |

Tarea 9, corridas finales: `tests/Feature/Archivo` 99 verdes (414 aserciones; incluye 3 pruebas nuevas: purga con acta + respuesta de 2 archivos y archivos ajenos intactos, purga con un archivo ya ausente, archivar+restaurar conserva filas y archivos); `tests/Feature/Application/Reportes/ConsultarReportesTest.php` 9 verdes (1 nueva: un nivel en `visita_programada` cuenta bajo "Visita programada" sin alterar los demás conteos); `tests/Feature/Application/Visita` 53 verdes; Pint limpio; PHPStan (con PHPat) 0 errores.

Registro de visitas realizadas: 20 pruebas nuevas (10 del caso de uso, 10 de la pantalla; RED visto antes de implementar); corridas de control: `tests/Feature/Application/Visita` 64 verdes, `tests/Feature/Livewire/Sedeq` 128 verdes, `tests/Feature/View` 82 verdes, `tests/Feature/Visita` 20 verdes; Pint limpio; PHPStan 0 errores. Revisión independiente aprobada (0 críticos, 0 importantes).

**Qué NO se corrió:** la suite completa (la verifica CI; regla del dueño, además de que se cae con "Premature end of PHP process" al correrla entera). **Nada se vio en un navegador**: ninguna pantalla nueva se abrió en :8001 ni en otro servidor. Ninguna prueba de CI de esta rama se ha ejecutado porque no hay push.

## Hallazgos de revisión y cómo se resolvieron

- T1 (2 importantes): CHECK de `subsanar_hasta` que evaluaba a NULL (permitía el caso inválido) y pruebas con `expectException` desnudo. Corregidos en 1 ronda.
- T2, T3, T6: aprobadas en la primera pasada.
- T4 (1 importante): si el listener post-commit fallaba, el `catch` borraba un acta ya confirmada. Corregido en 1 ronda (bandera de confirmación).
- T5 (1 importante): las claves de `$archivos` de Livewire no coincidían con los índices de `$rutas`. Corregido en 1 ronda. Sin huecos de autorización.
- T7 (1 importante): `Carbon::parse('')` devuelve "ahora", así que una fecha vacía programaba la visita en este instante. Corregido en 1 ronda; revisión de autorización, IDOR, cabeceras, N+1 y capas sin hallazgos.
- T8 (1 importante): un PDF de más de 12 MB en el campo de varios archivos fallaba en silencio (la clave `archivos.0` no se mostraba). Corregido en 1 ronda. Control de acceso sólido (matriz de roles, reautorización por petición, sin descargas en el DOM del responsable).
- T9: revisión independiente aprobada a la primera; revisión final de toda la rama: 'arreglar antes de fusionar', 0 críticos, 3 importantes (conflicto de fusión, supervisor veía niveles en captura [corregido], paso de despliegue).

## Pendientes y deuda diferida

Menores diferidos del libro, por tarea:

- **T1:** pocas pruebas de `orden`/observaciones en blanco; `realizada_el` no está atado a `estado` por CHECK.
- **T2:** typo `:(` en `index.blade.php`; contrato de días negativos de `DiasHabiles` sin documentar; sin prueba de `ResumenTramite` con visita ausente o con `en_revision` mezclado con posterior; "Mis trámites" une textos distintos con " · " (etiqueta larga, decisión del dueño); PHPStan necesita `php -d memory_limit=1G`.
- **T3:** la regla de fecha pasada también aplica en `ActualizarVisita` (no se puede corregir fecha o inspector de una visita cuya fecha ya pasó; la UI debe manejarlo o relajar si la fecha no cambia); sin prueba de frontera 150 del inspector; las pruebas de `Actualizar` no verifican la clave del error; `DatosInvalidos` con clave `nivel` (la UI la mapea); sin prueba de que un rollback no dispara el evento.
- **T4:** sin pruebas de frontera (observación de 1000 caracteres, `realizada_el` hoy o fin de semana, 10 MB exactos); comentario engañoso en `test_stored_file_removed_when_transaction_fails`; sin prueba de que `Adjuntar` conserva el archivo anterior si falla la transacción; `ArchivoActaVisita` depende de `ReglasPaecProyecto::MAX_MB` (comentarlo); `acta_nombre` es entrada cruda del cliente (vistas y cabeceras deben escaparla); un llamador externo con transacción que haga rollback tras el cierre interno dejaría un archivo huérfano (no hay tal llamador).
- **T5:** faltan pruebas (exactamente 5 archivos, nota de 2000, rechazo de visita que no es la última en `ResponderObservaciones`, actor no autorizado con archivo); bloque de precondiciones duplicado en `AceptarSubsanacion`/`ResponderObservaciones` (extraer a `VisitaInspeccion`); nombre `ArchivoActaVisita`/`$acta` engañoso ahora que sirve a respuestas; docblock de `EscuelaNivelPolicy` sin `verVisita`/`responderVisita`; `trim()` no quita NBSP; la ruta de estado incorrecto guarda y luego borra archivos (solo E/S); `validar` no rechaza elementos que no sean `UploadedFile`.
- **T6:** argumentos `$escuelaId` sin uso en ambas notificaciones; sin prueba de "1 observación" en singular ni con trámite nombrado; la prueba del enlace de respaldo ya no aplica (T8 quitó el respaldo); la prueba de cola solo comprueba `instanceof`.
- **T7:** `$observaciones`/`$acta` de `VisitaNivel` sin tipo y editables por el cliente (un payload alterado da 500 al supervisor); `nombreSeguro` solo quita control ASCII (añadir `\p{C}`) y mutila puntos; accesibilidad del campo de observaciones (label/aria apuntan a ids inexistentes, sin `aria-invalid`); `ConsultarVisita` se invoca dos veces por acción; falta `->whereNumber('escuelaNivel')` en rutas; faltan pruebas (`escuelaNivelId` alterado, bytes del cuerpo de la descarga); "Respuesta recibida" compara con el último historial de cualquier estado (comentar el supuesto); sin RED registrado para `ConsultarVisitaTest`; fechas relativas ("now", "tomorrow") aceptadas por `Carbon::parse` desde un cliente manipulado (usar `createFromFormat` estricto); confirmar que la zona horaria de `datetime-local` coincide con la mostrada.
- **T8:** la fecha límite se muestra dos veces con tres "aprox."; sin prueba del dueño llamando `responder` en estado incorrecto ni del responsable revocado entre `mount` y la siguiente llamada; `render()` hace un `EscuelaNivel::findOrFail` extra (devolver `esDueno` desde `ConsultarVisita`); los temporales de Livewire quedan 24 h (estándar); el error `archivos.N` persiste tras elegir archivos válidos hasta el siguiente envío (añadir `resetErrorBag('archivos.*')`); texto de error genérico; `role="alert"` solo en una de las dos variantes de error; `match` de `estadoTexto` duplicado entre componente y hub.
- **Registro de visitas realizadas:**
  - (a) un `desde=0000-01-01` pasa la validación de fecha pero Postgres rechaza el año 0 y daría un 500 solo por URL manipulada (exigir año >= 1);
  - (b) con `desde > hasta` se vacían ambas fechas en silencio (aviso o limpiar solo la que cambió);
  - (c) el texto "Plazo aprox. de corrección: dd/mm/aaaa (aprox.)" repite "aprox.";
  - (d) `Carbon::parse` de una `realizada_el` nula daría la fecha actual (la base no obliga `realizada_el` no nulo en visitas realizadas; hoy el caso de uso siempre la fija);
  - (e) la prueba de conteo de consultas del caso de uso no tiene render de calentamiento.
- **Revisión de navegador pendiente:** el dueño debe revisar las pantallas en :8001 (ver `task-7-report.md` para la lista de cosas a mirar).

## Preguntas abiertas para SEDEQ (no bloquean)

1. ¿El lazo de subsanación sigue el Acuerdo 243 art. 13 (5 días hábiles, informe bajo protesta, segunda visita dentro de 15 días hábiles) o el documento de Media Superior (5 días hábiles y luego el acta queda firme)?
2. Muestras reales de oficio de visita y de acta (hoy no se genera ninguno).
3. ¿Inicial y Básica difieren del flujo construido?

## Pasos tras la fusión

- En la BD destino: `php artisan migrate` y `php artisan db:seed --class=CatalogoMinimoSeeder`. Los 3 estados nuevos (`visita_programada`, `en_subsanacion`, `visita_aprobada`) existen solo por el seeder; sin él `ProgramarVisita` falla con error de FK y `/sedeq/expedientes` devuelve 500.
- Requiere el visto bueno del dueño para cualquier BD viva.

## Qué sigue

- Revisión final de la rama (lectura de código) y revisión en navegador en :8001.
- Cuando el dueño lo pida: commit, push, y espera de CI en segundo plano; el controlador registra `docs/progress.md` y guarda la sesión (`/ecc:save-session`).
- Fase 2: resolución y acuerdo; el estado `incorporado` lo emitirá esa fase, nunca la visita (`visita_aprobada` es el punto de relevo).
- Fase 3: CCT y publicidad. Fase 4: vida del acuerdo.
