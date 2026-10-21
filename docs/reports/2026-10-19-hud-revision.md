# HUD de SEDEQ, proyecto 2: revisión por pasos del revisor

Fecha: 2026-10-19. Rama `hud-revision` (desde `master` cf707f6), sin commits (el dueño decide el commit). Especificación: `docs/superpowers/specs/2026-10-19-revision-revisor-design.md`. Sigue al proyecto 1 (roles y asignación, ADR-016).

## Qué se construyó

Un revisor (o el supervisor) abre un nivel enviado (`escuela_nivel` en `en_revision`) y decide paso por paso si lo capturado es aceptable. Cada decisión queda registrada con autor, fecha y, si es un rechazo, el motivo. Al aceptar el último paso, el nivel pasa a `aprobado`.

- Página `App\Livewire\Sedeq\RevisionNivel` (ruta `sedeq.revision`, `/sedeq/revision/{escuelaNivel}`), diseño "A": lista de pasos con estado y avance a la izquierda; paso abierto a la derecha con lo capturado, documentos, pistas del motor y Aceptar / Rechazar.
- Enlaces "Revisar" en el tablero del supervisor y en la cola del revisor.
- Vista de solo lectura de todo lo que captura el solicitante, por paso (antes solo existían plantel, resumen del responsable y listas de documentos).
- Historial de decisiones de solo-agregar, conteo de avance (`progreso_revision`) y transición automática del nivel.
- Pistas del motor de validación por paso (informativas, nunca deciden).

## Decisiones del dueño

1. **Decisión por paso:** Aceptar / Rechazar; Rechazar exige un motivo. El trámite queda aceptado cuando todos los pasos están aceptados.
2. **Diseño A:** lista de pasos a la izquierda (estado por paso, conteo de avance), paso abierto a la derecha.
3. **El rechazo cae en el proyecto 3:** el proyecto 2 registra la decisión, el motivo y el historial; no devuelve el trámite al solicitante.
4. **Transiciones del nivel:** último paso aceptado, el nivel pasa a `aprobado` automáticamente (sin botón extra). Todos los pasos decididos y al menos uno rechazado, el nivel pasa a `con_observaciones` y espera ahí al proyecto 3. Mientras algún paso esté sin decidir, sigue `en_revision`. `rechazado` no se usa en el proyecto 2. Cada transición escribe una fila en `historial_estados_expediente`.
5. **Decisiones por nivel, incluidos los pasos compartidos:** los pasos generales (Datos del plantel, Responsable legal, Documentos del plantel, Niveles educativos) se deciden por separado en cada nivel, por el revisor de ese nivel. Una decisión de otro nivel del mismo plantel aparece solo como pista de solo lectura ("Aceptado por otro revisor en Secundaria, 19/10"), nunca vinculante.
6. **El supervisor también revisa:** puede decidir pasos de cualquier nivel en `en_revision`, incluso los asignados a un revisor. Mismo registro de decisión, con el supervisor como autor. Si dos personas deciden el mismo paso, vale la última decisión y el historial conserva ambas.
7. **"Validación final" no es un paso.** El motor de validación produce pistas por paso; el revisor decide.

## Modelo de datos

Dos tablas, creadas con DDL crudo vía `DB::unprepared()` en migraciones fechadas (convención del repositorio), con restricciones CHECK:

- `decisiones_revision`: decisión vigente, una fila por `(escuela_nivel_id, paso_clave)`. `estado` es `aceptado` o `rechazado`; `motivo` es obligatorio si es rechazado (CHECK). `paso_clave` restringido a las 11 claves.
- `historial_decisiones_revision`: historial de solo-agregar, una fila por cada decisión (quién, cuándo, decisión, motivo).

Migraciones: `2026_10_19_000000_create_decisiones_revision_table.php` y `2026_10_19_000100_create_historial_decisiones_revision_table.php`. Modelos: `DecisionRevision` y `HistorialDecisionRevision`. `DecisionRevision` declara `escuela_nivel_id` como llave primaria aunque la llave real es compuesta: nunca se usa `find`/`save`/`update`/`delete`/`updateOrCreate` sobre sus instancias; las escrituras van con `where()` y `DB::table()->upsert()`.

