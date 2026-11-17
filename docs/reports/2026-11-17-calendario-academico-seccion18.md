# Sección 18 del Proyecto Educativo: Calendario Académico

Fecha: 2026-11-17. Rama: `master` (trabajo directo, sin commit ni push). Solo media superior.

## Qué se construyó

Una sola página con un único "Guardar", con la misma forma que la sección 15 (Mapa Curricular):

- **Calendario académico:** un archivo **PDF** requerido. Reemplaza al campo de texto "Nombre de la carpeta o archivo" del dueño: el nombre se toma del archivo subido y se muestra como "Archivo guardado". Solo PDF, validado por contenido (empieza con `%PDF-`, se puede leer, máximo 10 MB, sin tope de páginas); una imagen, un Word o un texto renombrados a `.pdf` se rechazan.
- **Descripción del calendario académico:** opcional. Vacía es válida y se guarda como NULL; con texto debe tener 50 a 250 palabras. El texto de ayuda quedó "Puede describir aquí las fechas clave del calendario académico...", sin la frase "si no puede adjuntar imagen", porque las imágenes no se aceptan.

Decisiones mías, aprobadas con "Yes": descripción opcional (como en la sección 15), el rango de 50 a 250 palabras, 10 MB y sin tope de páginas. Se copió el patrón de las secciones 15 y 17 por consistencia; ya son tres secciones casi idénticas (15, 17 y 18) y una base común quitaría la duplicación, pero eso es una refactorización aparte que el dueño no ha pedido.

## Comportamiento

- Guardado todo o nada: si falla la descripción no se sube el archivo; si falla el archivo no se escribe nada.
- Sin archivo nuevo se conserva el ya guardado; un archivo nuevo lo reemplaza y el anterior se borra solo cuando el guardado se confirma. Un guardado con error conserva lo guardado, y si la transacción exterior se revierte no queda el archivo nuevo en disco.
- La sección está **completa** cuando hay un PDF guardado y la descripción es vacía o válida. El índice y el menú lateral la marcan igual que las demás. No marca el paso `proyecto_educativo` como completado. Un trámite enviado rechaza el guardado con el aviso habitual.

## Backend

- Tabla `proyecto_educativo_calendario_academico` (migración `2026_11_17_000300`, ejecutada en la base de desarrollo por la regla permanente del dueño): una fila por `escuela_nivel`, `archivo_path`, `archivo_nombre`, `archivo_paginas` (las tres juntas o ninguna; páginas >= 1) y `descripcion` (no en blanco si existe), con `CHECK` con nombre.
- `GuardarCalendarioAcademicoProyecto`, `ReglasCalendarioAcademicoProyecto`, `CalendarioAcademicoProyectoCapturada` (nunca expone la ruta de disco), `DatosCalendarioAcademicoProyecto`, modelo `ProyectoEducativoCalendarioAcademico` (con `rutasDe`), `SeccionesProyectoEducativo::calendarioAcademico()` y la entrada en `EstadoSeccionesProyecto`. Reutiliza `RevisarPdfProyecto` (sin tope de páginas) y el almacén de documentos.
- Limpieza de archivos: `PurgarTramite` y `RegistrarNivelesSeleccionados` reúnen la ruta del PDF antes del borrado y la eliminan tras el commit; `ResumenEliminacionNivel` cuenta el PDF como documento a eliminar.

## Evidencia

- **Se vieron fallar primero** (0 de 30 pruebas de aplicación pasaban y la de purga fallaba por la tabla inexistente) y luego pasaron.
- En verde: sección 18 y las cuatro pruebas del marcador "Contenido por definir" (ahora apuntan a la sección 19, Servicios de Biblioteca): 92. Regresiones por tandas (por el cierre conocido de PHP con corridas grandes): `Archivo` y `Validacion` 166, `EscuelaNiveles` 56, mapa curricular y PAEC 58, programas de estudios y planeación académica 67, demás archivos Livewire de Proyecto Educativo 72 + 113 (incluye el menú lateral). Pint limpio, PHPStan 0 errores.
- La prueba de CHECK de la base usa un savepoint por insert.
- La suite completa no se corrió localmente (regla del dueño); falta el veredicto de CI de esta sección (sin commit ni push). Nada se probó en un navegador (el selector de archivos y la subida real).

## Pendiente

- Recorrido en navegador de las secciones 4 a 18 (subida real de PDF, mensajes de error, "Archivo guardado").
- No hay descarga del PDF ni vista de revisor para estos archivos (calendario, planeación académica, programas adaptados, mapa curricular, PAEC y planeación de formación continua).
- `ResumenEliminacionNivel` no cuenta las filas de texto de las secciones 1 a 18.
- Las pruebas de CHECK con bucle de inserts en secciones anteriores (por ejemplo `GuardarAcademiasProyectoTest` y `GuardarPaecProyectoTest`) pueden tener el defecto del savepoint.
- Las secciones 15, 17 y 18 duplican casi todo su código; una base común sería una refactorización aparte.
- Siguiente: sección 19 (Servicios de Biblioteca y Acervo Bibliográfico), luego 20; después hacer completable el paso, el plan del CCT provisional y la visibilidad de trámites aprobados.
