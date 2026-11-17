# Sección 15 del Proyecto Educativo: Mapa Curricular

Fecha: 2026-11-17. Rama: `master` (trabajo directo, sin commit ni push). Solo media superior.

## Qué se construyó

Una sola página con un único "Guardar":

- **Mapa curricular:** un campo para subir un archivo PDF. Reemplaza al campo de texto "Nombre de la carpeta o archivo" del dueño: el nombre se toma del archivo subido y se muestra como "Archivo guardado". No se acepta ningún otro tipo de archivo.
- **Descripción del mapa curricular:** opcional. Vacía es válida y se guarda como NULL; con texto debe tener 50 a 250 palabras.

Decisiones mías, aprobadas con "Yes": el rango de 50 a 250 palabras, que la descripción sea opcional (el dueño la planteaba como alternativa para quien no podía adjuntar imagen, y ahora el PDF es obligatorio), 10 MB como tope (igual que el PAEC) y **sin tope de páginas** (un mapa curricular puede ser una sola lámina grande). El texto de ayuda de la descripción quedó "Puede describir aquí la estructura general del mapa curricular...", sin la frase "si no puede adjuntar imagen", porque las imágenes no se aceptan.

## Solo PDF

La validación lee el **contenido** del archivo y no confía en el nombre ni en el tipo que declara el navegador:

- El archivo debe empezar con `%PDF-`; una imagen PNG o JPEG, un documento Word o un texto renombrados a `.pdf` se rechazan con "Debe ser un archivo PDF.". Un archivo vacío también.
- Debe poder leerse (se cuentan sus páginas); uno dañado o con contraseña se rechaza con "No se pudo leer el PDF...".
- Máximo 10 MB.
- El campo del formulario además declara `accept="application/pdf"`, de modo que el selector del navegador solo ofrece PDF, pero la barrera real es la del servidor.

## Comportamiento

- Guardado todo o nada: si falla la descripción no se sube el archivo; si falla el archivo no se escribe nada.
- Sin archivo nuevo se conserva el ya guardado; un archivo nuevo lo reemplaza y el anterior se borra solo cuando el guardado se confirma. Un guardado con error conserva lo guardado, y si la transacción exterior se revierte no queda el archivo nuevo en disco.
- La sección está **completa** cuando hay un PDF guardado y la descripción es vacía o válida. El índice y el menú lateral la marcan igual que las demás. No marca el paso `proyecto_educativo` como completado.
- Un trámite enviado rechaza el guardado con el aviso habitual.

## Backend

- Tabla `proyecto_educativo_mapa_curricular` (migración `2026_11_17_000000`, ejecutada en la base de desarrollo por la regla permanente del dueño): una fila por `escuela_nivel`, `archivo_path`, `archivo_nombre`, `archivo_paginas` (las tres juntas o ninguna; páginas >= 1) y `descripcion` (no en blanco si existe), con `CHECK` con nombre.
- `GuardarMapaCurricularProyecto`, `ReglasMapaCurricularProyecto`, `MapaCurricularProyectoCapturada` (nunca expone la ruta de disco), `DatosMapaCurricularProyecto`, modelo `ProyectoEducativoMapaCurricular` (con `rutasDe`), `SeccionesProyectoEducativo::mapaCurricular()` y la entrada en `EstadoSeccionesProyecto`.
- `RevisarPdfProyecto::revisar()` recibe ahora el rango de páginas permitido como segundo argumento opcional (por defecto el del PAEC, así que el PAEC y la planeación de formación continua no cambian); el mapa pasa `null` (sin tope).
- Limpieza de archivos: `PurgarTramite` y `RegistrarNivelesSeleccionados` reúnen la ruta del PDF antes del borrado y la eliminan después del commit, y `ResumenEliminacionNivel` cuenta el PDF como documento a eliminar.

## Evidencia

- Pruebas escritas primero y vistas fallar por la razón correcta (clases y tabla inexistentes): 31 de aplicación, 11 de Livewire y 2 de limpieza (purga y quitar nivel).
- Después, en verde: sección 15 y las cuatro pruebas del marcador "Contenido por definir" (93, ahora apuntan a la sección 16, Programas de Estudios); PAEC, formación continua, `PurgarTest` y `RegistrarNivelesSeleccionadosTest` (111); `EscuelaNiveles`, `Archivo` y `Validacion` (216); demás archivos Livewire de Proyecto Educativo (72 + 77, incluye el menú lateral). Pint limpio, PHPStan 0 errores.
- La prueba de CHECK de la base usa un savepoint por insert (corrección de la sección 14).
- La suite completa no se corrió localmente (regla del dueño); falta el veredicto de CI. Nada se probó en un navegador (el selector de archivos y la subida real).

## Pendiente

- Recorrido en navegador de las secciones 4 a 15 (subida real de PDF, mensajes de error, "Archivo guardado").
- No hay descarga del PDF ni vista de revisor para el mapa curricular, el PAEC ni la planeación de formación continua.
- `ResumenEliminacionNivel` no cuenta las filas de texto de las secciones 1 a 15.
- Las pruebas de CHECK con bucle de inserts en secciones anteriores (por ejemplo `GuardarAcademiasProyectoTest` y `GuardarPaecProyectoTest`) pueden tener el defecto del savepoint.
- Siguiente: sección 16 (Programas de Estudios), luego 17 a 20; después hacer completable el paso, el plan del CCT provisional y la visibilidad de trámites aprobados.
