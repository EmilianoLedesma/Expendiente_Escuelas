# Perfil de usuario y eliminación de cuenta (2026-10-13)

Rama `worktree-perfil-cuenta` (desde `master` en `c2e89fe`). Sin commit: el dueño no lo ha pedido.

## Qué se hizo

- **Página "Mi perfil" única para los tres roles** (`GET /profile`, Livewire `App\Livewire\Cuenta\Perfil`, layout `tramite` con rama `cuenta`). Tres secciones: datos personales (nombre editable, correo solo lectura), cambio de contraseña (actual, nueva, confirmación) y eliminar cuenta (modal con contraseña). Todo en español.
- **Menú lateral por rol** (`x-cuenta.menu`): solicitante ve Mi perfil, Responsables por nivel y Mis trámites; responsable por nivel ve Mi perfil y Mis niveles; SEDEQ ve Mi perfil y Panel de administración (y no ve "Eliminar cuenta").
- **Encabezado:** el nombre del usuario ahora enlaza a Mi perfil para todos los roles (antes llevaba a Responsables por nivel solo al solicitante). Sigue actualizándose en vivo con el evento `profile-updated`.
- **Eliminar cuenta (ADR-014, punto 3):** caso de uso `Application\Tramite\EliminarCuenta`. Solicitante: solo si ningún trámite se ha enviado (todos los niveles `en_captura`); borra sus trámites con la misma limpieza que `EliminarTramite` (archivos incluidos) y luego su fila de `solicitantes` y el usuario, todo en una transacción: si un trámite ya se envió, no se borra nada y se muestra "No puedes eliminar tu cuenta: ya enviaste un trámite a SEDEQ.". Responsable por nivel: se borra la cuenta y sus accesos (cascade); lo capturado queda en el nivel; puede hacerlo aunque el trámite esté enviado. SEDEQ: rechazado.
- **Se retiró lo de Breeze:** `profile.blade.php`, los tres componentes Volt de `livewire/profile/`, `ProfileTest` y `Auth/PasswordUpdateTest` (sustituidos por `PerfilTest` y `EliminarCuentaTest`).

## Decisiones

- El correo no se edita (afecta verificación e invitaciones); solo nombre y contraseña, como se pidió.
- Los responsables por nivel invitados por un solicitante que borra su cuenta quedan sin accesos (huérfanos aceptados en ADR-015).
- Las cuentas de SEDEQ no se borran desde el perfil.
- La notificación de éxito dispara el evento `toast` directamente (contrato acordado con la rama de toasts); al integrar esa rama, sustituir por el trait `NotificaToast`. Marcado con `ponytail:` en `Perfil::avisar`.

## Evidencia

- `EliminarCuentaTest` 7 pruebas (vistas fallar primero por clase inexistente), `PerfilTest` 15 pruebas.
- Suite completa 1228/1228 (4061 aserciones), Pint limpio, PHPStan 0.
- Se actualizó una prueba de navbar de `ResponsablesNivelPaginaTest` al nuevo comportamiento y se eliminó otra, que ya no aplica.

## Pendiente / no verificado

- No se vio en navegador: modal de eliminar (que no se cierre al mostrar el error), menú en 375 px, foco. `npm run build` no se corrió en el worktree (sin `node_modules`); todas las clases usadas ya existen en otras vistas.
- Falta integrar con la rama de toasts (contrato `toast` / trait) y revisar el conflicto esperado en `layouts/tramite.blade.php`.
- ADR-014 punto 3 queda cumplido; `docs/progress.md` lo actualiza el controlador al hacer merge.
