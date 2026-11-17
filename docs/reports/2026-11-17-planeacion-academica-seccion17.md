# Sección 17 del Proyecto Educativo: Planeación Académica

Fecha: 2026-11-17. Rama: `master` (trabajo directo, sin commit ni push). Solo media superior.

## Qué se construyó

Una sola página con un único "Guardar", con dos campos requeridos:

- **Ejemplos de planeación académica:** un archivo **PDF** que reúne los tres ejemplos (de diferente campo disciplinar y semestre). Reemplaza al campo de texto "Nombre de la carpeta o archivo" del dueño: el nombre se toma del archivo subido y se muestra como "Archivo guardado". Solo PDF, validado por contenido (empieza con `%PDF-`, se puede leer, máximo 10 MB, sin tope de páginas); una imagen, un Word o un texto renombrados a `.pdf` se rechazan.
- **Descripción de los ejemplos de planeación:** requerida, de 50 a 250 palabras; debe indicar los campos disciplinares y semestres de los tres ejemplos.

Decisiones mías, aprobadas con "yes": un solo PDF con los tres ejemplos (en lugar de tres archivos), descripción requerida (a diferencia del mapa curricular, aquí el revisor necesita saber qué campos y semestres cubre cada ejemplo), el rango de 50 a 250 palabras, 10 MB y sin tope de páginas. Se ofreció la alternativa de tres PDF separados y el dueño eligió la propuesta.

## Comportamiento

- Guardado todo o nada: si falla la descripción no se sube el archivo; si falla el archivo no se escribe nada.
- Sin archivo nuevo se conserva el ya guardado; un archivo nuevo lo reemplaza y el anterior se borra solo cuando el guardado se confirma. Un guardado con error conserva lo guardado, y si la transacción exterior se revierte no queda el archivo nuevo en disco.
- La sección está **completa** cuando hay una fila guardada (PDF y descripción válida). El índice y el menú lateral la marcan igual que las demás. No marca el paso `proyecto_educativo` como completado. Un trámite enviado rechaza el guardado con el aviso habitual.

## Backend

- Tabla `proyecto_educativo_planeacion_academica` (migración `2026_11_17_000200`, ejecutada en la base de desarrollo por la regla permanente del dueño): una fila por `escuela_nivel`; `archivo_path`, `archivo_nombre`, `archivo_paginas` (>= 1) y `descripcion` son `NOT NULL` (no en blanco), porque el guardado es todo o nada.
- `GuardarPlaneacionAcademicaProyecto`, `ReglasPlaneacionAcademicaProyecto`, `PlaneacionAcademicaProyectoCapturada` (nunca expone la ruta de disco), `DatosPlaneacionAcademicaProyecto`, modelo `ProyectoEducativoPlaneacionAcademica` (con `rutasDe`), `SeccionesProyectoEducativo::planeacionAcademica()` y la entrada en `EstadoSeccionesProyecto`. Reutiliza `RevisarPdfProyecto` (sin tope de páginas) y el almacén de documentos de las secciones 15 y 16.
- Los campos del componente (`archivo_planeacion`, `descripcion_planeacion`) evitan chocar con las propiedades `planeacion` y `planeacion_continua` que ya usan el PAEC y la formación docente.
- Limpieza de archivos: `PurgarTramite` y `RegistrarNivelesSeleccionados` reúnen la ruta del PDF antes del borrado y la eliminan tras el commit; `ResumenEliminacionNivel` cuenta el PDF como documento a eliminar.

## Evidencia

- **Esta vez sí se vieron fallar primero** (0 de 28 pruebas de aplicación pasaban, y la de purga fallaba por la tabla inexistente) y luego pasaron.
- En verde: sección 17 y las cuatro pruebas del marcador "Contenido por definir" (ahora apuntan a la sección 18, Calendario Académico): 89. Regresiones por tandas (por el cierre conocido de PHP con corridas grandes): `Archivo` y `Validacion` 165, `EscuelaNiveles` 55, mapa curricular y PAEC 58, programas de estudios y formación continua 58, demás archivos Livewire de Proyecto Educativo 72 + 103 (incluye el menú lateral). Pint limpio.
- PHPStan encontró una comprobación `!== null` sobre `archivo_path` que nunca puede ser verdadera (la columna es `NOT NULL`); se eliminó en lugar de silenciarla. Ahora 0 errores.
- CI de `997c75b` (secciones 14 a 16 y la política de CI) terminó en **success**; el de esta sección aún no existe (sin commit ni push).
- La suite completa no se corrió localmente (regla del dueño). Nada se probó en un navegador (el selector de archivos y la subida real).

## Pendiente

- Recorrido en navegador de las secciones 4 a 17 (subida real de PDF, mensajes de error, "Archivo guardado").
- No hay descarga del PDF ni vista de revisor para estos archivos (planeación académica, programas adaptados, mapa curricular, PAEC y planeación de formación continua).
- `ResumenEliminacionNivel` no cuenta las filas de texto de las secciones 1 a 17.
- Las pruebas de CHECK con bucle de inserts en secciones anteriores (por ejemplo `GuardarAcademiasProyectoTest` y `GuardarPaecProyectoTest`) pueden tener el defecto del savepoint.
- Siguiente: sección 18 (Calendario Académico), luego 19 y 20; después hacer completable el paso, el plan del CCT provisional y la visibilidad de trámites aprobados.
