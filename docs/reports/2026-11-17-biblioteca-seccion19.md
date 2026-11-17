# Sección 19 del Proyecto Educativo: Servicios de Biblioteca y Acervo Bibliográfico

Fecha: 2026-11-17. Rama: `master` (trabajo directo, sin commit ni push). Solo media superior.

## Qué se construyó

Dos pasos que se guardan por separado ("Paso 1 de 2" y "Paso 2 de 2"). El dueño pidió dos bloques sin indicar el corte; el corte es una propuesta mía, aprobada con "Yes": sus cuatro encabezados, dos por paso.

**Paso 1, "Datos generales y perfiles"** (botón "Guardar y continuar")
- *Datos generales de la biblioteca escolar:* "Su biblioteca es..." (Física, Virtual o Mixta) y, solo con Virtual o Mixta, "La biblioteca virtual es..." (Propia, De dominio público o Mixta).
- *Perfiles y mecanismos:* "¿Cuenta con personal encargado de biblioteca?" (Sí/No), "Perfil de la persona encargada" (solo con Sí) y "Mecanismos y tipos de préstamo de materiales" (siempre).

**Paso 2, "Otros servicios y acervo"** (botón "Guardar")
- *Otros servicios y programas:* "¿Cuenta con programa de lectura?" (Sí/No), "Propósitos, implementación, promoción y justificación del programa de lectura" (solo con Sí) y "Otros programas o servicios" (siempre).
- *Acervo bibliográfico:* "Clasificación", "Existencia de títulos básicos (programas y planeaciones)" y "Existencia de títulos para la lectura recreativa" (siempre).

Todo texto es requerido y de **80 a 250 palabras** (rango del dueño, "por rubro").

## Comportamiento

- Cada paso valida y escribe **solo** los campos de su paso (todo o nada dentro del paso); lo ya guardado del otro no se toca. "Anterior" vuelve sin validar ni perder lo tecleado; tras guardar un paso solo se repone ese paso desde la base.
- Un campo condicional que no aplica se descarta aunque llegue y borra lo guardado: tipo de biblioteca virtual con biblioteca Física, perfil con "No" encargado, y texto del programa de lectura con "No" programa.
- La sección está **completa** únicamente con los dos pasos guardados y válidos. El índice y el menú lateral la marcan igual que las demás. Al abrir siempre se empieza en el paso 1 con lo guardado. No marca el paso `proyecto_educativo` como completado. Un trámite enviado rechaza el guardado con el aviso habitual.
- Avisos: "Paso guardado" al guardar un paso (regla del dueño de usar "Paso" en vez de "Bloque") y "Sección completa" al terminar.

## Backend

- Tabla `proyecto_educativo_biblioteca` (migración `2026_11_17_000400`, ejecutada en la base de desarrollo por la regla permanente del dueño): una fila por `escuela_nivel`; las columnas de un paso son NULL hasta que ese paso se guarda. `CHECK` con nombre para las listas, para la coherencia de lo condicional (virtual solo con virtual/mixta, perfil solo con encargado, texto del programa solo con programa), para que cada paso esté completo o vacío y para que ningún texto esté en blanco.
- `GuardarBibliotecaProyecto::ejecutar($nivelId, $bloque, DatosBibliotecaProyecto)`, `ReglasBibliotecaProyecto` (opciones, rango y `BLOQUES`), `BibliotecaProyectoCapturada` (devuelve las respuestas Sí/No como 'si'/'no'), `DatosBibliotecaProyecto`, modelo `ProyectoEducativoBiblioteca` (con casts booleanos), `SeccionesProyectoEducativo::biblioteca()` y la entrada en `EstadoSeccionesProyecto`.
- Los campos del componente llevan prefijo `biblioteca_*` y `acervo_*` para no chocar con los de otras secciones. No hay archivos, así que no hace falta limpieza al purgar un trámite o quitar un nivel (las filas se borran en cascada).

## Evidencia

- **Se vieron fallar primero** (0 de 33 pruebas de aplicación pasaban y 12 de 13 de Livewire) y luego pasaron.
- En verde: sección 19 y las cuatro pruebas del marcador "Contenido por definir" (ahora apuntan a la sección 20, Bibliografía del Proyecto): 97. Regresiones por tandas (por el cierre conocido de PHP con corridas grandes): `Archivo` y `Validacion` 166, `EscuelaNiveles` 56, calendario académico y PAEC 57, programas, planeación y mapa 98, demás archivos Livewire de Proyecto Educativo 72 + 124 (incluye el menú lateral). Pint limpio, PHPStan 0 errores.
- La prueba de CHECK de la base usa un savepoint por insert y cubre 12 combinaciones incoherentes.
- CI de la sección 17 (`c28676d`, ejecución `44133075308`) terminó en **success**; el de la sección 18 y el de esta aún no existen (sin commit ni push).
- La suite completa no se corrió localmente (regla del dueño). Nada se probó en un navegador (radios que muestran y ocultan campos, flujo entre pasos).

## Pendiente

- Recorrido en navegador de las secciones 4 a 19.
- `ResumenEliminacionNivel` no cuenta las filas de texto de las secciones 1 a 19.
- Las pruebas de CHECK con bucle de inserts en secciones anteriores (por ejemplo `GuardarAcademiasProyectoTest` y `GuardarPaecProyectoTest`) pueden tener el defecto del savepoint.
- Siguiente: sección 20 (Bibliografía del Proyecto); después decidir la refactorización de la base común de PDF (secciones 15, 17 y 18), hacer completable el paso, el plan del CCT provisional y la visibilidad de trámites aprobados.
