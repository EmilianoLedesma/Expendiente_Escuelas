# Reporte: Responsables por nivel (2026-10-07)

Rama: `worktree-responsables-por-nivel`. Sin commits: todo en el árbol de trabajo (regla de disciplina de commits). Decisión durable en `docs/decisions/ADR-015-responsables-por-nivel.md`. Spec y plan en `docs/superpowers/specs|plans/2026-10-07-responsables-por-nivel*`.

## Qué se construyó (Tareas 1-8)

1. **Datos:** migración `responsables_nivel`, modelo `ResponsableNivel`, rol `responsable_nivel` en `RolesSeeder`, `User::accesosNivel()`, trait de tests `ConNivelParaResponsables`. 3 tests.
2. **Autorización:** `VerificarAccesoResponsable`; `EscuelaNivelPolicy` (`view`/`update` dueño o responsable; `updateInmueble` y `gestionarResponsables` solo dueño); `EscuelaPolicy::verResumen`; ruta `tramite.resumen` con `can:verResumen,escuela`. 3 tests; `AutorizacionEscrituraTest` actualizado al nuevo middleware.
3. **Invitar:** `InvitarResponsableNivel` y notificación `InvitacionResponsableNivel`. 5 tests.
4. **Revocar y listar:** `RevocarResponsableNivel`, `ListarResponsablesNivel`, `ListarNivelesDelResponsable`. 6 tests.
5. **Hub acotado:** `ResumenTramite::paraEscuela(int, ?int)`, controlador y `Recorrido` pasan `auth()->id()`. 3 tests.
6. **Mis niveles y middleware `solicitante`:** `ExigeSolicitante`, vista `tramite/mis-niveles`, ajuste de `MisTramitesController`. 3 tests.
7. **Redirecciones y 3.1 solo lectura:** trait `RedirigeAPaso2` (usado en `CompuertaPaso3` y `Paso24DocumentosNivel`), `DatosInmueble::$soloLectura` con `abort_unless` en `guardar()`. 4 tests; se verificó por mutación que quitar el `abort_unless` rompe el test.
8. **Página de gestión:** Livewire `ResponsablesNivel`, ruta `tramite.responsables`, enlace en la navbar. 6 tests, más 2 de la ronda de corrección.

## Verificación

- Suite completa tras el lote de limpieza: **1081 tests, 1081 pasaron, 3519 aserciones** (según `cleanup-report.md`), con `DB_DATABASE=sedeq_incorporacion_testing_ui`.
- `vendor/bin/pint --test`: passed. `vendor/bin/phpstan analyse --no-progress --memory-limit=1G`: passed, 0 errores (ambos según el reporte de limpieza).
- Comando de la suite: `php -d memory_limit=1G vendor/bin/phpunit`.

## Decisiones y fallos registrados en el ledger

- **Tarea 2:** la ruta del Formato de Solicitud PDF usa `can:view,escuelaNivel`, así que el responsable puede leer el PDF con los datos personales del representante legal. Aceptado como intencional (ver ADR-015). Hay que comunicárselo al dueño.
- **Tarea 7:** `InfraestructuraNivel::guardar` escribe espacios y sanitarios compartidos del plantel (unión de ADR-005), por lo que un responsable puede escribir infraestructura compartida del plantel. El spec solo hizo de solo lectura el paso 3.1. Se comunica al dueño.
- **Cuentas huérfanas:** tras borrar un trámite o nivel pueden quedar cuentas de responsable sin accesos; aceptado.
- **DDL:** `docs/ddl_sistema_incorporacion_v3.sql` está en `.gitignore`; el dueño debe agregar la tabla localmente.
- Sin commits durante la ejecución (la regla de CLAUDE.md prevalece sobre el paso de commit del skill).
- Modelos: implementadores sonnet en 1, 3, 4, 6, 8; opus en 2, 5, 7 y en el lote de limpieza.

## Desviaciones respecto al plan o a los briefs

