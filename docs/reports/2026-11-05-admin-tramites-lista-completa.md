# 2026-11-05 — Pantalla "Trámites" del admin lista todos los trámites

Rama `worktree-admin-tramites`, fusionada en `master` (`9dd3eeb`). Proyecto 3 (cerrar y reabrir) y pantalla de Planteles al final de este documento.

## Qué se hizo
- `TramiteArchivadoResource` ya no filtra solo archivados: lista todas las escuelas. Etiqueta, slug y navegación pasan de "Trámites archivados" / `tramites-archivados` a "Trámites" / `tramites`.
- Filtro `TernaryFilter` "Estado" (Todos por defecto, Archivados, Activos) y columna "Estado".
- Acción **Archivar** en filas activas (el motivo pasó a ser opcional, ver abajo), que llama a `ArchivarTramite`. **Restaurar** y **Eliminar** solo se ven en filas archivadas.
- `docs/guia-recorrido-app.md` actualizado.

## Decisión
Se renombró solo lo visible (etiqueta, slug, navegación). Clase, carpeta y namespace siguen como `TramitesArchivados` / `TramiteArchivadoResource` para no tocar rutas de Livewire ni imports; renombrarlos es un cambio aparte.

## Evidencia
- Pruebas primero: 6 fallos esperados (filtro, acciones, URL), luego verde.
- Enfocadas: `tests/Feature/Admin` y `tests/Architecture`, 44/44. Pint y PHPStan limpios. Suite completa pendiente de CI (regla del dueño).
- Sin revisión en navegador ni escrituras en la BD de desarrollo.
- Entorno: el worktree usa una copia real de `vendor` (un junction hacía que el autoload cargara el código de `master`).

## Proyecto 3: cerrar y reabrir un trámite (`ee83076`, `ecccfce`)
Decisión del dueño: no se construye un estado "Cerrado". Cerrar = Archivar y reabrir = Restaurar, porque el archivo ya hace lo necesario (el solicitante no ve nada de un trámite archivado, comprobado a mano por el dueño). El admin actúa solo: sin solicitante, sin aprobación, y solo queda una traza fechada en `bitacora_acciones`.

- **Motivo opcional** al archivar desde la pantalla (`ArchivarTramite` ya no lo exige al admin).
- **Nombre de la escuela:** si no hay `nombre_aprobado` se muestra el primer nombre de la terna (ningún código escribe `nombre_aprobado` todavía, así que casi siempre se verá la terna).
- **Cuatro correos al dueño** (en cola, solo correo): trámite cerrado (solo el estado, sin enlace ni quién lo cerró), reabierto (con enlace), enviado a revisión, y revisión terminada sin observaciones. Eventos nuevos `TramiteCerradoPorAdmin`, `TramiteReabiertoPorAdmin`, `TramiteEnviado`, `RevisionAprobada`, despachados tras el commit. No hay correo si archiva el propio dueño ni en la cascada de una cuenta.
- **Texto neutro para el solicitante:** se quitó "SEDEQ" de mensajes, correos y pantallas del solicitante ("Enviar a revisión", "contacta a un administrador", "Se revisó el trámite…"). Se dejaron logo, títulos de página, PDFs, pantallas de personal y logs.

## Pantalla Planteles (proyecto 4, reducido)
El dueño no tiene un caso concreto para editar un plantel, así que no se construyó edición (el revisor ya puede devolver con observaciones). Se hizo solo lo pedido: ver y archivar.

- `PlantelResource` (solo lectura): dirección, municipio, C.P., "activos de total" y estado.
- **Archivar plantel** (con confirmación y motivo opcional): `ArchivarPlantel` archiva todos los trámites activos del plantel en un solo lote, vía `ArchivarTramite`, y registra `archivar_plantel`. **Restaurar plantel**: `RestaurarPlantel` devuelve solo los trámites del último lote archivado con el plantel (los archivados por separado siguen archivados). Solo admin.
- Sin columna nueva en `planteles`: un plantel cuenta como archivado cuando tiene trámites y ninguno activo. Un plantel sin trámites no se puede archivar.
- **BD de desarrollo (con permiso del dueño):** se borró el plantel 1 (huérfano, sin escuela) con sus hijos en cascada y los 6 archivos de documentos. Quedan los planteles 2 (user 4) y 3 (user 7). El 1 y el 2 tenían la misma dirección.

## Evidencia
- Pruebas enfocadas (Admin, Archivo, Notifications, Revision, Sedeq, Application, Livewire/Tramite y más), Pint y PHPStan limpios en cada paso.
- CI: `ee83076` verde. `ecccfce` y el merge `06d9502` fallaron en una sola prueba, `AvisosEnValidacionFinalTest`, que aún afirmaba "SEDEQ" en un aviso que ya es neutro; se corrigió el texto esperado.

## Pendiente
- Prueba manual en navegador (Trámites, Planteles, Cambiar rol, Crear cuenta de SEDEQ).
- Archivar los caminos de borrado duro del solicitante (`RegistrarNivelesSeleccionados::quitar`, `RevocarResponsableNivel`): necesita diseño.
- Proyecto 5: visor de bitácora, reportes, catálogos, vista de soporte, salud del sistema.
