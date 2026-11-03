# ADR-021 — Archivo (borrado lógico) de trámites y cuentas, y rol `admin`

**Estado:** resuelto el 2026-11-03 (rama `worktree-admin-fundacion`, proyecto 1 de la consola de administración)
**Fecha:** 2026-11-03
**Relacionado:** ADR-001 (capas), ADR-014 (decisiones de producto), ADR-015 (responsables por nivel), ADR-016 (roles supervisor/revisor), spec `docs/superpowers/specs/2026-11-03-admin-fundacion-design.md`

Nota de numeración: ADR-020 era el más alto existente y no hay ningún `PENDIENTE-` sobre este tema; este es el siguiente número libre.

## Contexto

- No existía un administrador real: el rol `sedeq` daba acceso al panel Filament `/admin`, pero no había comando para crearlo ni funciones propias.
- El solicitante podía borrar su trámite o su cuenta y los datos desaparecían de forma definitiva, sin posibilidad de recuperarlos ni rastro de quién lo hizo.

## Decisión

1. **Rol `admin`.** El rol `sedeq` se renombra `admin` (migración `2026_11_03_000000`, idempotente: sin rol `sedeq` no hace nada; si existen ambos, mueve los titulares y borra `sedeq`). Es la compuerta del panel Filament `/admin` (`User::canAccessPanel` pregunta `hasRole(RolesSedeq::ADMIN)`) y no opera trámites: los casos de uso exclusivos del admin llaman `RolesSedeq::exigir($actor, RolesSedeq::ADMIN)`. Las cuentas se crean con `php artisan sedeq:crear-admin {correo} {nombre}`.
2. **Archivar en lugar de borrar.** `escuelas` y `users` ganan `archivado_at`, `archivado_por`, `archivado_motivo` y `archivado_lote` (migración `2026_11_03_000100`). La acción de borrar del solicitante pasa a ser `ArchivarTramite` / `ArchivarCuenta`: se conservan filas y archivos.
3. **Ocultar por scope.** Los global scopes `EscuelaNoArchivada` y `NivelDeEscuelaNoArchivada` ocultan las escuelas archivadas y sus niveles en todas las consultas Eloquent; el código de admin los quita con `withoutGlobalScope`. Los lectores con `DB::table` crudo no pasan por scopes. Filtran `archivado_at` de forma explícita los que recorren varias escuelas: `ListarTablero` (tablero SEDEQ) y `ConstruirDatosCapacidad::matriculaPlantel` (matrícula de todo el plantel). Los demás lectores crudos leen una sola escuela o un solo nivel, y solo se llega a ellos por un binding o una consulta Eloquent que ya aplica el scope (un id archivado no se resuelve antes). La guarda de escritura `TramiteEditable` falla cerrada (etapa `archivo`) si la escuela ya no es visible, para que un componente Livewire abierto antes del archivo no escriba en él.
4. **Cuenta archivada.** No puede iniciar sesión, y el middleware `CuentaActiva` cierra la sesión abierta en la siguiente petición (cierra, invalida y regenera el token, y redirige a `login` con el aviso «Cuenta archivada, contacta a SEDEQ.»). El correo sigue reservado: la fila de `users` no se borra, así que no se puede registrar otra cuenta con él.
5. **Cascada por lote.** Archivar una cuenta genera un `archivado_lote` (UUID) que comparten la cuenta, sus trámites aún no archivados y los responsables por nivel arrastrados. Restaurar una cuenta restaura solo lo marcado con su mismo lote; lo archivado aparte (otro lote) sigue archivado. Las asignaciones de revisión que se liberaron al archivar no se reasignan; la liberación queda en `bitacora_asignaciones` con la acción `desasignar_archivo` (migración `2026_11_03_000300`).
6. **Quién puede.** El solicitante archiva su trámite solo mientras todos sus niveles estén en captura y su cuenta solo sin trámites enviados; las cuentas de SEDEQ no se archivan solas (ADR-014). El admin archiva cualquier trámite o cuenta con motivo obligatorio, salvo su propia cuenta y la del último admin activo. Solo el admin restaura.
7. **Purga: el único borrado definitivo.** `PurgarTramite` y `PurgarCuenta`: solo admin, el objetivo ya debe estar archivado, confirmación escrita `PURGAR` y motivo no vacío (`Purga::validar`). Dejan una fila en `bitacora_acciones` con ids y conteos, nunca nombre ni correo del sujeto. La purga de un trámite no toca el plantel ni sus documentos (el plantel puede compartirse); borra sus archivos en disco solo después de confirmarse el borrado de filas.
8. **Bitácora.** `bitacora_acciones` (migración `2026_11_03_000200`) es append-only y sin FK ni CHECK en `accion`: `actor_id` y `objeto_id` apuntan a filas que pueden purgarse, y proyectos posteriores añadirán acciones. `archivado_por` tampoco lleva FK, por la misma razón: purgar una cuenta nunca debe romperse por quien archivó.

## Consecuencias y límites descubiertos al implementar

- **Cuentas de personal con historial no se purgan.** Una cuenta de SEDEQ (rol `admin`/`supervisor`/`revisor` o fila en `revisores`) o referida por el historial de revisión (`asignaciones_revision`, `bitacora_asignaciones`, `decisiones_revision`, `historial_decisiones_revision`, `historial_estados_expediente`, `prioridades_revision`) no se puede purgar: esas columnas son FK NOT NULL y `PurgarCuenta` lo rechaza sin borrar nada. Esto cubre también la cuenta propia del admin y la del último admin.
- **Refinamiento de ADR-015 (el propietario debe confirmarlo).** La cascada de archivo de una cuenta solo archiva responsables por nivel que no tengan acceso en ningún otro nivel fuera de los de esa cuenta y que no sean solicitantes. ADR-015 hablaba de quitar el acceso de los responsables de los niveles; archivar a quien aún sirve en otro trámite lo dejaría sin cuenta.
- **Purga bloqueada por acceso ajeno.** Si un responsable del lote conserva acceso en el trámite de otro solicitante (por ejemplo, reinvitado tras archivarse), `PurgarCuenta` rechaza todo en lugar de borrar ese acceso en silencio.
- **Seguimiento conocido:** `InvitarResponsableNivel` todavía acepta invitar a una cuenta archivada; no hay comprobación de `archivado_at`.
- **El `motivo` de la bitácora es texto libre** y puede contener nombres. La pantalla de purga del proyecto 2 debe advertirlo.
- **El nombre `bitacora_acciones` es genérico a propósito** (ver punto 8).
- **Fuera de este proyecto:** la consola de admin en `/admin` (pantallas de listar, restaurar y purgar), cerrar/reabrir trámites y la edición del plantel por el admin son proyectos posteriores. Hoy restaurar y purgar existen solo como casos de uso en `app/Application/Archivo/`.
