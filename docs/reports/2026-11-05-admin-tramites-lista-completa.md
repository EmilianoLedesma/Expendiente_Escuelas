# 2026-11-05 — Pantalla "Trámites" del admin lista todos los trámites

Rama `worktree-admin-tramites` (worktree `.claude/worktrees/admin-tramites`), sin commit ni merge todavía.

## Qué se hizo
- `TramiteArchivadoResource` ya no filtra solo archivados: lista todas las escuelas. Etiqueta, slug y navegación pasan de "Trámites archivados" / `tramites-archivados` a "Trámites" / `tramites`.
- Filtro `TernaryFilter` "Estado" (Todos por defecto, Archivados, Activos) y columna "Estado".
- Acción **Archivar** en filas activas (motivo obligatorio), que llama a `ArchivarTramite`. **Restaurar** y **Eliminar** solo se ven en filas archivadas.
- `docs/guia-recorrido-app.md` actualizado.

## Decisión
Se renombró solo lo visible (etiqueta, slug, navegación). Clase, carpeta y namespace siguen como `TramitesArchivados` / `TramiteArchivadoResource` para no tocar rutas de Livewire ni imports; renombrarlos es un cambio aparte.

## Evidencia
- Pruebas primero: 6 fallos esperados (filtro, acciones, URL), luego verde.
- Enfocadas: `tests/Feature/Admin` y `tests/Architecture`, 44/44. Pint y PHPStan limpios. Suite completa pendiente de CI (regla del dueño).
- Sin revisión en navegador ni escrituras en la BD de desarrollo.
- Entorno: el worktree usa una copia real de `vendor` (un junction hacía que el autoload cargara el código de `master`).

## Pendiente
- Prueba manual en dev. Commit, merge y entrada en `docs/progress.md` cuando el dueño lo pida.
