# 2026-11-12 — Proyecto Educativo, sección 11: Formación Docente, bloque 2 (Formación Docente Continua)

Trabajo directo en `master`, sin commit ni push. Acotado, con TDD. Continúa el bloque 1 (`2026-11-12-formacion-docente-seccion11-bloque1.md`).

## Qué se construyó

El bloque continuo sigue al inicial en la misma sección. Pantallas: política inicial, una por acción inicial, **política continua**, **planeación** y una por acción continua. **Cada bloque se guarda por separado**: la última pantalla del bloque inicial dice "Guardar y continuar" (guarda el bloque y pasa a la política continua, aviso "Bloque guardado"); la última del continuo dice "Guardar". Con los dos bloques completos el aviso es "Sección completa". Razón: la sección es grande y un error en el segundo bloque no debe borrar lo tecleado en el primero. Cada bloque sigue siendo todo o nada. Siguiente valida solo la pantalla actual; Anterior no valida.

- **Política Global de Formación Continua**: 100 a 250 palabras, con etiqueta y placeholder del dueño.
- **Planeación (1 a 2 cuartillas)**: el dueño eligió **PDF de 1 a 2 páginas**, igual que el PAEC (máximo 10 MB, debe ser un PDF legible, el servidor cuenta las páginas). Se muestra el nombre y las páginas del archivo guardado; guardar sin volver a subir lo conserva. Al pulsar Siguiente en esa pantalla ya se revisa el PDF (sin subirlo a disco).
- **Acciones continuas**: mismos campos, límites y entradas nativas (hora, fecha, número de horas, días) que el bloque inicial; empiezan con 1, máximo 20, mínimo 1. Se asumió que "Acción 2" del texto del dueño solo indica que la lista se repite.

## Base de datos y archivos

`2026_11_12_000700_add_planeacion_to_proyecto_educativo_formacion_politica.php` agrega `planeacion_path`, `planeacion_nombre`, `planeacion_paginas` a la fila `tipo = 'continua'` (los tres o ninguno; páginas 1 a 2). Las acciones reutilizan `proyecto_educativo_formacion_acciones` con `tipo = 'continua'`. El archivo vive en el disco `documentos`: un archivo nuevo reemplaza al anterior y el anterior se borra **solo si el guardado se confirma**; si una transacción exterior se revierte, el nuevo se borra. `PurgarTramite` y `RegistrarNivelesSeleccionados::quitar` también borran este PDF y `ResumenEliminacionNivel` lo cuenta como documento (si no, el diálogo de confirmación lo subcontaría).

## Cambios de estructura

- `RevisarPdfProyecto`: la revisión del PDF sale de `GuardarPaecProyecto` y la comparten el PAEC y la planeación (las pruebas del PAEC siguieron en verde tras moverla).
- `EscribirAccionesFormacion` (reemplazar las acciones) y `FormacionProyectoCapturada` (base de lectura) compartidos por los dos bloques.
- `ReglasFormacionProyecto::normalizar` y `errores` ahora reciben el nombre de los campos del bloque: el inicial conserva `politica_inicial` y `acciones`; el continuo usa `politica_continua`, `planeacion_continua` y `acciones_continua`. La asimetría de nombres es deliberada para no reescribir las pruebas del bloque 1.
- Nuevos: `GuardarFormacionContinuaProyecto`, `DatosFormacionContinuaProyecto`, `FormacionContinuaProyectoCapturada`, `ProyectoEducativoFormacionPolitica::rutasDe()`.
- Cambios de comportamiento del bloque 1, consecuencia del diseño de dos bloques: su aviso al guardar es "Bloque guardado" (antes "Sección completa") y la sección solo figura Completa con los dos bloques completos.

## Evidencia

- Pruebas escritas antes del código y vistas fallar: `GuardarFormacionContinuaProyectoTest` (19 pruebas, 18 en rojo), las dos de limpieza (`PurgarTest` y `RegistrarNivelesSeleccionadosTest`, fallaban porque el archivo quedaba en disco) y las de Livewire del bloque continuo junto con las del bloque 1 ajustadas (33 pruebas, 28 en rojo).
- Verde, enfocadas: Formación inicial/continua y ProyectoEducativo (43), `tests/Feature/Validacion` (71), `tests/Feature/Application/ProyectoEducativo` (259), `tests/Feature/Archivo` (91), `EscuelaNiveles` y `Admin` (114), Livewire restantes de Proyecto Educativo (122). Pint limpio, PHPStan 0.
- Un fallo de una prueba propia, corregido en la prueba: el ayudante creaba un segundo trámite, así que marcar "enviado" no afectaba al componente.
- Migración corrida en desarrollo (autorización permanente del dueño).

## Qué NO se hizo / límites

- **No se vio en navegador**: subida real del PDF y su indicador "Subiendo…", los campos nativos de hora y fecha, la casilla de días, el contador con rutas con puntos (`acciones_continua.0.proposito`) y el paso entre bloques.
- No hay descarga del PDF de planeación para el solicitante, el responsable ni el revisor (igual que el PAEC).
- Un PDF muy grande con muchas imágenes puede tardar o consumir memoria al analizarse (como en el PAEC); no se midió. La licencia de `smalot/pdfparser` es LGPL-3.0.
- La suite completa no se corrió localmente (regla del dueño); la verifica CI al subir.
- Brecha previa: `ResumenEliminacionNivel` no cuenta los textos de las secciones 1 a 11.
- `docs/progress.md` no se tocó en esta sección.

## Siguiente

Más bloques de la sección 11 si el dueño los envía; revisión en navegador de las secciones 4 a 11; las secciones 10 y 11 siguen sin commit.
