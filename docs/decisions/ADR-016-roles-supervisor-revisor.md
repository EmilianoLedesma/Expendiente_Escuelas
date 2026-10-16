# ADR-016: Roles supervisor y revisor de SEDEQ

**Estado:** aceptada (implementada en la rama `hud-sedeq-1`, sin commit al momento de escribir).
**Fecha:** 2026-10-16
**Spec:** `docs/superpowers/specs/2026-10-16-hud-sedeq-roles-asignacion-design.md`
**Plan:** `docs/superpowers/plans/2026-10-16-hud-sedeq-roles-asignacion.md`
**Reporte:** `docs/reports/2026-10-16-hud-roles-asignacion.md`

Nota de numeración: ADR-015 era el más alto existente; este es el siguiente número libre.

## Contexto

Hasta ahora nadie del lado de SEDEQ podía ver ni trabajar un trámite enviado. El dueño pidió un HUD con modelo de aula: un **supervisor** ve todo y reparte; un **revisor** ve solo lo que se le asignó. Esto revierte la nota del 2026-09-11 de que la revisión humana quedaba fuera del MVP. El proyecto 1 entrega las personas y el reparto; la revisión (2), la devolución por sección (3) y la resolución con QR (4) vienen después. El rol `sedeq` es administrador del sistema (`/admin`) y no ve trámites.

## Decisión

1. **Roles separados.** `supervisor` y `revisor` son roles Spatie nuevos, distintos de `sedeq`. `sedeq` no cambia (solo administración, no abre el HUD); un supervisor no abre `/admin`. La lista de los tres vive en una constante (`RolesSedeq::TODOS`). El primer supervisor se crea con `sedeq:crear-supervisor`, nunca por registro. Las cuentas SEDEQ son aparte de las de solicitante: invitar un correo que ya existe se rechaza.
2. **Revocar conserva la fila del usuario.** Revocar quita el rol `revisor`, marca `revisores.revocado_at`, devuelve sus trámites a "Sin asignar" (registrado en la bitácora) y le quita el acceso al HUD (403), pero **no borra el usuario**: la bitácora de asignaciones y `historial_estados_expediente.usuario_sedeq_id` (`NOT NULL`, sin `ON DELETE`) apuntan a él. No se copia `RevocarResponsableNivel`, que sí borra la cuenta. Un revisor revocado puede reinvitarse (se restaura el rol y se limpia `revocado_at`), y solo si no tiene otro rol ni fila de solicitante.
3. **`view` se abre a SEDEQ en solo lectura, con regla acotada por nivel.** `EscuelaPolicy::view` y `EscuelaNivelPolicy::view` admiten al dueño o a SEDEQ vía `AccesoSedeq`: un supervisor ve todo nivel que no esté `en_captura`; un revisor solo los niveles asignados y que no estén `en_captura`. `update`, `updateInmueble`, `gestionarResponsables`, `delete` y `verResumen` no cambian y se prueban con 403 para ambos roles en todas las rutas de escritura. La página de lectura es un modelo de lectura propio (`ConsultarTramiteSedeq`) y no reutiliza `ResumenTramite::paraEscuela` ni las páginas del asistente. Nadie de SEDEQ modifica datos del trámite.
4. **Asignación por `escuela_nivel`** (única por nivel). El progreso de revisión vive en su propia tabla, independiente de la asignación, de modo que desasignar nunca lo borra; la prioridad también (tabla propia, no columna de `escuela_niveles`). Los datos de nivel de escuela (documentos, responsable legal, infraestructura compartida) son visibles a un revisor asignado a cualquier nivel de esa escuela.
5. **Límite de 5 por revisor, en configuración** (`sedeq.revision.limite_por_revisor`; el dueño dijo que el número no es final). Al llegar al límite la asignación se bloquea con aviso; si el supervisor confirma, el sistema desasigna un trámite del revisor con este orden: menos progreso de revisión, luego prioridad más baja, luego asignado más recientemente. La sobrecarga nunca desasigna un nivel de la propia selección, y una selección mayor que el tope se rechaza aun con la confirmación. Todo se registra en la bitácora (quién, cuándo, qué trámite). La asignación corre bajo bloqueo de la fila `users` del revisor para sostener el límite con supervisores simultáneos.
6. **La autorización se revalida dentro de cada caso de uso.** El middleware de ruta no protege las acciones de Livewire (`POST /livewire/update` solo reaplica el middleware persistente conocido). Por eso cada caso de uso comprueba el rol del actor (`RolesSedeq::exigir`) y, en el servidor, que el destino sea un revisor activo y que el nivel siga `en_revision`. Además `ExigeRol` se registra con `Livewire::addPersistentMiddleware` y sí se vuelve a ejecutar en `POST /livewire/update` (probado), pero eso es una segunda capa, no la primera.
7. **El reporte de validación sigue siendo solo del dueño** hasta el proyecto 2 (nueva `EscuelaPolicy::verReporte`). Cubre toda la escuela, expone datos de niveles hermanos a un revisor asignado a un solo nivel y contiene instantáneas previas al envío. Se abrirá, acotado por nivel y limitado a la evaluación enviada, en el proyecto 2.
8. **Cuentas.** Supervisor, revisor y `sedeq` no pueden eliminar su cuenta (`EliminarCuenta` y `Perfil` usan la misma constante); la invitación no dispara `Registered` ni crea fila de solicitante, y nunca hace `assignRole` sobre una cuenta que ya tiene otro rol. Los tres usan `/profile`.