El avance se guarda en `progreso_revision` (`secciones_revisadas` cuenta pasos con decisión vigente; `secciones_total` es el número de pasos del nivel), actualizado en la misma transacción que la decisión.

## Los 11 pasos

Generales: `plantel`, `responsable`, `documentos`, `niveles`. Por nivel: `documentos_nivel`, `inmueble`, `infraestructura`, `mobiliario` (solo `inicial`, `ResumenTramite::NIVEL_CON_MOBILIARIO`), `plan_estudios`, `plantilla_docente`, `matricula`. Son 10 pasos por nivel, 11 en Inicial. Catálogo en `PasosRevision`; los títulos vienen de `ResumenTramite`.

## Transiciones del nivel

`DecidirPaso` (una transacción, con el bloqueo del nivel y la revalidación de acceso con `AccesoSedeq`) registra la decisión, actualiza el avance y recalcula el estado:

- Algún paso sin decisión: `en_revision`.
- Todos decididos y alguno rechazado: `con_observaciones`.
- Todos aceptados: `aprobado`.

Se puede volver a decidir mientras el nivel esté `en_revision` o `con_observaciones` (cambiar un rechazo a aceptado puede completar el conjunto y aprobar). Un nivel `aprobado` queda bloqueado en el proyecto 2. Un nivel `en_captura` es invisible para `AccesoSedeq` (excepción de autorización); uno `aprobado` es visible pero no decidible (precondición incumplida). `sedeq` (admin) nunca decide. Un nivel que sale de `en_revision` deja de contar contra el tope de 5 niveles del revisor, que solo cuenta `en_revision`.

## Pistas del motor

`PistasDeRevision` corre el motor de validación de forma fresca para el nivel revisado (nunca lee una evaluación guardada; misma regla que `EnviarTramite`) y asigna cada fila a un paso: filas de documentos del plantel o escuela a `documentos`; de documentos por nivel a `documentos_nivel`; las de capacidad al paso de `FilaValidacion::pasoCorreccion`. Solo filas del nivel revisado; el reporte completo sigue siendo solo del solicitante porque filtra datos de niveles hermanos (dictamen de ADR-016). Estados: cumple, advertencia, no cumple, no evaluable, y "No verificable por el sistema".

## Archivos

Creados:
- `app-laravel/app/Application/Revision/{PasosRevision,DecidirPaso,ConsultarRevision,ContenidoPasoRevision,ContenidoGeneralRevision,ContenidoPaso3Revision,PistasDeRevision}.php`
- `app-laravel/app/Livewire/Sedeq/RevisionNivel.php` y `resources/views/livewire/sedeq/revision-nivel.blade.php`
- `app-laravel/app/Models/{DecisionRevision,HistorialDecisionRevision}.php`
- las dos migraciones `2026_10_19_*`
- pruebas: `tests/Unit/Application/Revision/PasosRevisionTest.php`, `tests/Feature/Database/DecisionesRevisionTest.php`, `tests/Feature/Application/Revision/{DecidirPaso,ConsultarRevision,ContenidoPasoRevision,ContenidoGeneralRevision,PistasDeRevision}Test.php`, `tests/Feature/Livewire/Sedeq/{RevisionNivel,RevisionFlujoCompleto}Test.php`
- este reporte

Modificados: `app/Application/Tramite/ResumenTramite.php`, `routes/web.php`, `resources/views/livewire/sedeq/cola-revisor.blade.php`, `resources/views/livewire/sedeq/tablero-supervisor.blade.php`, `docs/guia-recorrido-app.md`.

Nota de proceso: el ContenidoPasoRevision original se dividió en dos clases (`ContenidoPasoRevision` para acceso y pasos generales/documentos, `ContenidoPaso3Revision` para los pasos de Paso 3) al llegar a unas 260 líneas, antes de agregar tres pasos más.

## Evidencia

