# Sesión: botón de cerrar sesión en el wizard de trámite

**Fecha:** 2026-09-10
**Rama/worktree:** `worktree-tramite-logout`, `.claude/worktrees/tramite-logout`

## Diagnóstico

No existía ninguna ruta `POST /logout` en la aplicación. El único mecanismo de logout funcional era el componente Volt de Breeze (`resources/views/livewire/layout/navigation.blade.php`), cuyo método `logout()` llama a `App\Livewire\Actions\Logout`, pero ese componente solo se renderiza en `/dashboard` — una página que, según `docs/progress.md` (2026-09-09), ya no es destino de ningún flujo de login/registro real. `resources/views/components/tramite/top-nav.blade.php` (usado por Paso 1, 2 y el placeholder de Paso 3) es un componente Blade sin clase, así que nunca pudo tener un `wire:click="logout"` propio.

## Fix

- `routes/auth.php`: nueva ruta `Route::post('logout', ...)` dentro del grupo `auth` existente, nombrada `logout`, reutilizando la clase `Logout` ya existente (sin duplicar lógica de sesión).
- `top-nav.blade.php`: formulario POST simple (`@csrf` + `<x-ui.button-secondary>`), envuelto en `@auth` (defensivo — hoy el nav solo se renderiza en rutas autenticadas, pero es la semántica correcta). Sin JS ni componente Livewire nuevo — HTML nativo es suficiente.
- 2 tests nuevos en `AuthenticationTest`: `POST /logout` real desloguea y redirige a `login`; el nav de trámite efectivamente muestra el botón.

## Verificación

Suite completa: 183/183 (181 previas + 2 nuevas). Pint limpio. PHPStan 0 errores (con `--memory-limit=512M`).

## Qué no se hizo

- No se tocó el componente Volt `layout.navigation` ni la ruta `/dashboard` — quedan igual de muertos que antes, fuera del alcance de este fix puntual.
- No se agregó logout al panel Filament (`/admin`) — Filament ya trae uno propio en el menú de usuario por defecto, no se verificó visualmente pero es comportamiento estándar del framework, no un gap reportado.

## Próximo paso

`superpowers:finishing-a-development-branch` — merge a `master`.
