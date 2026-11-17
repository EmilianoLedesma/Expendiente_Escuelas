# Sección 13 del Proyecto Educativo: Criterios de Acreditación, Promoción y Certificación

Fecha: 2026-11-12. Rama: `master` (trabajo directo, sin commit ni push). Solo media superior.

## Qué se construyó

Cinco textos requeridos de 100 a 200 palabras cada uno, capturados en **dos bloques que se guardan por separado**:

- Bloque 1, "Acreditación y promoción": criterios de acreditación de asignaturas, opciones para acreditar asignaturas, criterios para la promoción de grado.
- Bloque 2, "Titulación y control escolar": criterios para la titulación, servicios de control escolar.

La división en bloques es una propuesta mía (el dueño pidió dos bloques sin indicar el corte) y fue aprobada con "Yes". Los límites de palabras vienen del dueño.

## Comportamiento

- El botón del bloque 1 dice "Guardar y continuar": valida y escribe **solo** los tres campos del bloque 1 (todo o nada dentro del bloque) y pasa al bloque 2. El botón del bloque 2 dice "Guardar" y escribe solo sus dos campos.
- "Anterior" vuelve de bloque sin validar ni perder lo tecleado. Tras guardar un bloque solo se repone desde la base lo de ese bloque, así que un borrador del otro bloque no se pierde.
- La sección está **completa** únicamente cuando los cinco campos guardados cumplen las reglas. El índice y el menú lateral la marcan igual que las demás secciones.
- Al abrir la sección siempre se empieza en el bloque 1 con lo ya guardado.
- Un trámite enviado rechaza el guardado con el aviso habitual. No marca el paso `proyecto_educativo` como completado.

## Backend

- Tabla `proyecto_educativo_criterios` (migración `2026_11_12_000900`, ejecutada en la base de desarrollo por la regla permanente del dueño): una fila por `escuela_nivel` (`UNIQUE`, `ON DELETE CASCADE`), cinco columnas `TEXT NULL` con un `CHECK` con nombre cada una (si no es NULL, no puede estar en blanco). Las columnas son NULL hasta que su bloque se guarda. El rango de palabras lo valida la capa de aplicación, no la base.
- `GuardarCriteriosProyecto::ejecutar($escuelaNivelId, $bloque, DatosCriteriosProyecto)`: compuerta del sub-paso, valida solo los campos del bloque con `ReglasCriteriosProyecto::errores($datos, $bloque)`, y escribe en transacción con `lockForUpdate` del nivel, `TramiteEditable::asegurarEscuela` y `updateOrCreate` de solo esos campos. Un bloque que no existe lanza `InvalidArgumentException`.
- `ReglasCriteriosProyecto` (rangos y bloques), `CriteriosProyectoCapturada` (lectura y `completa()`), `DatosCriteriosProyecto`, modelo `ProyectoEducativoCriterios`, `SeccionesProyectoEducativo::criterios()` y la entrada en `EstadoSeccionesProyecto`.
- Livewire: `ProyectoEducativoSeccion` (`guardarCriterios`, `anteriorCriterios`) y una rama en la vista. Los campos del componente se llaman igual que las columnas.
- La limpieza al purgar un trámite o quitar un nivel no necesita cambios: no hay archivos y las filas se borran en cascada.

## Evidencia

- Las 17 pruebas de aplicación se escribieron primero y se vieron fallar por la razón correcta (clases y tabla inexistentes). Las 10 de Livewire se escribieron antes del código pero **no se corrieron en rojo** antes de implementar.
- Después: `GuardarCriteriosProyectoTest` 17/17 y `Paso3CriteriosProyectoTest` 10/10. En la corrida en rojo, `test_la_base_rechaza_texto_en_blanco` pasó por una causa equivocada (la tabla no existía y el insert fallaba); ya con la tabla pasa por los `CHECK`.
- Cuatro pruebas del marcador "Contenido por definir" apuntaban a la sección 13 y ahora apuntan a la 14 (Plan de Estudios): `Paso3ProyectoEducativoTest`, `Paso3ObjetivosProyectoTest`, `Paso3FilosofiaProyectoTest`, `Paso3PresentacionProyectoTest`.
- Regresiones enfocadas, en tandas por el cierre conocido de PHP con corridas grandes: `tests/Feature/Validacion` 71, aplicación `ProyectoEducativo` 294 (210 + 65 + 19), Livewire de las demás secciones 123 + 38 + 21 (esta última incluye formación continua y el menú lateral, `RecorridoProyectoEducativoTest`). Pint limpio, PHPStan 0 errores.
- La suite completa no se corrió localmente (regla del dueño); falta el veredicto de CI. Nada se probó en un navegador.

## Pendiente

- Recorrido en navegador de las secciones 4 a 13 (contadores de palabras, flujo de bloques, botón "Guardar y continuar").
- Sigue sin haber descarga de PDF ni vista de revisor para los archivos del PAEC y de la planeación de formación continua; `ResumenEliminacionNivel` no cuenta las filas de texto de las secciones 1 a 13.
- Siguiente: sección 14 (Plan de Estudios), luego 15 a 20; después hacer completable el paso, el plan del CCT provisional y la visibilidad de trámites aprobados.

## Cambios posteriores (mismo día)

- **Rótulos unificados.** En todos los pasos del Proyecto Educativo que se dividen (secciones 4, 5, 7, 8, 11, 12 y 13) el contador dice ahora "Paso N de M" (antes: Campo, Bloque, Ámbito o Pantalla). El aviso al guardar un paso intermedio dice "Paso guardado" (antes "Bloque guardado") y el nombre accesible de la lista es "Pasos de la sección". Los nombres internos (`bloque`, métodos) no cambian. Pruebas: 8 archivos Livewire de las secciones, 109 en verde; Pint limpio.
- **Ícono de pestaña.** `public/favicon.png` (256x256) y `public/favicon.ico` salen de la Q del logo del login; los layouts `guest`, `tramite` y `app` lo enlazan con `<link rel="icon">`. No se vio en un navegador (los navegadores guardan el favicon en caché).
- **CI en rojo en `f9b4a30`.** Causa: dos avisos de PHPUnit (`failOnWarning`) en `GuardarEvaluacionProyectoTest` y `GuardarIngresoProyectoTest`: la prueba `test_cada_requerido_vacio_o_de_espacios_se_rechaza` recibía tres argumentos del proveedor `rangos` y aceptaba uno. Corregido con `int ...$rango`. Los avisos no se ven en la corrida local; el veredicto es el de CI.