- `RevisionFlujoCompletoTest` (3 pruebas, 21 aserciones, verde): el revisor asignado con `AsignarTramite` recorre los 10 pasos de un nivel de Primaria con `abrir` + `aceptar` en la página Livewire; el nivel termina `aprobado`, `progreso_revision` queda 10 de 10, sale del tablero y de la cola, y la barra del revisor muestra 0 asignados. Otra prueba deja 9 de 10 y comprueba que el tablero y la cola lo siguen mostrando (`en_revision`). La tercera rechaza `responsable` con motivo y acepta el resto (`con_observaciones`, 10 de 10) y al aceptar `responsable` el nivel pasa a `aprobado`. Los pasos de Paso 3 se abrieron y decidieron sin capturar datos: ningún constructor de contenido lanzó error.
- Como un nivel `aprobado` ya no está en `en_revision`, el tablero (`ListarTablero::filas`) no lo lista; "N de N" se verifica en `progreso_revision`, y en el tablero el avance se verifica con 9 de 10 mientras sigue en revisión.
- Suite completa (`php -d memory_limit=1G vendor/phpunit/phpunit/phpunit`): 1557 pruebas, 1557 correctas, 5421 aserciones (289 s), todo en verde.
- PHPStan (`vendor/bin/phpstan analyse --memory-limit=1G`): 0 errores.
- Pint (`vendor/bin/pint --test`): sin cambios pendientes.

## Lo que se dejó sin hacer a propósito

- **Devolver el trámite rechazado al solicitante** (desbloqueo de secciones, instantáneas y diferencias): proyecto 3. Un nivel `con_observaciones` espera ahí.
- **PDF de resolución, QR y página pública de verificación:** proyecto 4. Formato de folio y firmante también.
- **WS-7b-2** (edición de Paso 3 y filas antiguas por el solicitante): sigue pendiente y es requisito del proyecto 3.
- **`rechazado` sin uso:** un paso rechazado no es un trámite rechazado.
- **No se ha probado en un navegador.** El recorrido del dueño (asignar, abrir "Revisar", aceptar, rechazar sin y con motivo, terminar un nivel, revisar el tablero) está pendiente en la base de datos de pruebas `sedeq_incorporacion_testing_ui` con `php -S`.
- Nota opcional al aceptar: no se pidió y se omitió.

## Observaciones pendientes

Tomadas fielmente del registro de la ejecución (`.superpowers/sdd/2026-10-19-revision-revisor/progress.md`).

Menores diferidos:
- Tarea 1: `DecisionRevision` con `$primaryKey = escuela_nivel_id` es un riesgo latente para `save()`/`delete()` sobre instancias; se debe usar siempre `where()` + `DB::table()->upsert()`, nunca `save`/`find`/`updateOrCreate` (guardia opcional: `setKeysForSaveQuery`).
- Tarea 1: ninguna prueba recorre `PasosRevision::TODOS` por `titulo()`; la lista de claves del CHECK está duplicada en 2 migraciones y en `TODOS` sin una prueba de sincronía.
- Tarea 3: no hay prueba de `ConsultarRevision` para un nivel Inicial (total 11) ni para un id de nivel inexistente; `$nivel->escuela` se carga de forma perezosa (`ConsultarRevision.php:62-63,86`); no se maneja un `nombre_aprobado` en cadena vacía.
- Tarea 4: `ContenidoPasoRevision.php:71,78` resuelve `documentos_nivel` por NOMBRE del nivel (agregar el id del nivel a las filas `niveles` de `ConsultarTramiteSedeq` y comparar por id).
- Tarea 4: (corregido en la ronda final) un `pasoAbierto` manipulado producía un 500; `abrir()` sobre el paso ya abierto borra el motivo (`:157`); el encabezado de un bloque puede repetir el título de la sección (blade `:281`); no hay pruebas de 403 para id inexistente ni para usuario no SEDEQ; la validación de la clave de paso en `DecidirPaso` no se confirmó desde el diff (las pruebas de la Tarea 2 la cubren).
- Tarea 5a: no hay prueba con un `EscuelaNivel` hermano que tenga sus propias aulas/mobiliario (fijar que no hay fuga); `ContenidoPasoRevision.php:167` ruido "Sin dato títulos"; `:129` etiqueta vacía cuando faltan `nivel` y `otro_nivel_texto`; `Sala::orderBy` carga todas las salas.
- Tarea 5b: `ContenidoPaso3Revision` recarga `EscuelaNivel` (consulta extra); `ejecutar()` público no verifica acceso (ahora documentado en su docblock; es contrato del llamador); ramas de presentación sin prueba (etiqueta de reincorporación, Sala asignada, plataforma nula, modalidad o turno desconocidos).
- Tarea 6 (el motor en cada render quedó corregido en la ronda final): `whitespace-nowrap` en la etiqueta "No verificable por el sistema" puede desbordar en pantallas angostas; `PistasDeRevision` recarga `User`/`EscuelaNivel` en cada render.

