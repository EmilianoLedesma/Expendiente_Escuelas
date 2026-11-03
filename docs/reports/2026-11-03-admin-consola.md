# Consola de administración (proyecto 2 de 5) — 2026-11-03

Rama `worktree-admin-consola`, sin fusionar a `master` al cierre de este informe. Spec: `docs/superpowers/specs/2026-11-03-admin-consola-design.md`. Plan: `docs/superpowers/plans/2026-11-03-admin-consola*.md` (ambos ignorados por git). Decisión: `docs/decisions/ADR-021-archivo-y-rol-admin.md`, sección «Consola de administración». Ejecución: subagent-driven, tareas secuenciales, un implementador y un revisor por tarea. Base: proyecto 1 (`worktree-admin-fundacion`, fusionado).

## Qué se construyó

- **Menú Usuarios** (`/admin/usuarios`, `UsuarioResource`): tabla de todas las cuentas, incluidas las archivadas. Búsqueda por nombre y correo; filtros por rol, estado (activo / archivado) y plantel. Agrupada por plantel (etiqueta «calle numero_ext, colonia, municipio»); las cuentas sin trámite van en «Sin plantel». Un solicitante con trámites en varios planteles aparece una vez, bajo el plantel de menor id, con la columna «Planteles» que los lista todos.
- **Acciones por fila:** Archivar (motivo obligatorio; oculta en la propia fila), Restaurar (archivadas), Purgar (solo archivadas; escribir `PURGAR` y motivo; la ventana advierte que el motivo no debe llevar datos personales), Cambiar rol (admin / supervisor / revisor / sin rol de SEDEQ; oculta en archivadas y en la propia fila).
- **Crear cuenta de SEDEQ:** nombre, correo y rol admin o supervisor; la persona recibe un enlace para fijar su contraseña.
- **Menú Trámites archivados** (`/admin/tramites-archivados`, `TramiteArchivadoResource`): solo escuelas archivadas, con nombre aprobado y dirección del plantel, solicitante, fecha, quién archivó y motivo. Acciones Restaurar y Purgar.
- **Reglas en `app/Application/Revision/`:** `CambiarRolSedeq` y `CrearCuentaSedeq`. Filament solo las llama y muestra los rechazos como notificaciones.
  - `CambiarRolSedeq` (solo admin): rechaza cambiar el propio rol, una cuenta archivada, un solicitante, un responsable de nivel (o cuenta con rol ajeno a SEDEQ), un rol desconocido, el mismo rol y quitar admin al último admin activo. Un rol de SEDEQ a la vez; una cuenta con roles ajenos a SEDEQ o acceso de responsable se rechaza. Salir del rol de revisor libera sus asignaciones a la cola y pone `revocado_at`; entrar crea o limpia el registro de revisor. Audita `cambiar_rol` (de, a, asignaciones liberadas).
  - `CrearCuentaSedeq` (solo admin): roles admin o supervisor; correo único sin distinguir mayúsculas, también contra archivadas. Audita `crear_cuenta_sedeq` (solo el rol).
- **Sin migración.** `bitacora_acciones.accion` es VARCHAR(40) sin CHECK.
- **Comandos `sedeq:crear-admin` y `sedeq:crear-supervisor`** se conservan para el arranque.

## Verificación

- Pruebas enfocadas por tarea, con rojo visto antes del verde: `CambiarRolSedeqTest` (10), `CrearCuentaSedeqTest`, `tests/Feature/Admin` (38), y los directorios `tests/Feature/Archivo` y `tests/Feature/Revision` cuando se tocaron.
- Pint y PHPStan (con PHPat) limpios en cada tarea.
- La suite completa no se corrió localmente (regla de `CLAUDE.md`): la verifica CI al empujar la rama.
- Nada se ha visto en navegador. Lo descrito sale del código y de las pruebas automáticas.
- La base de desarrollo aún no tiene las cuatro migraciones `2026_11_03_*` del proyecto 1. Las pruebas corren contra la base de pruebas, nunca contra la de desarrollo.

## Decisiones

- Las reglas viven en `app/Application`; Filament no contiene lógica de negocio. Así lo exige la capa de presentación de `CLAUDE.md` y lo vigila PHPat.
- Un solo rol de SEDEQ por cuenta a la vez; una cuenta con roles ajenos a SEDEQ o acceso de responsable por nivel se rechaza (nunca se apila un rol de SEDEQ sobre ella).
- Auditoría con ids y conteos, nunca nombres ni correos (regla de bitácora del ADR-021).

## Lo que se dejó sin hacer

Diferidos menores, según las revisiones de tarea:

- **Tarea 1:** la comprobación de rol desconocido corre antes que la de admin; `CambiarRolSedeq` no rechaza a un actor archivado; falta prueba de revisor a admin que libere asignaciones.
- **Tarea 2:** la detección de correo duplicado depende de `lower(email)`.
- **Tarea 3:** el título del grupo de plantel sale de los datos ya cargados (sin consulta por registro).
- **Tarea 5:** una purga concurrente muestra un error de Filament en lugar de una notificación.

Fuera de este proyecto:

- Visor de bitácora, acciones masivas y exportaciones (proyecto 5).
- Cerrar y reabrir trámites (proyecto 3).
- Edición del plantel (proyecto 4).
