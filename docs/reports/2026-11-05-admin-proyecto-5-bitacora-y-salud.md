# 2026-11-05 — Proyecto 5 (parcial): historial por trámite, Bitácora global y Estado del sistema

Hecho directamente sobre ramas cortas fusionadas en `master` (`47e958c`, `d06c2ad`, `c2a359e`, `7e2f6c9`). El dueño pidió construir las cinco piezas del proyecto 5, una por una. Aquí van las tres primeras; faltan reportes, vista de soporte y catálogos.

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
- CI verde en `47e958c` y `d06c2ad`. `c2a359e` y `7e2f6c9`: ver `docs/progress.md`.
- Nada visto en navegador (el arnés de pruebas de Filament no pinta el panel lateral, así que el historial se probó con la vista renderizada).

## Pendiente
- Prueba manual en navegador: Historial, Estado del sistema y sus botones, Bitácora.
- Proyecto 5, lo que falta: reportes, vista de soporte, edición de catálogos (en ese orden).
- Visor de la bitácora de un trámite ya purgado (no hay fila clicable en la pantalla de Trámites).
