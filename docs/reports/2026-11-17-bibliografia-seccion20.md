# Sección 20 del Proyecto Educativo: Bibliografía del Proyecto

Fecha: 2026-11-17. Rama: `master` (trabajo directo, sin commit ni push). Solo media superior. Es la última sección: con ella las 20 secciones del Proyecto Educativo tienen formulario.

## Qué se construyó

Una página con una lista de referencias bibliográficas en formato APA, con un botón **"Agregar referencia"** que suma una tarjeta en blanco y un botón **"Quitar esta referencia"** en cada tarjeta. Mínimo 1 y máximo 50 (los límites son una propuesta mía, aprobada con "Yes"; el dueño no dio ninguno).

Cada referencia tiene campos estructurados, para que el formato APA salga igual en todas:

- **Autor(es)**, requerido, hasta 300 caracteres (por ejemplo "Pérez, J., & Gómez, L.").
- **Año**, requerido, de cuatro dígitos, de 1000 al año que viene.
- **Fuente** (editorial, revista o sitio), requerida, hasta 200 caracteres.
- **Título**, requerido, hasta 300 caracteres.
- **URL o DOI**, opcional; si se escribe debe empezar con `http://` o `https://` (hasta 500 caracteres).

Cada tarjeta muestra una **vista previa APA 7** que se actualiza al escribir: `Autores (Año). Título. Fuente. URL`, con el título en cursiva. Cada parte cierra con punto salvo que ya termine en punto, "?" o "!".

## Comportamiento

- Guardado todo o nada: reemplaza la lista completa en una transacción. Si cualquier referencia es inválida no se escribe nada, la lista guardada queda como estaba y cada error se marca en su tarjeta (`referencias.{n}.{campo}`).
- Se guarda en **orden alfabético por autor**, como pide APA (sin importar mayúsculas ni acentos; con el mismo autor, por año y luego por título). Tras guardar, la lista en pantalla se reordena igual.
- La sección está **completa** con al menos una referencia guardada y válida. El índice y el menú lateral la marcan igual que las demás. No marca el paso `proyecto_educativo` como completado. Un trámite enviado rechaza el guardado con el aviso habitual.
- Lo que llega del navegador se normaliza (cualquier valor raro se vuelve una referencia en blanco y falla la validación) y se corta en 51 elementos, así que una lista manipulada no rompe la página ni trabaja de más.

## Backend

- Tabla `proyecto_educativo_bibliografia` (migración `2026_11_17_000500`, ejecutada en la base de desarrollo por la regla permanente del dueño): una fila por referencia, con `orden` (1 a 50, único por nivel), `autores`, `anio`, `titulo`, `fuente` y `url`; `CHECK` con nombre para el orden, el año de cuatro dígitos, los textos no vacíos y la URL http/https. El formato APA no se guarda: se arma al mostrar.
- `GuardarBibliografiaProyecto`, `ReglasBibliografiaProyecto` (límites, `normalizar()` y `errores()`), `FormatoReferenciaApa` (`partes()`, `texto()` y `ordenar()`; el título va aparte para ponerlo en cursiva sin armar HTML con texto del usuario), `BibliografiaProyectoCapturada`, `DatosBibliografiaProyecto`, modelo `ProyectoEducativoBibliografia`, `SeccionesProyectoEducativo::bibliografia()` y la entrada en `EstadoSeccionesProyecto`.
- Livewire: `agregarReferencia()`, `quitarReferencia($indice)` y `guardarBibliografia()` en `ProyectoEducativoSeccion`, y una rama en la vista. No hay archivos, así que no hace falta limpieza al purgar un trámite o quitar un nivel (las filas se borran en cascada).

## Pruebas del marcador "Contenido por definir"

Con las 20 secciones construidas ya no queda ninguna con el marcador, así que las 4 pruebas que lo comprobaban quedaron sin objeto. Se eliminaron (`Paso3ProyectoEducativoTest::test_seccion_muestra_el_placeholder` y `test_las_otras_secciones_siguen_con_el_marcador` en los tests de Presentación, Objetivos y Filosofía) y se sustituyeron por una sola: `test_ninguna_de_las_veinte_secciones_muestra_el_marcador_de_contenido_por_definir`, que abre cada una de las 20 secciones y exige su nombre y la ausencia del marcador. La rama "Contenido por definir" de la vista queda sin uso; se dejó por si se agrega una sección futura.

## Evidencia

- **Se vieron fallar primero** (0 de 43 pruebas de aplicación pasaban y 11 de 12 de Livewire) y luego pasaron.
- En verde: sección 20 y las pruebas del índice y de las otras secciones tocadas (103). Regresiones por tandas (por el cierre conocido de PHP con corridas grandes): `Archivo` y `Validacion` 166, `EscuelaNiveles` 56, calendario, PAEC y biblioteca 90, programas, planeación y mapa 98, demás archivos Livewire de Proyecto Educativo 72 + 137 (incluye el menú lateral). Pint limpio (quitó dos importaciones sin uso de las pruebas eliminadas), PHPStan 0 errores.
- La prueba de CHECK de la base usa un savepoint por insert.
- CI de las secciones 18 y 19 (`d2ed92b`, ejecución `44139131171`) aún no se había leído al escribir este informe; el de esta sección no existe (sin commit ni push).
- La suite completa no se corrió localmente (regla del dueño). Nada se probó en un navegador (botones de agregar y quitar, vista previa en vivo).

## Pendiente

- Recorrido en navegador de las secciones 4 a 20.
- Hacer completable el paso `proyecto_educativo` (ahora que las 20 secciones existen), el plan del CCT provisional y la visibilidad de trámites aprobados.
- Decidir la refactorización de la base común de PDF (secciones 15, 17 y 18); con las 20 secciones a la vista, las formas son: PDF con descripción (15, 17, 18), bloques con respuestas condicionales (13, 16, 19) y listas repetidas (11, 20).
- `ResumenEliminacionNivel` no cuenta las filas de texto de las secciones 1 a 20.
- Las pruebas de CHECK con bucle de inserts en secciones anteriores (por ejemplo `GuardarAcademiasProyectoTest` y `GuardarPaecProyectoTest`) pueden tener el defecto del savepoint.