## Consecuencias

- Supervisor y revisor aterrizan en su HUD (`RutaInicio`, un solo resolvedor) y no en "Mis trámites" vacío; el menú de cuenta tiene rama de HUD.
- Cinco tablas nuevas (`asignaciones_revision`, `prioridades_revision`, `progreso_revision`, `bitacora_asignaciones`, `revisores`); el DDL de referencia (en `.gitignore`) no se actualizó.
- Un revisor revocado deja una fila de usuario huérfana de rol, a propósito; la reinvitación está acotada a cuentas sin otro rol.
- Limitación: revocar no impide iniciar sesión. La cuenta revocada pierde el acceso al HUD (403), pero puede entrar, es enviada a `/tramite` y ve un "Mis trámites" de solicitante vacío. Una vista dedicada de "acceso revocado" queda como mejora posible.
- Una cuenta de revisor revocado no puede eliminarse a sí misma (`RolesSedeq::cuentaProtegida`: rol de SEDEQ o fila en `revisores`); borrar el usuario rompería `revisores_user_id_fkey` y las claves de la bitácora.
- Abrir `view` a SEDEQ también abre `formato-solicitud` (imprime datos personales del representante legal) y `paso3-proximos-pasos` (redirección sin salida), según la spec; queda anotado como menor diferido.
- SEDEQ no puede descargar el reporte del motor hasta el proyecto 2.
- La devolución por sección del proyecto 3 no debe mover el nivel a `en_captura` (el revisor asignado perdería el acceso) o necesita su propio estado.
- Se mantiene una excepción documentada al orden de bloqueo del proyecto (usuario del revisor antes de `escuela_niveles`); es segura porque las cuentas SEDEQ y de solicitante son separadas. Salvedad: `asignarSeleccion` bloquea las filas `escuela_niveles` seleccionadas antes de comprobar `en_revision`, así que una selección manipulada que incluya un nivel `en_captura` podría, en teoría, formar un ciclo users↔niveles con `InvitarResponsableNivel`; Postgres lo detecta y aborta una de las transacciones; se deja para la pasada de esquema del proyecto 2.

## Alternativas consideradas

- **Reutilizar el rol `sedeq`** para supervisor y revisor: descartada; `sedeq` es administrador del sistema que nunca debe ver trámites, y el dueño pidió no renombrar ni convertir cuentas ni roles existentes.
- **Borrar al revisor al revocar** (como `RevocarResponsableNivel`): descartada; rompe las claves de la bitácora y de `historial_estados_expediente.usuario_sedeq_id`.
- **Reutilizar `ResumenTramite::paraEscuela($id, null)` o las páginas del asistente** para la vista de lectura: descartada; `null` significa contexto de dueño confiable y devuelve enlaces de edición, y las páginas son componentes de escritura detrás de `can:update`.
- **Prioridad como columna de `escuela_niveles`:** descartada para que "ningún dato del trámite cambia" sea literal.
- **Confiar solo en el middleware de rutas:** descartada; no protege las acciones de Livewire.
- **Abrir el reporte de validación a SEDEQ ya:** descartada (ver decisión 7).
- **Límite fijo en código:** descartada; el dueño dijo que el número no es final.
