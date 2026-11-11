# Rol admin: fundación (proyecto 1 de 5) — 2026-11-03

Rama `worktree-admin-fundacion`, fusionada a `master`. Spec y plan (ignorados por git): `docs/superpowers/specs/2026-11-03-admin-fundacion-design.md`, `docs/superpowers/plans/2026-11-03-admin-fundacion.md`. Decisión: `docs/decisions/ADR-021-archivo-y-rol-admin.md`. Ejecución: subagent-driven, 10 tareas secuenciales, un implementador y un revisor por tarea, más revisión final de toda la rama.

## Qué se construyó

- El rol `sedeq` se renombra `admin` (migración idempotente que mueve a quienes lo tuvieran). Sigue siendo la puerta del panel Filament en `/admin`. Comando nuevo `sedeq:crear-admin {correo} {nombre}`.
- Archivar en lugar de borrar: columnas `archivado_at/por/motivo/lote` en `escuelas` y `users`; scopes globales `EscuelaNoArchivada` y `NivelDeEscuelaNoArchivada` ocultan lo archivado a toda lectura Eloquent; los lectores crudos relevantes (`ListarTablero`, `ConstruirDatosCapacidad`) filtran explícitamente. `User` no tiene scope.
- `ArchivarTramite` y `ArchivarCuenta` reemplazan a `EliminarTramite` y `EliminarCuenta`. El solicitante conserva sus límites (solo en captura; cuenta solo sin trámites enviados); el admin archiva cualquier trámite o cuenta, con motivo obligatorio. Archivar una cuenta archiva sus trámites y a los responsables que invitó bajo un mismo `archivado_lote`; archivar a un revisor devuelve sus asignaciones a la cola (`DesasignarTramite::liberar`, acción `desasignar_archivo`).
- `RestaurarTramite` y `RestaurarCuenta` restauran solo lo archivado en el mismo lote. Una cuenta archivada no puede iniciar sesión (`LoginForm`) y una sesión abierta se cierra en la siguiente petición (`CuentaActiva`, también en Filament). El correo sigue reservado.
- `PurgarTramite` y `PurgarCuenta` son el único borrado real: solo admin, solo sobre lo ya archivado, confirmación escrita `PURGAR` y motivo. Rechazan cuentas de SEDEQ o con historial de revisión (llaves foráneas), a sí mismo y al último admin. La bitácora `bitacora_acciones` guarda ids y conteos, sin nombres ni correos.

## Verificación

- Pruebas por tarea (rojo visto antes del verde), Pint y PHPStan con PHPat limpios. Corrida completa por directorios sobre las 10 tareas, antes de la ronda final de correcciones: 2306 pruebas, 0 fallos, 1 omitida (ya existente). Tras la ronda final solo se corrieron pruebas enfocadas (unas 250, 19 archivos): por la regla nueva de `CLAUDE.md`, la verificación completa la hace CI.
- Nada se ha visto en navegador. Ninguna migración se ha corrido en la BD de desarrollo.

## Rondas de corrección (hallazgos reales)

- Tarea 5: el aviso de sesión cerrada no se mostraba en el login. Tarea 7: un responsable archivado podía borrarse de verdad al revocarlo o quitar su nivel. Tarea 9: la purga podía borrar a un responsable invitado después por otro solicitante.
- Revisión final: la guarda de edición fallaba abierta para un trámite archivado (pestañas de Livewire ya abiertas podían escribir); «Mis niveles» daba 500; la capacidad del plantel contaba trámites archivados; restaurar o purgar a un responsable de la cascada afectaba la cuenta del solicitante; un solicitante que alguna vez envió un trámite no se podía purgar; `InvitarResponsableNivel` aceptaba cuentas archivadas. Los seis se corrigieron con prueba primero.

## Decisiones tomadas por el controlador

- R1: Tarea 9 recuperó el cuerpo del antiguo `EliminarTramite` desde git (`f180bc4`). R2: se commitea en la rama del worktree por haber elegido el dueño la ejecución con subagentes; no se empujó ni fusionó sin orden. R3: ninguna escritura contra la BD de desarrollo.
- R4: la cascada de archivo solo alcanza a responsables sin otro acceso y que no sean solicitantes (ADR-015). Pendiente de confirmación del dueño; si no, se ensancha la consulta.

## Lo que se dejó sin hacer

- Rutas del solicitante que aún borran de verdad (quitar un nivel en borrador; revocar a un responsable): previas a esta rama, documentadas como excepción en el ADR; el dueño decide si se archivan también.
- Cuentas de SEDEQ con historial de revisión se archivan pero no se pueden purgar (llaves foráneas).
- Sin interfaz de admin (proyecto 2), sin cerrar/reabrir trámites (proyecto 3), sin edición de plantel (proyecto 4).
- Menores diferidos: ver el libro mayor local (`.superpowers/sdd/2026-11-03-admin-fundacion/progress.md`, ignorado por git): carrera en asignación, plantel compartido tras archivar un trámite enviado, reinvitar a un revisor archivado, notificar a un responsable archivado, `PurgarTramite::purgar()` público sin guarda de transacción, motivo de texto libre que puede contener nombres (advertirlo en la pantalla de purga del proyecto 2).
- Para usar en desarrollo: aplicar las 4 migraciones `2026_11_03_*` (con permiso del dueño) y crear el primer admin con `sedeq:crear-admin`.