No verificado (para la revisión final):
- Tarea 5a: la página `DatosInmueble` también captura terna, acreditación y constancia (`DatosInmueble.php:202-219`); confirmar que el paso `documentos` o algún bloque lo cubre para que el revisor vea todo lo que capturó el solicitante.

Rulings del controlador: sin commits de los implementadores (el dueño hace el commit al final); los pasos de Paso 3 no se mostraban hasta las Tareas 5a/5b (estado intermedio ya resuelto); el constructor de Paso 3 se separó en `ContenidoPaso3Revision`.

## Ronda final de correcciones

Hallazgos de la revisión final de toda la rama, corregidos en una sola pasada con prueba primero:

- **I1, pasos generales delgados.** Nueva clase `ContenidoGeneralRevision`, que `ContenidoPasoRevision` llama después de verificar el acceso (`ConsultarTramiteSedeq` y `TramiteLectura` no cambian, siguen siendo resumen). `responsable` muestra persona física (nombre, fecha de nacimiento, RFC, CURP) o moral (razón social, representante, escritura constitutiva con número y fecha, notario con nombre, número y ciudad, folio y fecha de inscripción del Registro Público), domicilio para notificaciones, persona autorizada a recoger, el gestor cuando existe y la terna de nombres en orden. `documentos` conserva la lista de archivos y agrega, por documento aplicable, los datos que se capturan con él: acreditación de ocupación legal, dictamen de uso de suelo, constancia de seguridad estructural, visto bueno de protección civil, INE, constancia de CURP, constancia de situación fiscal y certificado de número oficial. `documentos_nivel` conserva los archivos y agrega turno y tipo de alumnado (se capturan ahí), el recibo de pago (folio, monto, fecha, referencia) y el número de títulos del acervo. Solo hay fila para lo que la captura guardó; las lecturas van por lotes, sin consultas por documento. Se agregó el docblock `@property` a `AcreditacionOcupacionLegal` (como las demás tablas de datos de documento) para PHPStan. Pruebas nuevas en `ContenidoGeneralRevisionTest` (una por paso más la del responsable con gestor).
- **I2, `niveles` filtrado por visibilidad.** El paso ahora lista todos los niveles que la escuela solicitó, con su nombre y estado, sin importar cuáles puede ver el actor (lectura por `escuela_id`). Prueba con un revisor asignado a un solo nivel y dos niveles en la escuela.
- **M1.** `RevisionNivel::$pasoAbierto` lleva `#[Locked]` (solo `mount()` y `abrir()` lo fijan); la prueba de alteración desde el cliente se amplió.
- **M2.** Las pistas se calculan una vez por página con `#[Computed(persist: true)]`; un clic ya no reejecuta el motor (prueba con `evaluar` esperado una sola vez). `PistasDeRevision` ahora captura cualquier `Throwable` del motor, lo reporta y devuelve sin pistas, de modo que la página de revisión no responde 500 si el motor falla; `AuthorizationException` del acceso sigue propagándose. Pruebas en `PistasDeRevisionTest` y `RevisionNivelTest`.
- **M5.** `ContenidoPaso3Revision::ejecutar` advierte en su docblock que no verifica acceso.

Las pistas y el contenido pueden quedar desfasados si el solicitante cambiara datos durante la revisión, pero el trámite está bloqueado en `en_revision`, así que no ocurre. Las pistas persistidas viven en la caché de Livewire por página (reabrir la página las recalcula).