- Tarea 3: se inicializa `$esNueva = false` antes del `if` (ruling del controlador, por PHPStan); typo del test corregido (`.TEST`); `Solicitante::with('user')...->user->name` sustituido por `User::whereHas('solicitante', ...)` para PHPStan.
- Tarea 4: docblock `@return BelongsTo<Plantel, $this>` agregado en `Escuela::plantel()` (fuera de la lista de archivos; solo docblock); un método de test renombrado por Pint.
- Tarea 2: `AutorizacionEscrituraTest` actualizado a `can:verResumen,escuela`.
- Tarea 6: `AuthenticationTest` y `EmailVerificationTest` pasaron a usar `Solicitante::factory()->create()->user`.
- Tarea 8, ronda de corrección 1: `session()->flash` cambiado a `session()->now` y texto `sr-only` en el enlace de la navbar; dos tests nuevos.
- Limpieza C6: `MisTramitesTest::test_usuario_sin_solicitante_ve_el_estado_vacio` afirmaba que se veía "Iniciar nuevo trámite"; se invirtió a `assertDontSee`. Se solapa con el test nuevo de `MisNivelesResponsableTest`; se puede borrar uno.

## Lote de limpieza C1-C8

- **C1:** docblock de `EscuelaNivelPolicy` reescrito como una sola regla.
- **C2:** test de que un responsable de un nivel de otra escuela no tiene `verResumen`.
- **C3:** bloqueo `lockForUpdate` del usuario al invitar; captura de `UniqueConstraintViolationException` como error de correo ocupado; fallo del envío de correo capturado con `rescue(..., report: true)` tras el commit (la invitación queda guardada). 2 tests nuevos.
- **C4:** `lockForUpdate` del usuario en `RevocarResponsableNivel`; test de listado con forma completa de filas.
- **C5:** docblock de `paraEscuela` (`null` = contexto de solo dueño) y `assertDontSee('Datos generales')`.
- **C6:** botón "Iniciar nuevo trámite" oculto con `puedeIniciar` para usuarios sin solicitante.
- **C7:** `#[Locked]` en `DatosInmueble::$soloLectura` y enlace "Volver al resumen" en el aviso bloqueado.
- **C8:** `wire:key` en ambos bucles de `responsables-nivel.blade.php`.

El ledger registra que la revisión (opus) del lote de limpieza fue despachada; su veredicto y una revisión final de toda la rama no figuran en los archivos consultados.

## Pendientes menores de las revisiones (no resueltos, según el ledger)

- Revocar un acceso inexistente reporta éxito (Tarea 8).
- `x-tramite.documento-row` con fallback a ruta solo-dueño si se omite `descargar-href`.
- Mientras el Paso 2 del dueño está incompleto, el responsable ve secciones bloqueadas con "Completa primero" y sin tarjeta "siguiente" (UX).
- Un usuario admin/SEDEQ que llegara al hub vería la vista restringida vacía; hoy `verResumen` lo bloquea.

## Qué NO se verificó

- **No hubo pasada en navegador:** ajuste de la navbar a 375 px, orden de foco en la página nueva, ni el renderizado real del correo de invitación (los tests usan `Notification::fake()` y la ruta `login`/`password.reset` del correo no se renderiza).
- **La migración no se ha corrido en la base de desarrollo;** solo en `sedeq_incorporacion_testing_ui`. Requiere autorización explícita del dueño.
- **La ruta de aviso real tras commit y el bloqueo `lockForUpdate`** no se pueden probar con una sola conexión (carrera no reproducida en tests).

## Pasos del dueño

1. Autorizar y correr la migración en desarrollo (`php artisan migrate`); `findOrCreate` permite que la primera invitación funcione aunque no se haya vuelto a correr `RolesSeeder`.
2. Agregar `responsables_nivel` a su copia local de `docs/ddl_sistema_incorporacion_v3.sql` (ignorado por git).
3. Decidir sobre el PDF con datos del representante legal y sobre la escritura de infraestructura compartida por parte del responsable.
4. Indicar si se hace commit y merge. `docs/progress.md` lo actualiza el controlador al hacer el merge.
