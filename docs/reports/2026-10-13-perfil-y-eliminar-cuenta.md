# Perfil de usuario y eliminación de cuenta (2026-10-13)

Rama `worktree-perfil-cuenta` (desde `master` en `c2e89fe`), con la rama de toasts integrada (`2e65ca1`, informe `2026-10-13-toast-notificaciones.md`). Probada por el dueño en el puerto 8001.

## Qué se hizo

- **Página "Mi perfil" única para los tres roles** (`GET /profile`, Livewire `App\Livewire\Cuenta\Perfil`, layout `tramite` con rama `cuenta`). Tres secciones: datos personales (nombre editable, correo solo lectura), cambio de contraseña (actual, nueva, confirmación) y eliminar cuenta. Todo en español.
- **Menú lateral por rol** (`x-cuenta.menu`): solicitante ve Mi perfil, Responsables por nivel y Mis trámites; responsable por nivel ve Mi perfil y Mis niveles; SEDEQ ve Mi perfil y Panel de administración (y no ve "Eliminar cuenta").
- **Encabezado compartido** (`x-shell.encabezado`, el mismo para todos los roles): "Mis trámites", el nombre (enlaza a Mi perfil) y "Cerrar sesión" comparten un mismo estilo de pestaña, texto oscuro sin subrayado; solo la página actual lleva la barra azul inferior (`aria-current="page"`). En pantallas `lg` el nav queda a la derecha de la fila del logotipo. El botón "Cerrar sesión" lleva `uppercase` propio porque los botones reinician `text-transform`.
- **Eliminar cuenta (ADR-014, punto 3):** caso de uso `Application\Tramite\EliminarCuenta`. Solicitante: solo si ningún trámite se ha enviado (todos los niveles `en_captura`); borra sus trámites con la misma limpieza que `EliminarTramite` (archivos incluidos) y luego su fila de `solicitantes` y el usuario, en una transacción: si un trámite ya se envió no se borra nada y se muestra "No puedes eliminar tu cuenta: ya enviaste un trámite a SEDEQ.". Responsable por nivel: se borra la cuenta y sus accesos (cascade); lo capturado queda en el nivel; puede hacerlo aunque el trámite esté enviado. SEDEQ: rechazado.
- **Flujo de borrado en la interfaz:** sin modal y sin contraseña (decisión del dueño). Botón `x-ui.hold-button` (1,6 s; también Enter/Espacio). Al completarse, el botón se sustituye por `x-ui.undo-notice` ("Se eliminará tu cuenta" + "Deshacer", relleno de 5 s). **La cuenta solo se borra si el tiempo se agota**; "Deshacer" la conserva. Al terminar el borrado se redirige a `login`. Si el caso de uso rechaza (trámite enviado), se muestra el error en línea y como toast.
- **Toasts:** componente `x-ui.toast-region` en los tres layouts y trait `NotificaToast` (evento `toast`, flash de sesión para redirecciones). Tarjeta neutra, animación de 150 ms, descartar arrastrando; los errores no se cierran solos.
- **Subrayados:** se quitaron los subrayados estáticos de los enlaces en todas las vistas (quedan solo los de hover en la barra institucional). "Revocar acceso" en Responsables por nivel es ahora un botón rojo con `wire:confirm`.
- **Se retiró lo de Breeze:** `profile.blade.php`, los tres componentes Volt de `livewire/profile/`, `ProfileTest` y `Auth/PasswordUpdateTest` (sustituidos por `PerfilTest` y `EliminarCuentaTest`).

## Decisiones

- El correo no se edita (afecta verificación e invitaciones); solo nombre y contraseña.
- Los responsables por nivel invitados por un solicitante que borra su cuenta quedan sin accesos (huérfanos aceptados en ADR-015).
- Las cuentas de SEDEQ no se borran desde el perfil.
- **Sin contraseña para borrar la cuenta:** el dueño lo pidió; se le advirtió que debilita la salvaguarda (alguien con la sesión abierta puede borrar). El plazo de deshacer de 5 s la compensa solo en parte.
- **Subrayado:** quitarlo diferencia los enlaces solo por color (WCAG 1.4.1); decisión explícita del dueño.
- Deshacer es real porque el borrado se difiere: si se cierra la pestaña dentro de los 5 s, no se borra nada.

## Evidencia

- `EliminarCuentaTest` 7 pruebas, `PerfilTest` 16, `ShellTest` 28, `ResponsablesNivelPaginaTest` 12 (cada cambio visto fallar primero).
- Suite completa 1248/1248 (4119 aserciones), Pint limpio, PHPStan 0.
- El dueño probó en navegador (8001): borrar con mantener presionado, deshacer, redirección a login y la barra de navegación.

## Pendiente / no verificado

- No se verificó el diseño en 375 px.
- Las cuentas de prueba borradas en el entorno de desarrollo no se pueden restaurar (el borrado es real); se reinvitan desde Responsables por nivel.
- "Eliminar trámite" podría usar también `x-ui.hold-button`.
- Los layouts invitado y app dependen de la inyección automática de Alpine por Livewire.
- ADR-014 punto 3 queda cumplido; `docs/progress.md` lo actualiza el controlador al hacer merge.
