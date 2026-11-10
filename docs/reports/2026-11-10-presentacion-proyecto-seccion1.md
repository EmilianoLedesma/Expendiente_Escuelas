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

## Revisión

Ronda de correcciones tras la revisión (`.superpowers/sdd/seccion1/review.md`), en un commit aparte.

- **I-1, máscara de teléfono**: la máscara del navegador ya no puede desfasarse del servidor. Al teclear, escribe el valor enmascarado y lo empuja de forma explícita con `$wire.set(campo, valor, false)` (después de `x-model`, así gana el valor enmascarado). No recorta: con `+52`/`52` o más de 10 dígitos deja el texto como está y el servidor explica el error. La normalización vive en un solo lugar, `GuardarPresentacionProyecto::normalizar`: quita separadores y descarta una lada `+52`/`52` solo si quedan exactamente 10 dígitos (`+52 442 123 4567` se guarda como `4421234567`; `+52 442 123 45` sigue siendo error). El servidor sigue siendo la autoridad. Pruebas en Application y en Livewire. El comportamiento de Alpine en navegador sigue sin verificarse (requiere el visto bueno del dueño para usar navegador).
- **Slug**: `SeccionesProyectoEducativo::presentacion()` (primer slug del catálogo); el componente y el índice lo usan, sin copia fija en Livewire. Prueba que lo fija.
- **Migración** (editada en sitio, solo corrió en la base de pruebas): CHECK con nombre como las tablas hermanas (`pe_presentacion_*`), coherencia `ciclo_escolar_otro IS NULL OR ciclo_escolar = 'otro'` (igual para duración) y `telefono`/`celular ~ '^[0-9]{10}$'` aceptando NULL. La prueba de base ahora es un data provider con cinco filas incoherentes.
- **Pruebas de trámite enviado**: ambas fijan `etapaFaltante === TramiteEditable::ENVIADO`; la de Livewire además el toast de error con su título y descripción; la de Application, que no se escribe fila.
- **DTO**: `DatosPresentacionProyecto::campos()` se deriva por reflexión del constructor (se quitó la constante `CAMPOS`); prueba contra `aArray()`. Se quitó el `exists()` redundante de `completa()`.
- **Fechas**: `fecha_entrega` y `fecha_apertura` rechazan fechas anteriores a 1900-01-01, con prueba.
- **Sobre el formato de fecha (M-1)**: corrección de redacción: el input nativo muestra dd/mm/aaaa solo si el idioma de la interfaz del navegador es español; no depende de `<html lang>`. Es la misma limitación del Paso 2. Pendiente de aceptación explícita del dueño.
- Sin cambios: M-7 (correo `a@b`), M-8 (tope de caracteres de la síntesis), M-9, M-10.
