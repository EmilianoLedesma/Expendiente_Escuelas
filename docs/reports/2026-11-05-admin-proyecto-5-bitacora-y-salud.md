# 2026-11-05 — Proyecto 5 (parcial): historial por trámite, Bitácora global y Estado del sistema

Hecho directamente sobre ramas cortas fusionadas en `master` (`f1737a5`, `8dd06da`, `6377d2d`, `6d2deb2`). El dueño pidió construir las cinco piezas del proyecto 5, una por una. Aquí van las tres primeras; faltan reportes, vista de soporte y catálogos.

## Qué se hizo
- **Historial por trámite** (`ConsultarHistorialTramite`, acción "Historial" en la pantalla Trámites). Junta en una línea de tiempo lo registrado en cuatro tablas: `bitacora_acciones` (archivar y restaurar), `historial_estados_expediente`, `historial_decisiones_revision` y `bitacora_asignaciones`. Solo lectura, sin migración. Diseño visual: eventos agrupados por día, punto de color por tipo (ámbar acción del admin, azul estado, verde aceptado, rojo rechazado, gris asignación), nivel como etiqueta y motivo en un bloque con barra de color. Estilos en línea porque el panel de Filament no compila clases de Tailwind de las vistas propias.
- **Estado del sistema** (`/admin/salud`, último en el menú). `ConsultarSaludSistema` revisa base de datos, cola de correos (fallidos, trabajos atrasados, cola `sync`), correo (driver `log`/`array`), disco de documentos (escribe y borra un archivo temporal) y migraciones pendientes; cada chequeo trae un "Qué hacer" cuando no está bien. `OperarSistema` agrega botones: procesar la cola ahora (tope de 25 s), reintentar fallidos, descartar fallidos (con confirmación) y enviar correo de prueba al propio correo del admin. Cada acción que hace algo deja una fila en `bitacora_acciones` (`objeto_tipo = 'sistema'`, solo conteos). Las migraciones no se ejecutan desde la pantalla a propósito: solo se muestra el comando.
- **Bitácora global** (`BitacoraResource`, `/admin/bitacora`). Todas las filas de `bitacora_acciones`, más reciente primero, de solo lectura (la tabla es append-only). Filtros por acción, quién, tipo de objeto y rango de fechas; búsqueda por id. El objeto se muestra por su nombre (trámite: nombre aprobado o primer nombre de la terna; cuenta: nombre; plantel: dirección), leído en vivo, así la bitácora sigue sin guardar nombres; si el objeto se purgó dice "Ya no existe". Etiquetas de acción en un solo lugar (`EtiquetasBitacora`), que también usa el historial por trámite; una prueba exige una etiqueta para cada acción de `RegistrarAccion`.
- **Marca del panel:** el texto "Laravel" de la barra superior del admin pasó a "Administración SEDEQ" (`brandName`, sin tocar `APP_NAME`).

## Decisiones
- Solo se muestra lo que el sistema registró: lo que captura el solicitante no queda en ninguna tabla de historial, así que no aparece. Un trámite purgado pierde su historial de estados y decisiones (cascada); solo queda su fila en la bitácora.
- La Bitácora global no mezcla las otras tres fuentes del historial por trámite; esas siguen solo en el trámite.
- El dueño pidió botones de acción y seguimiento en Estado del sistema, no solo lectura; se acordó la lista (cola: procesar, reintentar, descartar; correo: prueba; migraciones: solo el comando).

## Evidencia
- Pruebas enfocadas por paso (Admin, Application/Auditoria, Application/Salud, Archivo, Architecture), Pint y PHPStan limpios. Suite completa: CI.
- CI verde en `f1737a5` y `8dd06da`. `6377d2d` y `6d2deb2`: ver `docs/progress.md`.
- Nada visto en navegador (el arnés de pruebas de Filament no pinta el panel lateral, así que el historial se probó con la vista renderizada).

## Pendiente
- Prueba manual en navegador: Historial, Estado del sistema y sus botones, Bitácora.
- Proyecto 5, lo que falta: reportes, vista de soporte, edición de catálogos (en ese orden).
- Visor de la bitácora de un trámite ya purgado (no hay fila clicable en la pantalla de Trámites).

## Actualización posterior: Reportes y cierre del proyecto 5
- **Reportes** (`/admin/reportes`, fusionado en `5d77d78`, CI verde). Cinco pestañas con paginación (10/25/50) y un botón "Descargar CSV" por pestaña: niveles por estado, trámites activos y archivados, trámites activos por municipio, esperando al solicitante (con días) y carga de revisión. La pantalla muestra el resumen; el CSV baja el **detalle por registro**: una fila por nivel (trámite, nivel, estado, solicitante y correo, plantel con dirección, municipio, C.P., teléfono y correo del plantel, revisor, fecha en ese estado) o por trámite (situación activo/archivado, motivo del archivo, niveles con su estado, plantel, fecha de creación). UTF-8 con BOM para Excel. Archivados fuera de todo salvo del conteo y del detalle de "activos y archivados". Paginación en memoria; pasar a SQL si crece a decenas de miles de filas.
- **Decisiones del dueño:** la **vista de soporte** (ver pantallas del solicitante) se descartó y la **edición de catálogos** no se construye porque ningún catálogo se usa hoy (`perfiles_profesionales` no se lee en ningún código; `asignaturas` solo alimenta un selector). El proyecto 5 queda cerrado con cuatro de cinco piezas: Historial, Estado del sistema, Bitácora y Reportes.
- **Siguiente (acordado):** (1) CCT provisional por expediente; el diseño aprobado está en `docs/superpowers/specs/2026-11-05-cct-provisional-design.md` y el plan en `docs/superpowers/plans/2026-11-05-cct-provisional.md` (ambos locales, ignorados por git); aún sin código. (2) Que los trámites aceptados sigan visibles: tarjeta "Aprobado" para el solicitante y lista "Aprobados" para supervisor/admin; hoy salen de todas las pantallas al aprobarse. (3) Después, la visita de verificación y la resolución final.
