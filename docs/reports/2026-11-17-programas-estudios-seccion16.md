# Sección 16 del Proyecto Educativo: Programas de Estudios

Fecha: 2026-11-17. Rama: `master` (trabajo directo, sin commit ni push). Solo media superior.

## Qué se construyó

Una sola página con un único "Guardar" y tres preguntas siempre visibles:

- **Periodicidad del ciclo:** Semestral, Cuatrimestral, Trimestral, Bimestral, Mensual u Otra. Con **Otra** aparece "Especifique la periodicidad" (obligatorio, hasta 60 caracteres).
- **Modalidad:** Presencial, Virtual o Mixta.
- **¿Retoma los programas de la DGB completos?:** Sí o No.

Y dos campos que aparecen **solo si la respuesta es No**:

- **Justificación y criterios de adaptación:** 200 a 500 palabras (rango del dueño).
- **Programas adaptados:** un archivo **PDF** (reemplaza al campo de texto "Nombre de la carpeta o archivo" del dueño; el nombre se toma del archivo subido). Solo PDF, validado por contenido (empieza con `%PDF-`, se puede leer, máximo 10 MB, sin tope de páginas); una imagen, un Word o un texto renombrados a `.pdf` se rechazan.

Decisiones mías, aprobadas con "Yes": que la justificación y el PDF dependan de la respuesta "No" (no hay nada que justificar si se retoman los programas sin cambios), que el archivo sea PDF (igual que la sección 15), el texto y el tope de 60 caracteres de "Otra" (el dueño no dio campo para eso), 10 MB y sin tope de páginas.

## Comportamiento

- Guardado todo o nada: se valida todo antes de escribir o subir nada.
- Con **Sí**, la justificación y el archivo enviados se ignoran (ni se validan ni se guardan). Pasar de **No a Sí** borra la justificación guardada y el PDF guardado (el PDF, solo cuando el guardado se confirma). Pasar de **Sí a No** exige justificación y PDF.
- Con **No**, sin archivo nuevo se conserva el guardado; un archivo nuevo lo reemplaza y el anterior se borra tras el commit. Un guardado con error conserva lo guardado; si la transacción exterior se revierte no queda el archivo nuevo en disco.
- Si la periodicidad no es "Otra", el texto de "Otra" se descarta.
- La sección está **completa** con las tres respuestas válidas y, si no retoma los programas, justificación válida y PDF guardado. El índice y el menú lateral la marcan igual que las demás. No marca el paso `proyecto_educativo` como completado. Un trámite enviado rechaza el guardado con el aviso habitual.

## Backend

- Tabla `proyecto_educativo_programas_estudios` (migración `2026_11_17_000100`, ejecutada en la base de desarrollo por la regla permanente del dueño): una fila por `escuela_nivel`, `periodicidad`, `periodicidad_otra`, `modalidad`, `retoma_dgb` (booleano), `justificacion`, `archivo_path/_nombre/_paginas`. `CHECK` con nombre para las listas, para la coherencia de "otra" y para la coherencia Sí/No (con sí no hay justificación ni archivo; con no hay justificación y archivo completo con páginas >= 1).
- `GuardarProgramasEstudiosProyecto`, `ReglasProgramasEstudiosProyecto`, `ProgramasEstudiosProyectoCapturada` (nunca expone la ruta de disco), `DatosProgramasEstudiosProyecto`, modelo `ProyectoEducativoProgramasEstudios` (con `rutasDe`), `SeccionesProyectoEducativo::programasEstudios()` y la entrada en `EstadoSeccionesProyecto`. Reutiliza `RevisarPdfProyecto` (sin tope de páginas) y el almacén de documentos de la sección 15.
- Los nombres de los campos del componente evitan chocar con la sección 1 (que ya usa `modalidad`): `periodicidad_ciclo`, `modalidad_programas`, `retoma_dgb`, `justificacion_adaptacion`, `archivo_programas`.
- Limpieza de archivos: `PurgarTramite` y `RegistrarNivelesSeleccionados` reúnen la ruta del PDF antes del borrado y la eliminan tras el commit; `ResumenEliminacionNivel` cuenta el PDF como documento a eliminar.

## Evidencia

- Sección 16 y las cuatro pruebas del marcador "Contenido por definir" (ahora apuntan a la sección 17, Planeación Académica): 105 en verde. Limpieza y regresiones, por tandas por el cierre conocido de PHP con corridas grandes: `Archivo` y `Validacion` 164, `EscuelaNiveles` 54, mapa curricular 31, PAEC y formación continua 46, demás archivos Livewire de Proyecto Educativo 72 + 88 (incluye el menú lateral). Pint limpio, PHPStan 0 errores.
- **No se vieron fallar primero** las pruebas de esta sección: se escribieron antes del código pero se corrieron por primera vez ya con el código escrito (en las secciones anteriores sí se vieron en rojo). La prueba de CHECK de la base usa un savepoint por insert.
- La suite completa no se corrió localmente (regla del dueño); falta el veredicto de CI. Nada se probó en un navegador (el cambio de visibilidad con los radios y la subida real).

## Pendiente

- Recorrido en navegador de las secciones 4 a 16 (radios que muestran y ocultan campos, subida de PDF, "Archivo guardado").
- No hay descarga del PDF ni vista de revisor para los programas adaptados, el mapa curricular, el PAEC ni la planeación de formación continua.
- `ResumenEliminacionNivel` no cuenta las filas de texto de las secciones 1 a 16.
- Las pruebas de CHECK con bucle de inserts en secciones anteriores (por ejemplo `GuardarAcademiasProyectoTest` y `GuardarPaecProyectoTest`) pueden tener el defecto del savepoint.
- Siguiente: sección 17 (Planeación Académica), luego 18 a 20; después hacer completable el paso, el plan del CCT provisional y la visibilidad de trámites aprobados.
