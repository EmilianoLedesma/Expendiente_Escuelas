# Sección 1 del Proyecto Educativo: Presentación del Proyecto Académico (media superior)

Fecha: 2026-11-10. Rama `worktree-presentacion-seccion1`, base `5633150`.

## Qué se construyó

La primera de las 20 secciones del sub-paso `proyecto_educativo` (solo media superior) pasa de marcador a formulario real con persistencia. Las otras 19 siguen mostrando "Contenido por definir".

- **Tabla** `proyecto_educativo_presentacion` (migración `2026_11_10_000000`, DDL crudo vía `DB::unprepared` como las demás): una fila por `escuela_nivel` (`UNIQUE` + FK con `ON DELETE CASCADE`), todas las columnas de datos `NULL` (se guarda borrador), `CHECK` solo en los tres enums (`modalidad`, `ciclo_escolar`, `duracion`), `created_at`/`updated_at`. Modelo `ProyectoEducativoPresentacion`.
- **Reglas en un solo lugar**: `ReglasPresentacionProyecto::errores($datos, $completa)`. Modo borrador valida formatos y máximos de lo capturado; modo completa exige además todo lo requerido y el mínimo de 500 palabras. Palabras = trozos separados por espacio en blanco (`preg_split('/\s+/u')`), contadas en servidor.
- **Escritura**: `GuardarPresentacionProyecto` (+ DTO `DatosPresentacionProyecto`). Normaliza (recorta, vacío a `null`, teléfono y celular a solo dígitos quitando separadores `() - . espacio`, texto "otro" a `null` si el enum no es `otro`), valida en modo borrador (lanza `DatosInvalidos` con claves = nombre de campo), y en transacción: bloquea el nivel, repite la compuerta de Paso 3, `TramiteEditable::asegurarEscuela` y `updateOrCreate` por nivel.
- **Lectura**: `PresentacionProyectoCapturada::datos()` y `::completa()` (misma lista de reglas, modo completa; sin fila = no completa).
- **UI**: `ProyectoEducativoSeccion` muestra el formulario solo para la sección 1 (grupos "Datos Generales", "Modalidad, Ciclo Escolar y Duración", "Síntesis de los Resultados del Estudio de Factibilidad", con las etiquetas y placeholders del dueño). Contador de palabras en vivo con Alpine (solo ayuda). Máscara `(442) 000-0000` al teclear; al cargar, los dígitos guardados se muestran con formato. Los campos "otro" aparecen al elegir "Otro". Guardar no redirige: avisa con toast.
- **Índice**: la sección 1 muestra "Completa" (`status-tag` completado) o "Pendiente" según la fila guardada; las otras 19 quedan "Pendiente".

## Decisiones

- **El slug real es `presentacion-del-proyecto-academico`** (sale de `Str::slug` del nombre), no `presentacion-proyecto-academico` como decía el encargo. Se usó el real; no se tocó `SeccionesProyectoEducativo`.
- **Propiedades en snake_case** en DTO, componente Livewire y errores, iguales a las columnas, para que una clave de error sea idéntica en todas las capas y no haga falta mapear. Es una desviación menor del camelCase de los hermanos.
- **Fechas con `<input type="date">`** (precedente en Paso 2): el navegador en `es-MX` las muestra dd/mm/aaaa; el valor viaja en ISO `Y-m-d`. No se escribió una máscara propia de fecha.
- **No se llama `MarcarPasoCompletado`** para `proyecto_educativo`: el paso no se completa y un trámite de media superior sigue sin poder enviarse.
- **Sin cambios** en acceso, 404 para otros niveles, compuerta ni política (`can:editarPaso`).
- Un trámite enviado: el caso de uso lanza `PrecondicionIncumplida(ENVIADO)` y el componente lo muestra como toast de error (la ruta ya da 403 por HTTP).
- Se actualizó la prueba existente `test_seccion_muestra_el_placeholder` para usar la sección 2, porque la 1 ya no es marcador.

## Pendiente / fuera de alcance

- Las 19 secciones restantes y el cierre del paso `proyecto_educativo`.
- La edición durante devolución (paso reabierto) pasa por `asegurarEscuela($escuelaId, 'proyecto_educativo', $nivelId)`, pero no tiene prueba propia.
- Verificación visual en navegador no realizada (no se usó navegador ni base de desarrollo).

## Evidencia

Pruebas escritas primero y vistas fallar (clases inexistentes) antes de implementar.

- `tests/Feature/Application/ProyectoEducativo/GuardarPresentacionProyectoTest.php`: requeridos, máximos, correo, teléfono/celular (10 dígitos y normalización), URL, enums, "otro" condicional, palabras 499/500/1000/1001, apertura futura/hoy, borrador parcial, bandera completa, una fila por nivel y segundo guardado actualiza, no marca el paso, trámite enviado y paso previo rechazan, `CHECK` de la base.
- `tests/Feature/Livewire/Tramite/Paso3PresentacionProyectoTest.php`: formulario y etiquetas, otras secciones con marcador, guardado y teléfono en dígitos, recarga con formato, errores por campo, trámite enviado, otro solicitante 403, primaria 404, etiqueta del índice Pendiente/Completa.
- Se ejecutaron solo directorios enfocados (sin la suite completa), en la base `sedeq_incorporacion_testing_ui`. Resultado final, Pint y PHPStan: ver `.superpowers/sdd/seccion1/report.md`.
