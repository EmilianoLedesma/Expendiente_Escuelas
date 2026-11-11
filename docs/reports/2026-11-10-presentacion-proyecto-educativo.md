# Presentación del Proyecto Educativo (media superior): paso del trámite

Fecha: 2026-11-10. Rama: `worktree-proyecto-educativo`. Spec: `docs/superpowers/specs/2026-11-10-presentacion-proyecto-educativo-design.md`.

## Qué se construyó

Un séptimo sub-paso del Paso 3, "Presentación del Proyecto Educativo", que aplica solo al nivel `media_superior`. En esta entrega es únicamente el paso y su compuerta: el contenido de la sección todavía no existe, así que un trámite de media superior queda con ese paso pendiente y no se puede enviar.

- `0f77cf5` — `SeccionesProyectoEducativo` (`aplicaA`, lista de secciones), séptima fila `proyecto_educativo` en `pasos_captura` (`PasosCapturaSeeder`) y aplicabilidad por nivel.
- `0fac085` — catálogo por nivel en `EstadoPaso3`; `ResumenTramite` y el modelo de lectura de SEDEQ (`ConsultarTramiteSedeq`) usan ese catálogo.
- `64d8f7f` — páginas de índice y de sección, 404 para niveles donde no aplica, y redirección desde Matrícula en `media_superior`.
- Esta tarea — prueba de que el envío queda bloqueado y la barrida de regresión. No hay cambios en `app/`.

El hub emite la sección solo en media superior (`totalPasos` 11 frente a 10 en el resto).

## Decisión: no se emite fila en lugar de `no_aplica`

Para primaria, secundaria, etc. el hub no emite la sección `proyecto_educativo`; no la muestra como `no_aplica`. Motivo: `no_aplica` ya significa "este paso existe en el nivel y se exime" (como Mobiliario en media superior). El proyecto educativo no existe en esos niveles, y mostrar una fila inerte cambiaría el conteo de pasos (`totalPasos`), la pantalla y la vista de SEDEQ de todos los trámites existentes sin aportar información. Es una desviación del texto del spec; el comportamiento de los niveles actuales queda idéntico.

## Compuerta de envío

`EnviarTramite::ejecutar` llama a `EjecutarValidacionFinal::evaluar`, que lanza `PrecondicionIncumplida` si `ResumenTramite::paraEscuela($id)->completo` es falso. No hizo falta código nuevo: la prueba lo demuestra.

- `test_tramite_de_media_superior_con_todo_lo_demas_no_se_puede_enviar`: trámite de media superior con todas las demás secciones completas (Mobiliario es `no_aplica`); `proyecto_educativo` es la única sección ni completada ni `no_aplica`, estado `pendiente`; `completo` es falso; el envío lanza `PrecondicionIncumplida`.
- `test_el_mismo_tramite_en_primaria_queda_completo`: contraprueba, `completo` es verdadero.

Fixture (`tests/Concerns/CapturaExpedienteConsistente.php`): `completarTramite(..., string $nivelClave = 'primaria')` y `registrarResponsable(..., $nivelClave)`; los llamadores existentes no cambian. Para media superior se siembra el mínimo que los seeders no traen (un grado y los cargos Director Técnico y Docente Titular de Grupo). No fue necesario el plan alterno de marcar sub-pasos con `MarcarPasoCompletado`: la captura de media superior se hizo con los casos de uso reales.

Evidencia TDD:
- RED: la prueba, antes de extender el fixture, falló con `Attempt to read property "estado" on null` (se construyó un trámite de primaria porque `completarTramite` ignoraba el cuarto argumento). 6 de 7 pruebas pasaban.
- GREEN: tras el parámetro y la siembra, `ProyectoEducativoEnTramiteTest` 7/7 (19 aserciones).

## Pendiente de ejecutar en dev (requiere visto bueno del dueño)

La base de desarrollo no tiene todavía la séptima fila del catálogo. Hay que correr `PasosCapturaSeeder` en dev para agregarla. No se ejecutó: escribe datos en una base viva. El solicitante temporal de prueba `mediasuperior.prueba@example.test` (usuario 8, solicitante 4) ya existe en dev y servirá para verlo.

## Fuera de alcance

- El contenido de la sección (formularios y datos del proyecto educativo).
- Progreso por sección dentro del paso.
- Revisión por SEDEQ de esta sección.
- Niveles superior y posgrado.

## Menores diferidos

- Tarea 1: la prueba fija solo 3 de los 20 nombres del catálogo.
- Tarea 2: en `ResumenTramiteTest` hay un empate de `orden` en `visita_inspeccion`; la prueba de SEDEQ usa `ReflectionMethod` sobre un método privado.
- Tarea 3: no hay prueba de redirección cuando el Paso 2 está incompleto para las rutas nuevas; el grado de la prueba se inserta con `DB::table('grados')`.

## Evidencia de la barrida (Paso 3 del plan)

Base de pruebas `sedeq_incorporacion_testing_ui`, un directorio a la vez, nunca la suite completa (la verdad de la suite completa es CI tras un push que pida el dueño).

| Directorio | Resultado |
|---|---|
| `tests/Feature/Application/Tramite` | 232 pruebas, 231 pasan, 1 omitida (`markTestSkipped` ajena a esta rama; no se tocó) |
| `tests/Feature/Livewire/Tramite` | 369 pruebas, 369 pasan (1527 aserciones) |
| `tests/Feature/Tramite` | 83 pruebas, 83 pasan (405 aserciones) |
| `tests/Feature/Sedeq` | 8 pruebas, 8 pasan (71 aserciones) |
| `tests/Feature/Policies` | 47 pruebas, 47 pasan (215 aserciones) |
| `tests/Feature/Database` | 159 pruebas, 159 pasan (374 aserciones) |
| `tests/Feature/Application/EscuelaNiveles` | 49 pruebas, 49 pasan (113 aserciones) |

Además: `vendor/bin/pint --test` limpio y `vendor/bin/phpstan analyse --memory-limit=1G` con 0 errores.

## Revisión final

- **Base sin la fila 7 del catálogo (importante 1).** Sin `PasosCapturaSeeder` ejecutado, guardar Matrícula en media superior redirigía a la página nueva y esta lanzaba `InvalidArgumentException` (500). Ahora `Matricula::guardar` solo redirige al Proyecto Educativo cuando `EstadoPaso3::primerPendiente` es `proyecto_educativo`; si no, cae al redirect de resumen de siempre. Las dos páginas (índice y sección) responden 404 si el paso no está en el catálogo del nivel, mediante el nuevo `EstadoPaso3::tieneEnCatalogo`. Pruebas de regresión en `Paso3ProyectoEducativoTest` y `EstadoPaso3Test`.
- **Callejón sin explicación (importante 2).** El índice ahora explica que el contenido aún no está disponible y que el trámite podrá enviarse cuando se puedan completar las secciones, y marca cada una de las 20 filas con la etiqueta estática "Pendiente" (`x-ui.status-tag`).
- Menores: prueba de redirección a Paso 2 incompleto para las dos rutas nuevas, docblocks corregidos (Matrícula, `completarTramite`) y comentario de `Paso3RequierePaso2CompletoTest` veraz.
- **Nota.** Una vez sembrada la fila del catálogo, los trámites de media superior YA ENVIADOS mostrarán el paso nuevo como completado en el hub congelado (el avance pasa a 11) y como "pendiente" en el modelo de lectura de SEDEQ; no hay fallo, es aceptable.
- **Diferido, no hecho:** memoizar `EstadoPaso3::catalogo` por nivel (hoy hace una consulta con join extra por llamada, unas 6 por nivel en el hub).
