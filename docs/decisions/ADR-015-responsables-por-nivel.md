# ADR-015: Responsables por nivel

**Estado:** aceptado (implementado en la rama `worktree-responsables-por-nivel`, sin commit al momento de escribir).
**Fecha:** 2026-10-07
**Spec:** `docs/superpowers/specs/2026-10-07-responsables-por-nivel-design.md`
**Plan:** `docs/superpowers/plans/2026-10-07-responsables-por-nivel.md`

Nota de numeración: el plan lo nombraba ADR-008, pero ADR-008..014 ya existen en `master`; este es el siguiente número libre.

## Contexto

ADR-002 y ADR-004 fijan un modelo de dueño único: una cuenta `solicitante` es dueña de sus escuelas y trámites, y las policies solo dejan pasar al dueño. El solicitante necesita, de forma opcional y no bloqueante, dar acceso a otras personas para que capturen la información de un nivel concreto (un `escuela_nivel`). Quien no use la función sigue exactamente el flujo actual.

## Decisión

1. **Tabla pivote `responsables_nivel`** (`user_id`, `escuela_nivel_id`, `invitado_por_solicitante_id`, `created_at`; única por `(user_id, escuela_nivel_id)`; cascada al borrar usuario o `escuela_nivel`; `RESTRICT` sobre el solicitante invitador). Migración `2026_10_07_000000_create_responsables_nivel_table.php`. Modelo `ResponsableNivel`, relación `User::accesosNivel()`.
2. **Rol Spatie `responsable_nivel`** en `RolesSeeder`.
3. **Alcance del acceso:** un `escuela_nivel`. Paso 2.4 (documentos del nivel) y Paso 3 completo; el paso 3.1 (inmueble) queda en solo lectura. Sin acceso a responsable legal, documentos de la escuela, validación final, eliminar trámite ni crear trámites.
4. **Autorización:**
   - `EscuelaNivelPolicy::view` y `::update` pasan a dueño o responsable asignado a ese nivel (decisión pura en `Application\Escuelas\VerificarAccesoResponsable`).
   - `updateInmueble` y `gestionarResponsables`: solo dueño.
   - `EscuelaPolicy` gana `verResumen` (dueño o responsable de algún nivel de la escuela); `view`, `update` y `delete` no cambian. La ruta `tramite.resumen` usa `can:verResumen,escuela`.
5. **Resumen (hub) acotado:** `ResumenTramite::paraEscuela(int, ?int $usuarioId)` devuelve, para un no dueño, solo sus niveles, sin secciones generales, con `completo = false` y `puedeEliminar = false`. `null` significa contexto confiable de solo dueño.
6. **Casos de uso** en `app/Application/ResponsablesNivel/`: `InvitarResponsableNivel`, `RevocarResponsableNivel`, `ListarResponsablesNivel`, `ListarNivelesDelResponsable`.
   - Invitar revalida en servidor: el nivel pertenece al solicitante, está `en_captura`, el correo no es de un solicitante ni de SEDEQ, y no existe ya el acceso. Un correo nuevo crea la cuenta (contraseña aleatoria, correo verificado, rol) y envía el enlace con el broker de contraseñas de Laravel; un responsable existente se reutiliza y recibe el nivel adicional (también entre solicitantes distintos).
   - Revocar borra el acceso y borra la cuenta solo si era su último acceso; lo ya capturado permanece (cuelga de `escuela_nivel`, no de la persona).
7. **Interfaz:** página Livewire `ResponsablesNivel` (ruta `tramite.responsables`, middleware `solicitante`), enlace desde el nombre del solicitante en la navbar, y vista "Mis niveles" para usuarios sin fila `solicitante` y con asignaciones. Middleware nuevo `ExigeSolicitante` (alias `solicitante`) en `/tramite/preregistro`.

## Consecuencias

- **Guardas por `->solicitante` nulo:** un usuario responsable no tiene fila `solicitante`; el código que la asumía se protegió (middleware `solicitante`, `MisTramitesController`, botón "Iniciar nuevo trámite" oculto con `puedeIniciar`). Dos tests de Auth (`AuthenticationTest`, `EmailVerificationTest`) pasaron a usar usuarios con solicitante porque ahora un `User` simple es redirigido desde `/tramite/preregistro`.
- **Cuentas de responsable huérfanas (aceptado):** si se elimina un trámite o un nivel, la cascada borra los accesos pero no la cuenta del responsable, que puede quedar sin accesos. Se acepta; seguimiento posible: limpiarlas en `EliminarTramite`.
- **Formato de Solicitud PDF (decisión del controlador, Tarea 2):** la ruta del PDF usa `can:view,escuelaNivel`, de modo que el responsable puede leerlo, y el PDF imprime datos personales del representante legal. Se acepta como intencional: es el documento de presentación del propio nivel y el Paso 2.4 exige que el responsable lo descargue, firme y suba; además el dueño eligió invitar a esa persona. Costo si es incorrecto: un delegado ve esos datos; corrección sería ruta solo de dueño o un PDF redactado para no dueños.
- **Infraestructura compartida del plantel (Tarea 7):** `InfraestructuraNivel::guardar` escribe espacios y sanitarios compartidos del plantel (unión de ADR-005). Por tanto un responsable puede escribir infraestructura compartida del plantel; el spec solo hizo de solo lectura al paso 3.1. Queda señalado al dueño.
- **Enlace de invitación (revisión final):** el token de `Password::broker()->createToken` caduca a los 60 minutos (`config/auth.php`). Quien abra el correo después recibe "token inválido" y debe usar "¿Olvidaste tu contraseña?". Aceptado; alternativa: un broker con mayor expiración.
- **Divulgación al invitar (revisión final):** invitar el correo de un responsable existente agrega el nivel y la lista muestra el nombre real de esa cuenta, no el escrito; el rechazo distinto para correos de solicitante/SEDEQ confirma que el correo existe. Impacto bajo, acorde al spec. Dos invitaciones simultáneas al mismo correo nuevo: la perdedora ve "ya pertenece a otra cuenta"; reintentar funciona.
- **Latente:** `x-tramite.documento-row` cae a la ruta de descarga solo-dueño si se omite `descargar-href`; hoy todas las filas de Paso 2.4 lo pasan explícito.
- **DDL local:** `docs/ddl_sistema_incorporacion_v3.sql` está en `.gitignore` y no contiene `responsables_nivel`; el dueño debe agregar la tabla localmente. La fuente real del esquema para esta tabla es la migración.
- **Migración:** solo se ejecutó contra la base de pruebas `sedeq_incorporacion_testing_ui` (aprobada por el dueño el 2026-10-07). No se ha corrido en la base de desarrollo; requiere autorización explícita. `findOrCreate` del rol hace que la primera invitación funcione aunque no se haya vuelto a correr `RolesSeeder`.

## Fuera de alcance

Reasignar un acceso, bitácora de auditoría, avisos al dueño cuando el responsable captura, acceso a varias escuelas por rol, edición del inmueble por el responsable.
